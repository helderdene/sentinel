<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FrasPlaceholderSeeder extends Seeder
{
    /**
     * Intentionally empty — FRAS tables seed on demand from factories.
     *
     * Satisfies Phase 18 SC4 "every new table has a factory and a seeder"
     * without populating production DBs. Phase 20 admin flows create real
     * camera + personnel rows; Phase 19 RecognitionHandler creates event rows.
     */
    public function run(): void
    {
        // No-op by design (Phase 18 D-62).
    }
}
