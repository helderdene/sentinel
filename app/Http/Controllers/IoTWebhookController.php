<?php

namespace App\Http\Controllers;

use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Models\Incident;
use App\Models\IncidentTimeline;
use App\Models\IncidentType;
use App\Services\BarangayLookupService;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IoTWebhookController extends Controller
{
    public function __construct(
        private BarangayLookupService $barangayLookup,
    ) {}

    /**
     * Handle incoming IoT sensor webhook payload.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sensor_type' => ['required', 'string'],
            'sensor_id' => ['required', 'string'],
            'value' => ['required', 'numeric'],
            'threshold' => ['required', 'numeric'],
            'location_text' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $mappings = config('services.iot.sensor_mappings');
        $sensorType = $validated['sensor_type'];

        if (! isset($mappings[$sensorType])) {
            return response()->json([
                'error' => "Unknown sensor type: {$sensorType}",
            ], 422);
        }

        $mapping = $mappings[$sensorType];
        $incidentType = IncidentType::query()->where('code', $mapping['incident_type_code'])->first();

        if (! $incidentType) {
            return response()->json([
                'error' => "Incident type not found for code: {$mapping['incident_type_code']}",
            ], 422);
        }

        $data = [
            'incident_type_id' => $incidentType->id,
            'priority' => IncidentPriority::from($mapping['priority']),
            'status' => IncidentStatus::Pending,
            'channel' => IncidentChannel::IoT,
            'location_text' => $validated['location_text'] ?? null,
            'notes' => "IoT Alert: {$sensorType} sensor {$validated['sensor_id']} reported value {$validated['value']} exceeding threshold {$validated['threshold']}",
            'raw_message' => json_encode($request->all()),
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

        return response()->json([
            'incident_no' => $incident->incident_no,
            'incident_id' => $incident->id,
        ], 201);
    }
}
