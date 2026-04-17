<?php

namespace Database\Factories;

use App\Models\IncidentCategory;
use App\Models\IncidentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentType>
 */
class IncidentTypeFactory extends Factory
{
    protected $model = IncidentType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_category_id' => IncidentCategory::factory(),
            'category' => fake()->randomElement(['Medical', 'Fire', 'Natural Disaster', 'Vehicular']),
            'name' => fake()->unique()->words(3, true),
            'code' => strtoupper(fake()->unique()->lexify('???-###')),
            'default_priority' => fake()->randomElement(['P1', 'P2', 'P3', 'P4']),
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
