<?php

namespace Database\Seeders;

use App\Models\IncidentOutcome;
use Illuminate\Database\Seeder;

class IncidentOutcomeSeeder extends Seeder
{
    public function run(): void
    {
        $medical = ['Medical', 'Fire', 'Vehicular', 'Water Rescue', 'Hazmat'];

        $outcomes = [
            // Medical / patient-care.
            [
                'code' => 'TREATED_ON_SCENE',
                'label' => 'Treated on Scene',
                'description' => 'Patient assessed and treated, no transport required.',
                'applicable_categories' => $medical,
                'requires_vitals' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'TRANSPORTED_TO_HOSPITAL',
                'label' => 'Transported to Hospital',
                'description' => 'Patient transported to a receiving facility.',
                'applicable_categories' => $medical,
                'requires_vitals' => true,
                'requires_hospital' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'REFUSED_TREATMENT',
                'label' => 'Refused Treatment',
                'description' => 'Patient declined assessment or transport.',
                'applicable_categories' => $medical,
                'sort_order' => 30,
            ],
            [
                'code' => 'DECLARED_DOA',
                'label' => 'Declared DOA',
                'description' => 'Dead-on-arrival; coroner / PNP notified.',
                'applicable_categories' => $medical,
                'sort_order' => 40,
            ],

            // Crime / Security (PoI).
            [
                'code' => 'SUBJECT_DETAINED',
                'label' => 'Subject Detained / Handed to PNP',
                'description' => 'PoI located and handed off to law enforcement.',
                'applicable_categories' => ['Crime / Security'],
                'sort_order' => 50,
            ],
            [
                'code' => 'SUBJECT_NOT_LOCATED',
                'label' => 'Subject Not Located',
                'description' => 'PoI not found at scene by time of stand-down.',
                'applicable_categories' => ['Crime / Security'],
                'sort_order' => 60,
            ],
            [
                'code' => 'SUBJECT_FLED',
                'label' => 'Subject Fled',
                'description' => 'PoI fled the area before responders arrived.',
                'applicable_categories' => ['Crime / Security'],
                'sort_order' => 70,
            ],
            [
                'code' => 'MISMATCH',
                'label' => 'Mismatch / False Positive',
                'description' => 'FRAS recognition was a false positive.',
                'applicable_categories' => ['Crime / Security'],
                'sort_order' => 80,
            ],

            // Generic non-medical.
            [
                'code' => 'SITUATION_RESOLVED',
                'label' => 'Situation Resolved',
                'description' => 'Incident resolved without transport / detention.',
                'applicable_categories' => ['Public Disturbance', 'Natural Disaster', 'Other'],
                'sort_order' => 90,
            ],
            [
                'code' => 'HANDOFF_TO_AGENCY',
                'label' => 'Hand-off to Other Agency',
                'description' => 'Transferred to BFP, PNP, DRRMO, or other partner.',
                'applicable_categories' => ['Crime / Security', 'Public Disturbance', 'Natural Disaster', 'Other'],
                'sort_order' => 100,
            ],

            // Universal — always last in any category list.
            [
                'code' => 'FALSE_ALARM',
                'label' => 'False Alarm / Stand Down',
                'description' => 'No incident found; responders stand down.',
                'applicable_categories' => null,
                'is_universal' => true,
                'sort_order' => 999,
            ],
        ];

        foreach ($outcomes as $data) {
            IncidentOutcome::query()->updateOrCreate(
                ['code' => $data['code']],
                array_merge(['is_active' => true], $data),
            );
        }
    }
}
