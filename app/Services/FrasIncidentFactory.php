<?php

namespace App\Services;

use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\PersonnelCategory;
use App\Enums\RecognitionSeverity;
use App\Events\IncidentCreated;
use App\Events\RecognitionAlertReceived;
use App\Models\Camera;
use App\Models\Incident;
use App\Models\IncidentTimeline;
use App\Models\IncidentType;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Single load-bearing bridge between the FRAS recognition pipeline + IoT sensor
 * webhook and the Incident domain. All Wave 2+ surfaces (map pulse, IntakeStation
 * rail, escalate-to-P1 action) read state this factory writes.
 *
 * - createFromSensor() is factored VERBATIM from IoTWebhookController lines 56–92
 *   so the v1.0 IoT webhook contract (tests/Feature/Intake/IoTWebhookTest.php)
 *   passes UNCHANGED after the controller delegates here.
 * - createFromRecognition() implements the 5-gate chain per CONTEXT D-07:
 *   severity → confidence → personnel category → dedup → write.
 */
final class FrasIncidentFactory
{
    /**
     * Memoized IncidentType row for the FRAS recognition path. Avoids a
     * per-event DB roundtrip for a row that never changes during a request.
     */
    private ?IncidentType $personOfInterestType = null;

    public function __construct(
        private BarangayLookupService $barangayLookup,
    ) {}

    /**
     * Create an Incident from a validated IoT sensor webhook payload.
     *
     * Body factored verbatim from the pre-Phase-21 IoTWebhookController; the
     * controller itself is now a thin delegate that invokes this method.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $mapping
     */
    public function createFromSensor(array $validated, array $mapping, IncidentType $incidentType): Incident
    {
        return DB::transaction(function () use ($validated, $mapping, $incidentType) {
            $sensorType = $validated['sensor_type'];

            $data = [
                'incident_type_id' => $incidentType->id,
                'priority' => IncidentPriority::from($mapping['priority']),
                'status' => IncidentStatus::Pending,
                'channel' => IncidentChannel::IoT,
                'location_text' => $validated['location_text'] ?? null,
                'notes' => "IoT Alert: {$sensorType} sensor {$validated['sensor_id']} reported value {$validated['value']} exceeding threshold {$validated['threshold']}",
                'raw_message' => json_encode($validated),
            ];

            $latitude = $validated['latitude'] ?? null;
            $longitude = $validated['longitude'] ?? null;

            if ($latitude !== null && $longitude !== null) {
                $data['coordinates'] = Point::makeGeodetic((float) $latitude, (float) $longitude);
                $barangay = $this->barangayLookup->findByCoordinates((float) $latitude, (float) $longitude);

                if ($barangay) {
                    $data['barangay_id'] = $barangay->id;
                }
            }

            $incident = Incident::query()->create($data);

            IncidentTimeline::query()->create([
                'incident_id' => $incident->id,
                'event_type' => 'incident_created',
                'event_data' => [
                    'source' => 'iot_sensor',
                    'sensor_type' => $sensorType,
                    'sensor_id' => $validated['sensor_id'],
                ],
            ]);

            $incident->load('incidentType', 'barangay');
            IncidentCreated::dispatch($incident);

            return $incident;
        });
    }

    /**
     * Create an Incident from a persisted RecognitionEvent, applying the
     * 5-gate chain in CONTEXT D-07 order. Returns the Incident on success,
     * or null when any gate blocks (Warning still broadcasts alert-only).
     */
    public function createFromRecognition(RecognitionEvent $event): ?Incident
    {
        // Gate 1: severity — only Critical proceeds to write path. Warning
        // broadcasts alert-only (incident=null); Info is silent history-only.
        if ($event->severity !== RecognitionSeverity::Critical) {
            if ($event->severity === RecognitionSeverity::Warning) {
                RecognitionAlertReceived::dispatch($event, null);
            }

            return null;
        }

        // Gate 2: confidence threshold.
        $threshold = (float) config('fras.recognition.confidence_threshold', 0.75);
        if ((float) $event->similarity < $threshold) {
            return null;
        }

        // Gate 3: personnel category. Null personnel (unknown face) and
        // allow-list matches never create Incidents.
        $personnel = $event->personnel_id
            ? Personnel::query()->find($event->personnel_id)
            : null;

        if (! $personnel || $personnel->category === PersonnelCategory::Allow) {
            return null;
        }

        // Gate 4: dedup — atomic Redis SET NX. Cache::add returns false when
        // the key already exists, which is the "duplicate within window" path.
        $dedupKey = "fras:incident-dedup:{$event->camera_id}:{$event->personnel_id}";
        $ttl = (int) config('fras.recognition.dedup_window_seconds', 60);

        if (! Cache::add($dedupKey, true, $ttl)) {
            return null;
        }

        // Gate 5: write path — all gates passed, create the Incident.
        return DB::transaction(function () use ($event, $personnel) {
            $type = $this->personOfInterestType ??= IncidentType::query()
                ->where('code', 'person_of_interest')
                ->firstOrFail();

            $priority = $this->resolvePriority($event->severity, $personnel->category);
            /** @var Camera|null $camera */
            $camera = $event->camera()->first();

            $incident = Incident::query()->create([
                'incident_type_id' => $type->id,
                'priority' => $priority,
                'status' => IncidentStatus::Pending,
                'channel' => IncidentChannel::IoT,
                'coordinates' => $camera?->location,
                'barangay_id' => $camera?->barangay_id,
                'location_text' => $camera?->name ?? $camera?->camera_id_display,
                'notes' => $this->formatNotes($event, $personnel, $camera),
                'raw_message' => json_encode($event->raw_payload ?? []),
            ]);

            IncidentTimeline::query()->create([
                'incident_id' => $incident->id,
                'event_type' => 'incident_created',
                'event_data' => [
                    'source' => 'fras_recognition',
                    'recognition_event_id' => $event->id,
                    'camera_id' => $event->camera_id,
                    'personnel_id' => $event->personnel_id,
                    'personnel_category' => $personnel->category->value,
                    'confidence' => (float) $event->similarity,
                    'captured_at' => $event->captured_at->toIso8601String(),
                ],
            ]);

            $event->incident_id = $incident->id;
            $event->save();

            $incident->load('incidentType', 'barangay');
            IncidentCreated::dispatch($incident);
            RecognitionAlertReceived::dispatch($event, $incident);

            return $incident;
        });
    }

    /**
     * Operator-driven manual promotion path (Phase 22 D-12, D-13).
     *
     * Additive to createFromRecognition; neither sensor nor automatic-
     * recognition paths are modified. The operator override explicitly
     * SKIPS the severity, confidence and dedup gates — only the category
     * gate remains (unmatched faces and allow-list matches never create
     * Incidents, even manually).
     *
     * The Incident timeline entry is tagged with trigger='fras_operator_promote'
     * and carries the audit fields (promoted_by_user_id, promotion_reason,
     * promoted_priority) for DPA compliance reporting.
     */
    public function createFromRecognitionManual(
        RecognitionEvent $event,
        IncidentPriority $priority,
        string $reason,
        User $actor,
    ): Incident {
        $personnel = $event->personnel_id
            ? Personnel::query()->find($event->personnel_id)
            : null;

        if (! $personnel) {
            abort(422, 'Cannot promote: no personnel match.');
        }

        if ($personnel->category === PersonnelCategory::Allow) {
            abort(422, 'Cannot promote: allow-list match.');
        }

        return DB::transaction(function () use ($event, $personnel, $priority, $reason, $actor) {
            $type = $this->personOfInterestType ??= IncidentType::query()
                ->where('code', 'person_of_interest')
                ->firstOrFail();

            /** @var Camera|null $camera */
            $camera = $event->camera()->first();

            $incident = Incident::query()->create([
                'incident_type_id' => $type->id,
                'priority' => $priority,
                'status' => IncidentStatus::Pending,
                'channel' => IncidentChannel::IoT,
                'coordinates' => $camera?->location,
                'barangay_id' => $camera?->barangay_id,
                'location_text' => $camera?->name ?? $camera?->camera_id_display,
                'notes' => $this->formatNotes($event, $personnel, $camera)
                    ." — Manually promoted by {$actor->name}: {$reason}",
                'raw_message' => json_encode($event->raw_payload ?? []),
            ]);

            IncidentTimeline::query()->create([
                'incident_id' => $incident->id,
                'event_type' => 'incident_created',
                'event_data' => [
                    'source' => 'fras_recognition',
                    'trigger' => 'fras_operator_promote',
                    'recognition_event_id' => $event->id,
                    'camera_id' => $event->camera_id,
                    'personnel_id' => $event->personnel_id,
                    'personnel_category' => $personnel->category->value,
                    'confidence' => (float) $event->similarity,
                    'captured_at' => $event->captured_at->toIso8601String(),
                    'promoted_by_user_id' => $actor->id,
                    'promoted_priority' => $priority->value,
                    'promotion_reason' => $reason,
                ],
            ]);

            $event->incident_id = $incident->id;
            $event->save();

            $incident->load('incidentType', 'barangay');
            IncidentCreated::dispatch($incident);
            RecognitionAlertReceived::dispatch($event, $incident);

            return $incident;
        });
    }

    /**
     * Resolve IncidentPriority from config('fras.recognition.priority_map')
     * with a P2 safety fallback if the (severity, category) cell is unset.
     */
    private function resolvePriority(RecognitionSeverity $severity, PersonnelCategory $category): IncidentPriority
    {
        /** @var array<string, array<string, string>> $map */
        $map = config('fras.recognition.priority_map', []);
        $code = $map[$severity->value][$category->value] ?? 'P2';

        return IncidentPriority::from($code);
    }

    /**
     * Format the human-readable notes body per CONTEXT D-03 for dispatchers
     * reading the Incident without opening the full recognition history.
     */
    private function formatNotes(RecognitionEvent $event, Personnel $personnel, ?Camera $camera): string
    {
        $labels = [
            PersonnelCategory::Block->value => 'Block-list match',
            PersonnelCategory::Missing->value => 'Missing person sighting',
            PersonnelCategory::LostChild->value => 'Lost child sighting',
        ];

        $label = $labels[$personnel->category->value] ?? 'Recognition match';
        $confidence = number_format((float) $event->similarity * 100, 1);
        $cameraDisplay = $camera?->camera_id_display ?? 'unknown camera';

        return "FRAS Alert: {$label} — {$personnel->name} matched on {$cameraDisplay} at {$confidence}% confidence";
    }
}
