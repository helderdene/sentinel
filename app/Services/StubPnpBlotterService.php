<?php

namespace App\Services;

use App\Contracts\PnpBlotterServiceInterface;
use Illuminate\Support\Facades\Log;

class StubPnpBlotterService implements PnpBlotterServiceInterface
{
    /**
     * Create an e-Blotter entry for a criminal incident.
     *
     * Builds a 5W1H (Who, What, When, Where, Why, How) payload matching
     * PNP e-Blotter reporting conventions for Butuan City.
     *
     * @param  array{incident_no: string, incident_type: string, location_text: string, barangay: string, notes: string, reporting_unit: string, created_at: string, resolved_at: string|null, outcome: string}  $incidentData
     * @return array{status: string, blotter_no: string, payload: array}
     */
    public function createBlotterEntry(array $incidentData): array
    {
        $blotter = [
            'who' => 'Unknown suspect',
            'what' => $incidentData['incident_type'],
            'when' => $incidentData['created_at'],
            'where' => $incidentData['location_text'].', '.$incidentData['barangay'].', Butuan City',
            'why' => 'Under investigation',
            'how' => $incidentData['notes'],
            'blotter_no' => 'BLT-'.date('Y').'-'.str_pad(
                (string) abs(crc32($incidentData['incident_no']) % 99999),
                5,
                '0',
                STR_PAD_LEFT,
            ),
            'reporting_unit' => $incidentData['reporting_unit'],
            'status' => 'Filed',
            'police_station' => 'Butuan City Police Station',
            'outcome' => $incidentData['outcome'],
            'resolved_at' => $incidentData['resolved_at'],
        ];

        Log::info('StubPnpBlotterService::createBlotterEntry', [
            'incident_no' => $incidentData['incident_no'],
            'blotter_no' => $blotter['blotter_no'],
        ]);

        $blotterNo = 'BLT-STUB-'.strtoupper(substr(md5($incidentData['incident_no']), 0, 8));

        return [
            'status' => 'filed',
            'blotter_no' => $blotterNo,
            'payload' => $blotter,
        ];
    }
}
