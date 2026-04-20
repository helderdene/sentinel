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
        $templates = [
            [
                'slug' => 'cardiac',
                'name' => 'Cardiac Emergency',
                'description' => 'Protocol checklist for cardiac arrests and heart-related medical calls.',
                'is_default' => false,
                'is_active' => true,
                'items' => [
                    ['key' => 'scene_secured', 'label' => 'Scene secured'],
                    ['key' => 'patient_responsive', 'label' => 'Patient responsive check'],
                    ['key' => 'abc_assessment', 'label' => 'ABC assessment'],
                    ['key' => 'vital_signs', 'label' => 'Vital signs taken'],
                    ['key' => 'aed_monitor', 'label' => 'AED/monitor attached'],
                    ['key' => 'iv_access', 'label' => 'IV access established'],
                    ['key' => 'medication', 'label' => 'Medication administered'],
                ],
            ],
            [
                'slug' => 'road_accident',
                'name' => 'Road Accident',
                'description' => 'Protocol checklist for vehicular collisions and road incidents.',
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
                ],
            ],
            [
                'slug' => 'structure_fire',
                'name' => 'Structure Fire',
                'description' => 'Protocol checklist for building and structure fires.',
                'is_default' => false,
                'is_active' => true,
                'items' => [
                    ['key' => 'scene_secured', 'label' => 'Scene secured'],
                    ['key' => 'fire_suppression', 'label' => 'Fire suppression confirmed'],
                    ['key' => 'search_completed', 'label' => 'Search completed'],
                    ['key' => 'hazmat_assessment', 'label' => 'Hazmat assessment'],
                    ['key' => 'ventilation_status', 'label' => 'Ventilation status'],
                    ['key' => 'patient_triage', 'label' => 'Patient triage'],
                    ['key' => 'decontamination', 'label' => 'Decontamination (if needed)'],
                ],
            ],
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

        foreach ($templates as $data) {
            ChecklistTemplate::query()->updateOrCreate(
                ['slug' => $data['slug']],
                $data,
            );
        }

        $cardiac = ChecklistTemplate::query()->where('slug', 'cardiac')->value('id');
        $roadAccident = ChecklistTemplate::query()->where('slug', 'road_accident')->value('id');
        $structureFire = ChecklistTemplate::query()->where('slug', 'structure_fire')->value('id');

        IncidentType::query()
            ->whereNull('checklist_template_id')
            ->where('code', 'MED-001')
            ->update(['checklist_template_id' => $cardiac]);

        IncidentType::query()
            ->whereNull('checklist_template_id')
            ->where('category', 'Vehicular')
            ->update(['checklist_template_id' => $roadAccident]);

        IncidentType::query()
            ->whereNull('checklist_template_id')
            ->where('category', 'Fire')
            ->update(['checklist_template_id' => $structureFire]);
    }
}
