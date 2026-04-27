<?php

namespace Tests;

use Database\Seeders\IncidentOutcomeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed reference data needed by most feature tests after every
     * RefreshDatabase migration. Currently only the IncidentOutcome
     * catalogue — ResolveIncidentRequest validates against this table,
     * so any test that hits the responder.resolve route needs the rows.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed(IncidentOutcomeSeeder::class);
        }
    }

    protected function skipUnlessFortifyFeature(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
