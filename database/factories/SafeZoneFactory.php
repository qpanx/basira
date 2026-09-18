<?php

namespace Database\Factories;

use App\Models\BlindUser;
use App\Models\SafeZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SafeZone>
 */
class SafeZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blind_user_id' => BlindUser::factory(),
            'name' => fake()->randomElement(['Home', 'Work', 'Rehabilitation Center', 'Family Home']),
            'center_lat' => fake()->randomFloat(7, 29.95, 30.15),
            'center_lng' => fake()->randomFloat(7, 31.15, 31.45),
            'radius_meters' => fake()->randomElement([100, 150, 250, 500]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
