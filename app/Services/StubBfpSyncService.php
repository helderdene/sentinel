<?php

namespace App\Services;

use App\Contracts\BfpSyncServiceInterface;
use Illuminate\Support\Facades\Log;

class StubBfpSyncService implements BfpSyncServiceInterface
{
    /**
     * Map IRMS priority levels to BFP fire alarm levels (1-5).
     */
    private const PRIORITY_TO_ALARM_LEVEL = [
        'P1' => 5,
        'P2' => 4,
        'P3' => 3,
        'P4' => 2,
        'P5' => 1,
    ];

    /**
     * Map BFP alarm levels to IRMS severity descriptors.
     */
    private const ALARM_LEVEL_TO_SEVERITY = [
        1 => 'minor',
        2 => 'moderate',
        3 => 'major',
        4 => 'critical',
        5 => 'catastrophic',
    ];

    /**
     * Push a fire incident from IRMS to BFP-AIMS.
     *
     * Builds outbound payload matching BFP-AIMS format with fire alarm levels
     * mapped from IRMS priority system.
     *
     * @param  array{incident_no: string, incident_type: string, location_text: string, barangay: string, coordinates: array{lat: float, lng: float}|null, priority: string, status: string, created_at: string}  $incidentData
     * @return array{status: string, reference_id: string, payload: array}
     */
    public function pushFireIncident(array $incidentData): array
    {
        $alarmLevel = self::PRIORITY_TO_ALARM_LEVEL[$incidentData['priority']] ?? 1;

        $outbound = [
            'fire_alarm_level' => $alarmLevel,
            'incident_type' => $incidentData['incident_type'],
            'location' => $incidentData['location_text'].', '.$incidentData['barangay'].', Butuan City',
            'coordinates' => $incidentData['coordinates'],
            'responding_unit' => 'BFP Butuan City Fire Station',
            'status' => $incidentData['status'],
            'reported_at' => $incidentData['created_at'],
            'source_system' => 'IRMS-CDRRMO',
            'source_reference' => $incidentData['incident_no'],
        ];

        Log::info('StubBfpSyncService::pushFireIncident', [
            'incident_no' => $incidentData['incident_no'],
            'alarm_level' => $alarmLevel,
        ]);

        $referenceId = 'BFP-STUB-'.strtoupper(substr(md5($incidentData['incident_no']), 0, 8));

        return [
            'status' => 'synced',
            'reference_id' => $referenceId,
            'payload' => $outbound,
        ];
    }

    /**
     * Parse an inbound fire incident from BFP-AIMS webhook.
     *
     * Normalizes BFP-AIMS webhook payload to IRMS incident format,
     * mapping alarm levels to severity descriptors.
     *
     * @return array{type: string, location: string, severity: string, source_reference: string, description: string, coordinates: array{lat: float, lng: float}|null}
     */
    public function parseInboundFireIncident(array $payload): array
    {
        $alarmLevel = $payload['alarm_level'] ?? 1;
        $severity = self::ALARM_LEVEL_TO_SEVERITY[$alarmLevel] ?? 'minor';

        $coordinates = null;
        if (isset($payload['latitude'], $payload['longitude'])) {
            $coordinates = [
                'lat' => (float) $payload['latitude'],
                'lng' => (float) $payload['longitude'],
            ];
        }

        Log::info('StubBfpSyncService::parseInboundFireIncident', [
            'reference_no' => $payload['reference_no'] ?? 'unknown',
            'alarm_level' => $alarmLevel,
        ]);

        return [
            'type' => 'Structure Fire',
            'location' => $payload['location'] ?? '',
            'severity' => $severity,
            'source_reference' => $payload['reference_no'] ?? '',
            'description' => $payload['description'] ?? '',
            'coordinates' => $coordinates,
        ];
    }
}
