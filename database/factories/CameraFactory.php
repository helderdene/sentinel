<?php

namespace Database\Factories;

use App\Enums\CameraStatus;
use App\Models\Camera;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Camera>
 */
class CameraFactory extends Factory
{
    protected $model = Camera::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => fake()->uuid(),
            'camera_id_display' => null, // Phase 20 sequences CAM-XX per D-05
            'name' => fake()->company().' Camera',
            'location_label' => fake()->streetAddress(),
            'location' => Point::makeGeodetic(
                8.9475 + fake()->randomFloat(4, -0.05, 0.05),
                125.5406 + fake()->randomFloat(4, -0.05, 0.05),
            ),
            'status' => CameraStatus::Offline,
        ];
    }
}
