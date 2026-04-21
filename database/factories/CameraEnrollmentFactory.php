<?php

namespace Database\Factories;

use App\Enums\CameraEnrollmentStatus;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CameraEnrollment>
 */
class CameraEnrollmentFactory extends Factory
{
    protected $model = CameraEnrollment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'camera_id' => Camera::factory(),
            'personnel_id' => Personnel::factory(),
            'status' => CameraEnrollmentStatus::Pending,
            'enrolled_at' => null,
            'photo_hash' => null,
            'last_error' => null,
        ];
    }
}
