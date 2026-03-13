<?php

namespace App\Contracts;

interface BfpSyncServiceInterface
{
    /**
     * Push a fire incident from IRMS to BFP-AIMS.
     *
     * @param  array{incident_no: string, incident_type: string, location_text: string, barangay: string, coordinates: array{lat: float, lng: float}|null, priority: string, status: string, created_at: string}  $incidentData
     * @return array{status: string, reference_id: string, payload: array}
     */
    public function pushFireIncident(array $incidentData): array;

    /**
     * Parse an inbound fire incident from BFP-AIMS webhook.
     *
     * @return array{type: string, location: string, severity: string, source_reference: string, description: string, coordinates: array{lat: float, lng: float}|null}
     */
    public function parseInboundFireIncident(array $payload): array;
}
