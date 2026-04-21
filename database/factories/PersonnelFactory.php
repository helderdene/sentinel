<?php

namespace Database\Factories;

use App\Enums\PersonnelCategory;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Personnel>
 */
class PersonnelFactory extends Factory
{
    protected $model = Personnel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'custom_id' => null,
            'name' => fake()->name(),
            'gender' => fake()->randomElement([0, 1, null]),
            'birthday' => fake()->date(),
            'id_card' => fake()->numerify('IDC-########'),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'photo_path' => null,
            'photo_hash' => null,
            'category' => PersonnelCategory::Allow,
        ];
    }
}
