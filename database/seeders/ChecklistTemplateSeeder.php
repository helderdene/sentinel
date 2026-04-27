<?php

namespace Database\Seeders;

use App\Models\ChecklistTemplate;
use App\Models\IncidentType;
use Illuminate\Database\Seeder;

class ChecklistTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->templates() as $data) {
            ChecklistTemplate::query()->updateOrCreate(
                ['slug' => $data['slug']],
                $data,
            );
        }

        $this->assignTypeChecklists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            // ── Medical ────────────────────────────────────────────────
            [
                'slug' => 'cardiac',
                'name' => 'Cardiac Emergency',
                'description' => 'Cardiac arrest, MI, and other heart-related calls.',
                'is_default' => false,
                'is_active' => true,
                'items' => [
                    ['key' => 'scene_secured', 'label' => 'Scene secured'],
                    ['key' => 'patient_responsive', 'label' => 'Patient responsiveness checked'],
                    ['key' => 'abc_assessment', 'label' => 'ABC assessment'],
                    ['key' => 'cpr_started', 'label' => 'CPR started (if pulseless)'],
                    ['key' => 'aed_monitor', 'label' => 'AED / cardiac monitor attached'],
                    ['key' => 'iv_access', 'label' => 'IV access established'],
                    ['key' => 'medication', 'label' => 'Medication administered'],
                    ['key' => 'rosc_or_transport', 'label' => 'ROSC achieved or transport initiated'],
                ],
            ],
            [
                'slug' => 'stroke',
                'name' => 'Stroke (FAST)',
                'description' => 'Acute stroke / CVA — FAST assessment and rapid transport.',
                'is_default' => false,
                'is_active' => true,
                'items' => [
                    ['key' => 'scene_secured', 'label' => 'Scene secured'],
                    ['key' => 'fast_face', 'label' => 'FAST: facial droop assessed'],
                    ['key' => 'fast_arm', 'label' => 'FAST: arm weakness assessed'],
                    ['key' => 'fast_speech', 'label' => 'FAST: speech difficulty assessed'],
                    ['key' => 'last_known_well', 'label' => 'Last-known-well time recorded'],
                    ['key' => 'glucose_check', 'label' => 'Blood glucose checked'],
                    ['key' => 'vital_signs', 'label' => 'Vital signs taken'],
                    ['key' => 'stroke_alert', 'label' => 'Stroke alert called to receiving facility'],
                ],
            ],
            [
                'slug' => 'medical_general',
                'name' => 'Medical (General)',
                'description' => 'General medical call protocol — non-cardiac, non-stroke.',
                'is_default' => false,
                'is_active' => true,
                'items' => [
                    ['key' => 'scene_secured', 'label' => 'Scene secured'],
                    ['key' => 'bsi_ppe', 'label' => 'BSI / PPE applied'],
                    ['key' => 'chief_complaint', 'label' => 'Chief complaint documented'],
                    ['key' => 'sample_history', 'label' => 'SAMPLE history obtained'],
                    ['key' => 'vital_signs', 'label' => 'Vital signs taken'],
                    ['key' => 'focused_exam', 'label' => 'Focused physical exam'],
                    ['key' => 'treatment_provided', 'label' => 'Treatment provided'],
                    ['key' => 'reassessment', 'label' => 'Reassessment completed'],
                ],
            ],

            // ── Fire ───────────────────────────────────────────────────
            [
                'slug' => 'structure_fire',
                'name' => 'Structure Fire',
                'description' => 'Building and structure fires.',
                'is_default' => false,
                'is_active' => true,
                'items' => [
                    ['key' => 'scene_secured', 'label' => 'Scene secured / cordon set'],
                    ['key' => 'water_supply', 'label' => 'Water supply established'],
                    ['key' => 'primary_search', 'label' => 'Primary search complete'],
                    ['key' => 'fire_suppression', 'label' => 'Fire suppression confirmed'],
                    ['key' => 'ventilation_status', 'label' => 'Ventilation status'],
                    ['key' => 'utilities_secured', 'label' => 'Utilities (gas/electric) secured'],
                    ['key' => 'overhaul', 'label' => 'Overhaul / hot-spot check'],
                    ['key' => 'patient_triage', 'label' => 'Civilian patient triage'],
                ],
            ],

            // ── Natural Disaster ───────────────────────────────────────
            [
                'slug' => 'natural_disaster',
                'name' => 'Natural Disaster',
                'description' => 'Earthquake, flood, landslide, typhoon, storm surge.',
                'is_default' => false,
                'is_active' => true,
                'items' => [
                    ['key' => 'scene_secured', 'label' => 'Scene assessed for safety'],
                    ['key' => 'evacuation_started', 'label' => 'Evacuation initiated'],
                    ['key' => 'evac_center_coord', 'label' => 'Evacuation center coordination'],
                    ['key' => 'casualty_count', 'label' => 'Initial casualty count'],
                    ['key' => 'access_routes', 'label' => 'Access routes assessed'],
                    ['key' => 'utility_status', 'label' => 'Utility status reported'],
                    ['key' => 'sitrep_sent', 'label' => 'SITREP sent to Operations'],
                    ['key' => 'agency_handoff', 'label' => 'Hand-off to BFP / DRRMO if needed'],
                ],
            ],

            // ── Vehicular ─────────────────────────────────────────────
            [
                'slug' => 'road_accident',
                'name' => 'Road Accident',
                'description' => 'Vehicular collisions and road incidents.',
                'is_default' => false,
                'is_active' => true,
                'items' => [
                    ['key' => 'scene_secured', 'label' => 'Scene secured'],
                    ['key' => 'traffic_control', 'label' => 'Traffic control established'],
                    ['key' => 'vehicle_stability', 'label' => 'Vehicle stability assessed'],
                    ['key' => 'extrication', 'label' => 'Patient extrication (if needed)'],
                    ['key' => 'spinal_immobilization', 'label' => 'Spinal immobilization applied'],
                    ['key' => 'bleeding_control', 'label' => 'Bleeding controlled'],
                    ['key' => 'patient_assessed', 'label' => 'Patient assessed'],
                    ['key' => 'tow_requested', 'label' => 'Tow / removal requested'],
                ],
            ],

            // ── Crime / Security ───────────────────────────────────────
            [
                'slug' => 'crime_security',
                'name' => 'Crime / Security',
                'description' => 'Assault, robbery, suspicious activity. Stage and wait for PNP clearance before approach.',
                'is_default' => false,
                'is_active' => true,
                'items' => [
                    ['key' => 'staged_safe_distance', 'label' => 'Staged at safe distance'],
                    ['key' => 'pnp_clearance', 'label' => 'PNP scene clearance confirmed'],
                    ['key' => 'scene_secured', 'label' => 'Scene secured'],
                    ['key' => 'evidence_preserved', 'label' => 'Evidence preserved'],
                    ['key' => 'witnesses_identified', 'label' => 'Witnesses identified'],
                    ['key' => 'patient_assessed', 'label' => 'Patient assessed'],
                    ['key' => 'chain_of_custody', 'label' => 'Chain of custody documented'],
                ],
            ],
            [
                'slug' => 'person_of_interest',
                'name' => 'Person of Interest (FRAS)',
                'description' => 'FRAS recognition match — confirm identity, secure subject, hand off to PNP.',
                'is_default' => false,
                'is_active' => true,
                'items' => [
                    ['key' => 'pnp_notified', 'label' => 'PNP notified of FRAS match'],
                    ['key' => 'subject_located', 'label' => 'Subject located'],
                    ['key' => 'identity_confirmed', 'label' => 'Identity visually confirmed against capture'],
                    ['key' => 'perimeter_set', 'label' => 'Perimeter / containment set'],
                    ['key' => 'subject_detained', 'label' => 'Subject detained safely'],
                    ['key' => 'bystanders_cleared', 'label' => 'Bystanders cleared from area'],
                    ['key' => 'pnp_handoff', 'label' => 'Hand-off to PNP completed'],
                    ['key' => 'incident_logged', 'label' => 'Incident logged with photos / timestamps'],
                ],
            ],

            // ── Hazmat ────────────────────────────────────────────────
            [
                'slug' => 'hazmat',
                'name' => 'Hazmat',
                'description' => 'Chemical spill, gas leak, fuel release. Approach upwind only.',
                'is_default' => false,
                'is_active' => true,
                'items' => [
                    ['key' => 'upwind_approach', 'label' => 'Approached from upwind'],
                    ['key' => 'evacuation_zone', 'label' => 'Evacuation zone established'],
                    ['key' => 'product_identified', 'label' => 'Hazmat product identified'],
                    ['key' => 'ppe_donned', 'label' => 'Appropriate PPE donned'],
                    ['key' => 'leak_contained', 'label' => 'Leak / release contained'],
                    ['key' => 'decontamination', 'label' => 'Decontamination set up'],
                    ['key' => 'patient_decon', 'label' => 'Patient decontaminated (if exposed)'],
                    ['key' => 'specialist_called', 'label' => 'Hazmat specialist called'],
                ],
            ],

            // ── Water Rescue ──────────────────────────────────────────
            [
                'slug' => 'water_rescue',
                'name' => 'Water Rescue',
                'description' => 'Drowning, swift-water, flood rescue, capsized boat.',
                'is_default' => false,
                'is_active' => true,
                'items' => [
                    ['key' => 'pfd_donned', 'label' => 'PFDs donned by all responders'],
                    ['key' => 'water_assessment', 'label' => 'Water hazards assessed (current, depth, debris)'],
                    ['key' => 'rescue_method', 'label' => 'Rescue method selected (reach / throw / row / go)'],
                    ['key' => 'subject_reached', 'label' => 'Subject reached'],
                    ['key' => 'subject_extracted', 'label' => 'Subject extracted from water'],
                    ['key' => 'cpr_started', 'label' => 'CPR started (if pulseless)'],
                    ['key' => 'hypothermia_check', 'label' => 'Hypothermia assessment'],
                    ['key' => 'patient_transported', 'label' => 'Patient transported'],
                ],
            ],

            // ── Public Disturbance ────────────────────────────────────
            [
                'slug' => 'public_disturbance',
                'name' => 'Public Disturbance',
                'description' => 'Crowd control, large gatherings, civil disturbances.',
                'is_default' => false,
                'is_active' => true,
                'items' => [
                    ['key' => 'staged_safe_distance', 'label' => 'Staged at safe distance'],
                    ['key' => 'pnp_coordination', 'label' => 'PNP coordination established'],
                    ['key' => 'crowd_size_estimate', 'label' => 'Crowd size estimated'],
                    ['key' => 'access_routes', 'label' => 'Access / egress routes identified'],
                    ['key' => 'de_escalation', 'label' => 'De-escalation attempted'],
                    ['key' => 'medical_standby', 'label' => 'Medical standby in place'],
                    ['key' => 'sitrep_sent', 'label' => 'SITREP sent to Operations'],
                ],
            ],

            // ── Default fallback ──────────────────────────────────────
            [
                'slug' => 'default',
                'name' => 'General',
                'description' => 'Fallback checklist used when no specific template is assigned.',
                'is_default' => true,
                'is_active' => true,
                'items' => [
                    ['key' => 'scene_secured', 'label' => 'Scene secured'],
                    ['key' => 'area_assessment', 'label' => 'Area assessment complete'],
                    ['key' => 'hazards_identified', 'label' => 'Hazards identified'],
                    ['key' => 'patient_contacted', 'label' => 'Patient contacted'],
                    ['key' => 'initial_assessment', 'label' => 'Initial assessment'],
                    ['key' => 'treatment_provided', 'label' => 'Treatment provided'],
                    ['key' => 'documentation_complete', 'label' => 'Documentation complete'],
                ],
            ],
        ];
    }

    /**
     * Map incident types to checklist templates. Idempotent — only fills
     * incident types that don't already have a template assigned, so manual
     * overrides via the admin UI are preserved.
     */
    private function assignTypeChecklists(): void
    {
        $byCategory = ChecklistTemplate::query()
            ->whereIn('slug', [
                'medical_general',
                'natural_disaster',
                'road_accident',
                'crime_security',
                'hazmat',
                'water_rescue',
                'public_disturbance',
                'default',
                'structure_fire',
            ])
            ->pluck('id', 'slug');

        // Per-code overrides (more specific than category-level).
        $byCode = [
            'MED-001' => ChecklistTemplate::query()->where('slug', 'cardiac')->value('id'),
            'MED-002' => ChecklistTemplate::query()->where('slug', 'stroke')->value('id'),
            'person_of_interest' => ChecklistTemplate::query()
                ->where('slug', 'person_of_interest')
                ->value('id'),
        ];

        foreach ($byCode as $code => $templateId) {
            if (! $templateId) {
                continue;
            }
            IncidentType::query()
                ->whereNull('checklist_template_id')
                ->where('code', $code)
                ->update(['checklist_template_id' => $templateId]);
        }

        $categoryMap = [
            'Medical' => 'medical_general',
            'Fire' => 'structure_fire',
            'Natural Disaster' => 'natural_disaster',
            'Vehicular' => 'road_accident',
            'Crime / Security' => 'crime_security',
            'Hazmat' => 'hazmat',
            'Water Rescue' => 'water_rescue',
            'Public Disturbance' => 'public_disturbance',
            'Other' => 'default',
        ];

        foreach ($categoryMap as $category => $slug) {
            $templateId = $byCategory[$slug] ?? null;
            if (! $templateId) {
                continue;
            }
            IncidentType::query()
                ->whereNull('checklist_template_id')
                ->where('category', $category)
                ->update(['checklist_template_id' => $templateId]);
        }
    }
}
