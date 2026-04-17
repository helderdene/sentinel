<?php

namespace Database\Seeders;

use App\Models\IncidentCategory;
use App\Models\IncidentType;
use Illuminate\Database\Seeder;

class IncidentTypeSeeder extends Seeder
{
    /**
     * Seed the incident types from IRMS specification.
     */
    public function run(): void
    {
        $this->seedCategories();
        $this->seedTypes();
    }

    /**
     * Seed the incident categories with icons.
     */
    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Medical', 'icon' => 'Heart', 'sort_order' => 0],
            ['name' => 'Fire', 'icon' => 'Flame', 'sort_order' => 1],
            ['name' => 'Natural Disaster', 'icon' => 'CloudLightning', 'sort_order' => 2],
            ['name' => 'Vehicular', 'icon' => 'Car', 'sort_order' => 3],
            ['name' => 'Crime / Security', 'icon' => 'Shield', 'sort_order' => 4],
            ['name' => 'Hazmat', 'icon' => 'Biohazard', 'sort_order' => 5],
            ['name' => 'Water Rescue', 'icon' => 'Waves', 'sort_order' => 6],
            ['name' => 'Public Disturbance', 'icon' => 'Megaphone', 'sort_order' => 7],
            ['name' => 'Other', 'icon' => 'HelpCircle', 'sort_order' => 8],
        ];

        foreach ($categories as $category) {
            IncidentCategory::updateOrCreate(
                ['name' => $category['name']],
                [
                    'icon' => $category['icon'],
                    'is_active' => true,
                    'sort_order' => $category['sort_order'],
                ]
            );
        }
    }

    /**
     * Seed the incident types.
     */
    private function seedTypes(): void
    {
        $categoryMap = IncidentCategory::pluck('id', 'name');
        $types = $this->getIncidentTypes();
        $sortOrder = 0;

        foreach ($types as $type) {
            IncidentType::updateOrCreate(
                ['code' => $type['code']],
                [
                    'incident_category_id' => $categoryMap[$type['category']] ?? null,
                    'category' => $type['category'],
                    'name' => $type['name'],
                    'default_priority' => $type['priority'],
                    'description' => $type['description'] ?? null,
                    'is_active' => true,
                    'show_in_public_app' => $type['show_in_public_app'] ?? true,
                    'sort_order' => $sortOrder++,
                ]
            );
        }
    }

    /**
     * Get the incident types organized by category.
     *
     * @return array<int, array{category: string, name: string, code: string, priority: string, description?: string}>
     */
    private function getIncidentTypes(): array
    {
        return [
            ['category' => 'Medical', 'name' => 'Cardiac Arrest', 'code' => 'MED-001', 'priority' => 'P1'],
            ['category' => 'Medical', 'name' => 'Stroke', 'code' => 'MED-002', 'priority' => 'P1'],
            ['category' => 'Medical', 'name' => 'Severe Bleeding', 'code' => 'MED-003', 'priority' => 'P1'],
            ['category' => 'Medical', 'name' => 'Difficulty Breathing', 'code' => 'MED-004', 'priority' => 'P2'],
            ['category' => 'Medical', 'name' => 'Seizure', 'code' => 'MED-005', 'priority' => 'P2'],
            ['category' => 'Medical', 'name' => 'Allergic Reaction', 'code' => 'MED-006', 'priority' => 'P2'],
            ['category' => 'Medical', 'name' => 'Abdominal Pain', 'code' => 'MED-007', 'priority' => 'P3'],
            ['category' => 'Medical', 'name' => 'Minor Injury', 'code' => 'MED-008', 'priority' => 'P3'],
            ['category' => 'Medical', 'name' => 'Fever / Illness', 'code' => 'MED-009', 'priority' => 'P3'],
            ['category' => 'Medical', 'name' => 'Animal Bite', 'code' => 'MED-010', 'priority' => 'P3'],

            ['category' => 'Fire', 'name' => 'Structure Fire', 'code' => 'FIR-001', 'priority' => 'P1'],
            ['category' => 'Fire', 'name' => 'Vehicle Fire', 'code' => 'FIR-002', 'priority' => 'P1'],
            ['category' => 'Fire', 'name' => 'Industrial Fire', 'code' => 'FIR-003', 'priority' => 'P1'],
            ['category' => 'Fire', 'name' => 'Brush / Grass Fire', 'code' => 'FIR-004', 'priority' => 'P2'],
            ['category' => 'Fire', 'name' => 'Electrical Fire', 'code' => 'FIR-005', 'priority' => 'P2'],
            ['category' => 'Fire', 'name' => 'Small Contained Fire', 'code' => 'FIR-006', 'priority' => 'P3'],

            ['category' => 'Natural Disaster', 'name' => 'Earthquake', 'code' => 'NAT-001', 'priority' => 'P1'],
            ['category' => 'Natural Disaster', 'name' => 'Flood', 'code' => 'NAT-002', 'priority' => 'P1'],
            ['category' => 'Natural Disaster', 'name' => 'Landslide', 'code' => 'NAT-003', 'priority' => 'P1'],
            ['category' => 'Natural Disaster', 'name' => 'Typhoon', 'code' => 'NAT-004', 'priority' => 'P1'],
            ['category' => 'Natural Disaster', 'name' => 'Storm Surge', 'code' => 'NAT-005', 'priority' => 'P1'],
            ['category' => 'Natural Disaster', 'name' => 'Tornado', 'code' => 'NAT-006', 'priority' => 'P1'],

            ['category' => 'Vehicular', 'name' => 'Multi-Vehicle Collision', 'code' => 'VEH-001', 'priority' => 'P1'],
            ['category' => 'Vehicular', 'name' => 'Vehicle vs Pedestrian', 'code' => 'VEH-002', 'priority' => 'P1'],
            ['category' => 'Vehicular', 'name' => 'Single Vehicle Accident', 'code' => 'VEH-003', 'priority' => 'P2'],
            ['category' => 'Vehicular', 'name' => 'Motorcycle Accident', 'code' => 'VEH-004', 'priority' => 'P2'],
            ['category' => 'Vehicular', 'name' => 'Minor Fender Bender', 'code' => 'VEH-005', 'priority' => 'P3'],
            ['category' => 'Vehicular', 'name' => 'Vehicle Breakdown on Highway', 'code' => 'VEH-006', 'priority' => 'P4'],

            ['category' => 'Crime / Security', 'name' => 'Active Shooter', 'code' => 'CRM-001', 'priority' => 'P1', 'show_in_public_app' => false],
            ['category' => 'Crime / Security', 'name' => 'Bomb Threat', 'code' => 'CRM-002', 'priority' => 'P1', 'show_in_public_app' => false],
            ['category' => 'Crime / Security', 'name' => 'Assault', 'code' => 'CRM-003', 'priority' => 'P2'],
            ['category' => 'Crime / Security', 'name' => 'Robbery / Hold-up', 'code' => 'CRM-004', 'priority' => 'P2'],
            ['category' => 'Crime / Security', 'name' => 'Domestic Violence', 'code' => 'CRM-005', 'priority' => 'P2'],
            ['category' => 'Crime / Security', 'name' => 'Suspicious Activity', 'code' => 'CRM-006', 'priority' => 'P3'],
            ['category' => 'Crime / Security', 'name' => 'Theft', 'code' => 'CRM-007', 'priority' => 'P3'],

            ['category' => 'Hazmat', 'name' => 'Chemical Spill', 'code' => 'HAZ-001', 'priority' => 'P1'],
            ['category' => 'Hazmat', 'name' => 'Gas Leak', 'code' => 'HAZ-002', 'priority' => 'P1'],
            ['category' => 'Hazmat', 'name' => 'Radioactive Material', 'code' => 'HAZ-003', 'priority' => 'P1', 'show_in_public_app' => false],
            ['category' => 'Hazmat', 'name' => 'Fuel Spill', 'code' => 'HAZ-004', 'priority' => 'P2'],
            ['category' => 'Hazmat', 'name' => 'Minor Hazmat Release', 'code' => 'HAZ-005', 'priority' => 'P3'],

            ['category' => 'Water Rescue', 'name' => 'Drowning', 'code' => 'WTR-001', 'priority' => 'P1'],
            ['category' => 'Water Rescue', 'name' => 'Boat Capsized', 'code' => 'WTR-002', 'priority' => 'P1'],
            ['category' => 'Water Rescue', 'name' => 'Flood Rescue', 'code' => 'WTR-003', 'priority' => 'P1'],
            ['category' => 'Water Rescue', 'name' => 'Swift Water Rescue', 'code' => 'WTR-004', 'priority' => 'P1'],
            ['category' => 'Water Rescue', 'name' => 'Person in Water', 'code' => 'WTR-005', 'priority' => 'P2'],

            ['category' => 'Public Disturbance', 'name' => 'Riot / Civil Unrest', 'code' => 'PUB-001', 'priority' => 'P2'],
            ['category' => 'Public Disturbance', 'name' => 'Large Gathering', 'code' => 'PUB-002', 'priority' => 'P3'],
            ['category' => 'Public Disturbance', 'name' => 'Noise Complaint', 'code' => 'PUB-003', 'priority' => 'P4'],
            ['category' => 'Public Disturbance', 'name' => 'Illegal Dumping', 'code' => 'PUB-004', 'priority' => 'P4'],

            ['category' => 'Other', 'name' => 'Other Emergency', 'code' => 'OTHER_EMERGENCY', 'priority' => 'P3', 'description' => 'Anything not covered by the other categories.'],
        ];
    }
}
