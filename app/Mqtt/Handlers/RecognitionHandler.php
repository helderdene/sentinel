<?php

namespace App\Mqtt\Handlers;

use App\Enums\RecognitionSeverity;
use App\Models\Camera;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use App\Mqtt\Contracts\MqttHandler;
use App\Services\FrasIncidentFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RecognitionHandler implements MqttHandler
{
    public function __construct(
        private FrasIncidentFactory $factory,
    ) {}

    /** Face image size cap in bytes (1 MB) — spec §5 `pic`. */
    private const FACE_IMAGE_MAX_BYTES = 1_048_576;

    /** Scene image size cap in bytes (2 MB) — spec §5 `scene`. */
    private const SCENE_IMAGE_MAX_BYTES = 2_097_152;

    /**
     * Parse a V1.25 RecPush payload (spec §5), classify severity, persist a
     * recognition_events row idempotently (camera_id, record_id UNIQUE), and
     * write face + scene images to the fras_events private disk under
     * {YYYY-MM-DD}/{faces|scenes}/{event_id}.jpg.
     *
     * V1.25 envelope: { operator: "RecPush", info: { customId, personId,
     * RecordID, VerifyStatus, PersonType, similarity1 (0–100), Sendintime,
     * personName, facesluiceId, idCard, telnum, time, isNoMask, PushType,
     * targetPosInScene, pic, scene } }. All FRAS record data lives under `info`;
     * the outer deviceId is carried by the topic path (mqtt/face/{deviceId}/Rec)
     * rather than the payload.
     */
    public function handle(string $topic, string $message): void
    {
        $payload = json_decode($message, true);

        if (! is_array($payload)) {
            Log::channel('mqtt')->warning('RecPush payload not JSON', ['topic' => $topic]);

            return;
        }

        if (($payload['operator'] ?? null) !== 'RecPush') {
            Log::channel('mqtt')->warning('RecPush operator mismatch', [
                'topic' => $topic,
                'operator' => $payload['operator'] ?? null,
            ]);

            return;
        }

        $info = is_array($payload['info'] ?? null) ? $payload['info'] : null;

        if ($info === null) {
            Log::channel('mqtt')->warning('RecPush missing info block', ['topic' => $topic]);

            return;
        }

        // V1.25 drops top-level deviceId; extract from the topic path and
        // cross-check against info.facesluiceId when present.
        $deviceId = $this->extractDeviceIdFromTopic($topic);
        $recordId = $info['RecordID'] ?? null;

        if ($deviceId === null || $recordId === null) {
            Log::channel('mqtt')->warning('RecPush missing deviceId or recordId', [
                'topic' => $topic,
            ]);

            return;
        }

        $camera = Camera::where('device_id', $deviceId)->first();

        if (! $camera) {
            Log::channel('mqtt')->warning('RecPush for unknown camera', [
                'device_id' => $deviceId,
                'topic' => $topic,
            ]);

            return;
        }

        // Firmware-typo fallback preserved from V1.x deployments (D-61).
        $personName = $info['personName'] ?? $info['persionName'] ?? null;
        $personType = (int) ($info['PersonType'] ?? 0);
        $verifyStatus = (int) ($info['VerifyStatus'] ?? 0);
        $similarity = $this->normalizeSimilarity($info['similarity1'] ?? 0);

        $customId = $this->nullableString($info['customId'] ?? null);
        $cameraPersonId = $this->nullableString($info['personId'] ?? null);
        $facesluiceId = $this->nullableString($info['facesluiceId'] ?? null);
        $idCard = $this->nullableString($info['idCard'] ?? null);
        $phone = $this->nullableString($info['telnum'] ?? null);

        $isNoMask = (int) ($info['isNoMask'] ?? 0);
        // Sendintime: 1 = real-time (within 10s), 0 = replayed/stored-and-forward.
        $isRealTime = (int) ($info['Sendintime'] ?? 1) === 1;
        $targetBbox = is_array($info['targetPosInScene'] ?? null) ? $info['targetPosInScene'] : null;

        $severity = RecognitionSeverity::fromEvent($personType, $verifyStatus);
        $capturedAt = $this->parseCapturedAt($info['time'] ?? null);

        // V1.25 echoes the platform-assigned customId back on RecPush, so we
        // can resolve personnel_id here rather than deferring to a FaceMatcher.
        // Factory Gate 3 (personnel_id NOT NULL + not allow-list) relies on this.
        $personnelId = $customId !== null
            ? Personnel::where('custom_id', $customId)->value('id')
            : null;

        try {
            // Nested DB::transaction emits a SAVEPOINT on Postgres so a
            // UNIQUE violation here does NOT poison the outer
            // RefreshDatabase test transaction (D-03 idempotency).
            /** @var RecognitionEvent $event */
            $event = DB::transaction(fn () => RecognitionEvent::create([
                'camera_id' => $camera->id,
                'personnel_id' => $personnelId,
                'record_id' => (int) $recordId,
                'custom_id' => $customId,
                'camera_person_id' => $cameraPersonId,
                'facesluice_id' => $facesluiceId,
                'name_from_camera' => $personName,
                'person_type' => $personType,
                'verify_status' => $verifyStatus,
                'similarity' => $similarity,
                'is_real_time' => $isRealTime,
                'is_no_mask' => $isNoMask,
                'id_card' => $idCard,
                'phone' => $phone,
                'target_bbox' => $targetBbox,
                'severity' => $severity,
                'raw_payload' => $payload,
                'captured_at' => $capturedAt,
                'received_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            // D-03: duplicate (camera_id, record_id) → idempotent no-op.
            Log::channel('mqtt')->info('Duplicate RecPush rejected at DB layer', [
                'camera_id' => $camera->id,
                'record_id' => $recordId,
            ]);

            return;
        }

        $datePrefix = $capturedAt->format('Y-m-d');

        $this->persistImage(
            $info['pic'] ?? null,
            "{$datePrefix}/faces/{$event->id}.jpg",
            self::FACE_IMAGE_MAX_BYTES,
            'face',
            $event,
            'face_image_path',
        );

        $this->persistImage(
            $info['scene'] ?? null,
            "{$datePrefix}/scenes/{$event->id}.jpg",
            self::SCENE_IMAGE_MAX_BYTES,
            'scene',
            $event,
            'scene_image_path',
        );

        // Phase 21 D-07/D-10: hand the persisted event to the factory. The
        // factory owns the 5-gate chain and all downstream broadcasts
        // (IncidentCreated + RecognitionAlertReceived). Return value is
        // informational only — the handler's contract is persist + images.
        $this->factory->createFromRecognition($event);
    }

    /**
     * Extract {deviceId} from an MQTT topic of shape `<prefix>/{deviceId}/Rec`.
     */
    private function extractDeviceIdFromTopic(string $topic): ?string
    {
        return preg_match('#/([^/]+)/Rec$#', $topic, $m) === 1 ? $m[1] : null;
    }

    /**
     * V1.25 `similarity1` is a 0–100 score (often emitted as a string like
     * "94.000000"); the schema stores a 0–1 decimal(5,2). Normalise by dividing
     * anything >1 by 100 so both representations persist consistently.
     */
    private function normalizeSimilarity(mixed $raw): float
    {
        $value = is_numeric($raw) ? (float) $raw : 0.0;

        if ($value > 1.0) {
            $value /= 100.0;
        }

        return round($value, 4);
    }

    /**
     * V1.25 emits `time` as "YYYY-MM-DD HH:mm:ss" in camera-local wall time.
     * With APP_TIMEZONE=Asia/Manila that parses directly; we still accept ISO
     * 8601 strings with explicit offsets for legacy fixtures and tests.
     */
    private function parseCapturedAt(?string $time): Carbon
    {
        if ($time === null || $time === '') {
            return now();
        }

        try {
            return Carbon::parse($time);
        } catch (Throwable) {
            return now();
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = is_string($value) ? $value : (string) $value;

        return $string === '' ? null : $string;
    }

    /**
     * Decode a base64 image, enforce the size cap, write to the fras_events disk,
     * and persist the resulting path on the given RecognitionEvent column. V1.25
     * may wrap the payload as a data URI ("data:image/jpeg;base64,..."); strip
     * the prefix before decoding.
     */
    private function persistImage(?string $raw, string $path, int $maxBytes, string $kind, RecognitionEvent $event, string $column): void
    {
        if ($raw === null || $raw === '') {
            return;
        }

        $base64 = $raw;

        if (str_starts_with($base64, 'data:') && ($comma = strpos($base64, ',')) !== false) {
            $base64 = substr($base64, $comma + 1);
        }

        $binary = base64_decode($base64, true);

        if ($binary === false || $binary === '') {
            Log::channel('mqtt')->warning("Invalid base64 for {$kind} image", ['event_id' => $event->id]);

            return;
        }

        if (strlen($binary) > $maxBytes) {
            Log::channel('mqtt')->warning("{$kind} image exceeds size cap", [
                'event_id' => $event->id,
                'bytes' => strlen($binary),
                'cap_bytes' => $maxBytes,
            ]);

            return;
        }

        Storage::disk('fras_events')->put($path, $binary);
        $event->update([$column => $path]);
    }
}
