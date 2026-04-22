<?php

namespace Database\Seeders;

use App\Models\IncidentCategory;
use App\Models\IncidentType;
use Illuminate\Database\Seeder;

final class PersonOfInterestIncidentTypeSeeder extends Seeder
{
    /**
     * Seed the Person of Interest IncidentType (D-02).
     *
     * Idempotent via updateOrCreate keyed on `code = 'person_of_interest'`.
     * Category resolved dynamically from existing IncidentCategory rows so
     * tests running this after IncidentTypeSeeder see the full chain.
     */
    public function run(): void
    {
        $categoryId = IncidentCategory::query()
            ->where('name', 'Crime / Security')
            ->value('id');

        IncidentType::updateOrCreate(
            ['code' => 'person_of_interest'],
            [
                'incident_category_id' => $categoryId,
                'category' => 'Crime / Security',
                'name' => 'Person of Interest',
                'default_priority' => 'P2',
                'is_active' => true,
                'show_in_public_app' => false,
                'sort_order' => (int) (IncidentType::max('sort_order') ?? 0) + 1,
            ]
        );
    }
}
