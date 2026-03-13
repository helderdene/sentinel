<?php

namespace App\Contracts;

interface HospitalEhrServiceInterface
{
    /**
     * Send patient pre-notification to hospital EHR system (HL7 FHIR R4).
     *
     * @param  array{blood_pressure?: string, heart_rate?: int, spo2?: int, gcs?: int}  $vitals
     * @param  string[]  $assessmentTags
     * @return array{status: string, reference_id: string, fhir_payload: array}
     */
    public function preNotify(
        string $hospitalId,
        string $incidentNo,
        string $incidentType,
        array $vitals,
        array $assessmentTags,
        string $etaMinutes,
        string $unitCallsign,
    ): array;
}
