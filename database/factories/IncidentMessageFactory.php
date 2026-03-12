<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentMessage>
 */
class IncidentMessageFactory extends Factory
{
    protected $model = IncidentMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'sender_type' => User::class,
            'sender_id' => User::factory(),
            'body' => fake()->paragraph(),
            'message_type' => 'text',
            'is_quick_reply' => false,
            'read_at' => null,
        ];
    }
}
