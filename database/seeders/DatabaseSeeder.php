<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            OperatorUserSeeder::class,
            IncidentTypeSeeder::class,
            PersonOfInterestIncidentTypeSeeder::class,
            ChecklistTemplateSeeder::class,
            IncidentOutcomeSeeder::class,
            UnitSeeder::class,
            BarangaySeeder::class,
            AgencySeeder::class,
            IncidentSeeder::class,
        ]);
    }
}
