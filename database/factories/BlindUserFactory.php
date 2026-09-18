<?php

namespace Database\Factories;

use App\Models\BlindUser;
use App\Models\Ngo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlindUser>
 */
class BlindUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ngo_id' => Ngo::factory(),
            'created_by' => User::factory()->superAdmin(),
            'name' => fake()->name(),
            'phone' => '+20'.fake()->numerify('1#########'),
            'emergency_notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
