<?php

namespace Database\Factories;

use App\Models\GeneratedReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneratedReport>
 */
class GeneratedReportFactory extends Factory
{
    protected $model = GeneratedReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'dilg_monthly',
            'title' => fake()->sentence(),
            'period' => now()->format('Y-m'),
            'file_path' => 'reports/test.pdf',
            'status' => 'ready',
        ];
    }

    /**
     * Indicate the report is currently generating.
     */
    public function generating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'generating',
        ]);
    }

    /**
     * Indicate the report generation failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    /**
     * Set the report type to quarterly.
     */
    public function quarterly(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'quarterly',
            'period' => 'Q1-'.now()->year,
        ]);
    }

    /**
     * Set the report type to annual.
     */
    public function annual(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'annual',
            'period' => (string) now()->year,
        ]);
    }

    /**
     * Set the report type to NDRRMC SitRep.
     */
    public function ndrrmc(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'ndrrmc_sitrep',
            'period' => 'INC-'.now()->year.'-00001',
        ]);
    }
}
