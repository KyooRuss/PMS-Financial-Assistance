<?php

namespace Database\Factories;

use App\Models\WaterDistrict;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterDistrict>
 */
class WaterDistrictFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Water District',
            'category' => fake()->randomElement(['A', 'B', 'C', 'D']),
            'address' => fake()->city(),
        ];
    }

    /**
     * A water district of the given category (A–D). Emergency loans need C or D.
     */
    public function category(string $category): static
    {
        return $this->state(['category' => $category]);
    }
}
