<?php

namespace App\Http\Controllers;

use App\Models\IncidentType;
use App\Services\FrasIncidentFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IoTWebhookController extends Controller
{
    public function __construct(
        private FrasIncidentFactory $factory,
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

        $incident = $this->factory->createFromSensor($validated, $mapping, $incidentType);

        return response()->json([
            'incident_no' => $incident->incident_no,
            'incident_id' => $incident->id,
        ], 201);
    }
}
