<?php

namespace App\Services;

use App\Contracts\HospitalEhrServiceInterface;
use Illuminate\Support\Facades\Log;

class StubHospitalEhrService implements HospitalEhrServiceInterface
{
    /** @var array<string, string> LOINC codes for common vitals */
    private const VITAL_LOINC = [
        'blood_pressure' => '85354-9',
        'heart_rate' => '8867-4',
        'spo2' => '2708-6',
        'gcs' => '9269-2',
    ];

    /** @var array<string, string> Human-readable vital names */
    private const VITAL_DISPLAY = [
        'blood_pressure' => 'Blood Pressure',
        'heart_rate' => 'Heart Rate',
        'spo2' => 'Oxygen Saturation (SpO2)',
        'gcs' => 'Glasgow Coma Scale (GCS)',
    ];

    /** @var array<string, string> Vital measurement units */
    private const VITAL_UNITS = [
        'blood_pressure' => 'mmHg',
        'heart_rate' => '/min',
        'spo2' => '%',
        'gcs' => '{score}',
    ];

    /**
     * Send patient pre-notification to hospital EHR system (HL7 FHIR R4).
     *
     * Builds a FHIR R4 Bundle with Patient, Encounter, and Observation resources.
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
    ): array {
        $hospitalName = $this->resolveHospitalName($hospitalId);
        $patientRef = 'urn:uuid:patient-'.$incidentNo;

        $patient = $this->buildPatientResource($incidentNo, $patientRef);
        $encounter = $this->buildEncounterResource(
            $incidentNo, $incidentType, $hospitalName, $patientRef, $etaMinutes, $unitCallsign, $assessmentTags,
        );
        $observations = $this->buildObservationResources($vitals, $patientRef);

        $entries = [
            ['fullUrl' => $patientRef, 'resource' => $patient, 'request' => ['method' => 'POST', 'url' => 'Patient']],
            ['fullUrl' => 'urn:uuid:encounter-'.$incidentNo, 'resource' => $encounter, 'request' => ['method' => 'POST', 'url' => 'Encounter']],
        ];

        foreach ($observations as $i => $observation) {
            $entries[] = [
                'fullUrl' => 'urn:uuid:observation-'.$incidentNo.'-'.$i,
                'resource' => $observation,
                'request' => ['method' => 'POST', 'url' => 'Observation'],
            ];
        }

        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'transaction',
            'entry' => $entries,
        ];

        $referenceId = 'FHIR-STUB-'.strtoupper(substr(md5($incidentNo), 0, 8));

        Log::info('StubHospitalEhrService::preNotify', [
            'hospitalId' => $hospitalId,
            'incidentNo' => $incidentNo,
            'resources' => count($entries),
        ]);

        return [
            'status' => 'accepted',
            'reference_id' => $referenceId,
            'fhir_payload' => $bundle,
        ];
    }

    /**
     * Look up hospital name from config/hospitals.php by ID.
     */
    private function resolveHospitalName(string $hospitalId): string
    {
        $hospitals = config('hospitals', []);

        foreach ($hospitals as $hospital) {
            if ($hospital['id'] === $hospitalId) {
                return $hospital['name'];
            }
        }

        return 'Unknown Hospital ('.$hospitalId.')';
    }

    /**
     * Build FHIR R4 Patient resource.
     *
     * @return array<string, mixed>
     */
    private function buildPatientResource(string $incidentNo, string $patientRef): array
    {
        return [
            'resourceType' => 'Patient',
            'identifier' => [
                [
                    'system' => 'urn:cdrrmo:butuan:incident',
                    'value' => $incidentNo,
                ],
            ],
            'name' => [
                [
                    'use' => 'usual',
                    'text' => 'Emergency Patient',
                ],
            ],
        ];
    }

    /**
     * Build FHIR R4 Encounter resource.
     *
     * @param  string[]  $assessmentTags
     * @return array<string, mixed>
     */
    private function buildEncounterResource(
        string $incidentNo,
        string $incidentType,
        string $hospitalName,
        string $patientRef,
        string $etaMinutes,
        string $unitCallsign,
        array $assessmentTags,
    ): array {
        $encounter = [
            'resourceType' => 'Encounter',
            'status' => 'planned',
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => 'EMER',
                'display' => 'Emergency',
            ],
            'subject' => [
                'reference' => $patientRef,
                'display' => 'Emergency Patient ('.$incidentNo.')',
            ],
            'serviceProvider' => [
                'display' => $hospitalName,
            ],
            'reasonCode' => [
                [
                    'text' => $incidentType,
                ],
            ],
            'period' => [
                'start' => now()->addMinutes((int) $etaMinutes)->toIso8601String(),
            ],
        ];

        if (! empty($assessmentTags)) {
            $encounter['extension'] = [
                [
                    'url' => 'urn:cdrrmo:butuan:assessment-tags',
                    'valueString' => implode(', ', $assessmentTags),
                ],
            ];
        }

        if (! empty($unitCallsign)) {
            $encounter['participant'] = [
                [
                    'type' => [
                        [
                            'text' => 'Transport Unit',
                        ],
                    ],
                    'individual' => [
                        'display' => $unitCallsign,
                    ],
                ],
            ];
        }

        return $encounter;
    }

    /**
     * Build FHIR R4 Observation resources for each non-null vital sign.
     *
     * @param  array<string, mixed>  $vitals
     * @return array<int, array<string, mixed>>
     */
    private function buildObservationResources(array $vitals, string $patientRef): array
    {
        $observations = [];

        foreach ($vitals as $key => $value) {
            if ($value === null || ! isset(self::VITAL_LOINC[$key])) {
                continue;
            }

            $observation = [
                'resourceType' => 'Observation',
                'status' => 'preliminary',
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => self::VITAL_LOINC[$key],
                            'display' => self::VITAL_DISPLAY[$key],
                        ],
                    ],
                ],
                'subject' => [
                    'reference' => $patientRef,
                ],
                'effectiveDateTime' => now()->toIso8601String(),
            ];

            if (is_string($value)) {
                $observation['valueString'] = $value;
            } else {
                $observation['valueQuantity'] = [
                    'value' => $value,
                    'unit' => self::VITAL_UNITS[$key],
                    'system' => 'http://unitsofmeasure.org',
                ];
            }

            $observations[] = $observation;
        }

        return $observations;
    }
}
