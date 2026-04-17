<?php

namespace Database\Factories;

use App\Models\IncidentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentCategory>
 */
class IncidentCategoryFactory extends Factory
{
    protected $model = IncidentCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'icon' => fake()->randomElement(['Heart', 'Flame', 'CloudLightning', 'Car', 'Shield', 'AlertTriangle']),
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
