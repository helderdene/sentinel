<?php

namespace App\Contracts;

interface NdrrmcReportServiceInterface
{
    /**
     * Submit a Situation Report (SitRep) to NDRRMC.
     *
     * @param  array{incident_no: string, incident_type: string, priority: string, location_text: string, barangay: string, coordinates: array{lat: float, lng: float}|null, outcome: string, units_deployed: int, timeline_summary: string, created_at: string, resolved_at: string|null}  $incidentData
     * @return array{status: string, reference_id: string, xml_payload: string}
     */
    public function submitSitRep(array $incidentData): array;
}
