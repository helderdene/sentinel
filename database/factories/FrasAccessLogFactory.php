<?php

namespace Database\Factories;

use App\Enums\FrasAccessAction;
use App\Enums\FrasAccessSubject;
use App\Models\FrasAccessLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FrasAccessLog>
 */
class FrasAccessLogFactory extends Factory
{
    protected $model = FrasAccessLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'subject_type' => FrasAccessSubject::RecognitionEventFace->value,
            'subject_id' => (string) Str::uuid(),
            'action' => FrasAccessAction::View->value,
            'accessed_at' => now(),
        ];
    }
}
