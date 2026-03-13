<?php

namespace App\Contracts;

interface PnpBlotterServiceInterface
{
    /**
     * Create an e-Blotter entry for a criminal incident.
     *
     * @param  array{incident_no: string, incident_type: string, location_text: string, barangay: string, notes: string, reporting_unit: string, created_at: string, resolved_at: string|null, outcome: string}  $incidentData
     * @return array{status: string, blotter_no: string, payload: array}
     */
    public function createBlotterEntry(array $incidentData): array;
}
