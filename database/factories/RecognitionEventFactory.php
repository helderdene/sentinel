<?php

namespace Database\Factories;

use App\Enums\RecognitionSeverity;
use App\Models\Camera;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecognitionEvent>
 */
class RecognitionEventFactory extends Factory
{
    protected $model = RecognitionEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $capturedAt = now()->subSeconds(1);
        $recordId = fake()->unique()->numberBetween(1, 2_000_000_000);

        return [
            'camera_id' => Camera::factory(),
            'personnel_id' => null,
            'incident_id' => null,
            'record_id' => $recordId,
            'custom_id' => null,
            'camera_person_id' => null,
            'verify_status' => 1,
            'person_type' => 0,
            'similarity' => fake()->randomFloat(2, 60, 99),
            'is_real_time' => true,
            'name_from_camera' => null,
            'facesluice_id' => null,
            'id_card' => null,
            'phone' => null,
            'is_no_mask' => 0,
            'target_bbox' => [120, 80, 340, 360],
            'captured_at' => $capturedAt,
            'received_at' => now(),
            'face_image_path' => null,
            'scene_image_path' => null,
            'raw_payload' => [
                'recordId' => $recordId,
                'cameraDeviceId' => fake()->uuid(),
                'personName' => fake()->name(),
                'persionName' => fake()->name(),
                'similarity' => 85.2,
                'verifyStatus' => 1,
                'personType' => 0,
                'isRealTime' => true,
            ],
            'severity' => RecognitionSeverity::Info,
            'acknowledged_by' => null,
            'acknowledged_at' => null,
            'dismissed_at' => null,
        ];
    }

    /**
     * State: Critical severity (blocklist / high-risk match).
     */
    public function critical(): static
    {
        return $this->state(fn () => ['severity' => RecognitionSeverity::Critical]);
    }

    /**
     * State: Warning severity (possible match, needs review).
     */
    public function warning(): static
    {
        return $this->state(fn () => ['severity' => RecognitionSeverity::Warning]);
    }

    /**
     * State: Info severity (routine observation).
     */
    public function info(): static
    {
        return $this->state(fn () => ['severity' => RecognitionSeverity::Info]);
    }

    /**
     * State: Link this event to a specific Personnel.
     */
    public function withPersonnel(Personnel $personnel): static
    {
        return $this->state(fn () => ['personnel_id' => $personnel->id]);
    }

    /**
     * State: Blocklist match (person_type = 1 + critical severity).
     */
    public function blockMatch(): static
    {
        return $this->state(fn () => [
            'person_type' => 1,
            'severity' => RecognitionSeverity::Critical,
        ]);
    }
}
