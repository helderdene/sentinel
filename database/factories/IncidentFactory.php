<?php

namespace Database\Factories;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentType;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_type_id' => IncidentType::factory(),
            'priority' => fake()->randomElement(IncidentPriority::cases()),
            'status' => IncidentStatus::Pending,
            'channel' => fake()->randomElement(['phone', 'radio', 'walk-in', 'sms', 'iot']),
            'location_text' => fake()->address(),
            'coordinates' => Point::makeGeodetic(
                8.9475 + fake()->randomFloat(4, -0.05, 0.05),
                125.5406 + fake()->randomFloat(4, -0.05, 0.05),
            ),
            'caller_name' => fake()->name(),
            'caller_contact' => fake()->phoneNumber(),
            'raw_message' => fake()->sentence(),
            'notes' => null,
            'vitals' => ['bp' => '120/80', 'hr' => 72, 'spo2' => 98, 'gcs' => 15],
        ];
    }
}
