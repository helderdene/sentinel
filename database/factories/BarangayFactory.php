<?php

namespace Database\Factories;

use App\Models\Barangay;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barangay>
 */
class BarangayFactory extends Factory
{
    protected $model = Barangay::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $centerLng = 125.5406 + fake()->randomFloat(4, -0.05, 0.05);
        $centerLat = 8.9475 + fake()->randomFloat(4, -0.05, 0.05);
        $offset = 0.005;

        $ring = LineString::make([
            Point::makeGeodetic($centerLat - $offset, $centerLng - $offset),
            Point::makeGeodetic($centerLat - $offset, $centerLng + $offset),
            Point::makeGeodetic($centerLat + $offset, $centerLng + $offset),
            Point::makeGeodetic($centerLat + $offset, $centerLng - $offset),
            Point::makeGeodetic($centerLat - $offset, $centerLng - $offset),
        ]);

        return [
            'name' => fake()->unique()->word().' Brgy',
            'district' => fake()->randomElement(['District 1', 'District 2', 'District 3', null]),
            'city' => 'Butuan City',
            'boundary' => Polygon::make([$ring], 4326),
            'population' => fake()->numberBetween(1000, 20000),
            'risk_level' => fake()->randomElement(['low', 'moderate', 'high']),
        ];
    }
}
