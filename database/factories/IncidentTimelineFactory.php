<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentTimeline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentTimeline>
 */
class IncidentTimelineFactory extends Factory
{
    protected $model = IncidentTimeline::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'event_type' => fake()->randomElement(['created', 'dispatched', 'acknowledged', 'en_route', 'on_scene', 'resolved']),
            'event_data' => ['detail' => fake()->sentence()],
            'actor_type' => null,
            'actor_id' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
