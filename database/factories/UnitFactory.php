<?php

namespace Database\Factories;

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\Unit;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(UnitType::cases());
        $prefixes = [
            UnitType::Ambulance->value => 'AMB',
            UnitType::Fire->value => 'FIRE',
            UnitType::Rescue->value => 'RESCUE',
            UnitType::Police->value => 'POLICE',
            UnitType::Boat->value => 'BOAT',
        ];
        $prefix = $prefixes[$type->value];
        $number = str_pad((string) fake()->unique()->numberBetween(1, 99), 2, '0', STR_PAD_LEFT);

        return [
            'id' => "{$prefix}-{$number}",
            'callsign' => "{$prefix} {$number}",
            'type' => $type,
            'agency' => fake()->randomElement(['CDRRMO', 'BFP', 'PNP']),
            'crew_capacity' => fake()->numberBetween(2, 6),
            'status' => UnitStatus::Available,
            'coordinates' => Point::makeGeodetic(
                8.9475 + fake()->randomFloat(4, -0.05, 0.05),
                125.5406 + fake()->randomFloat(4, -0.05, 0.05),
            ),
            'shift' => fake()->randomElement(['day', 'night', null]),
            'notes' => null,
        ];
    }
}
