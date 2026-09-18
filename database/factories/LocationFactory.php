<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'blind_user_id' => fn (array $attributes): int => Device::query()
                ->findOrFail($attributes['device_id'])
                ->blind_user_id,
            'message_id' => fake()->uuid(),
            'lat' => fake()->randomFloat(7, 29.95, 30.15),
            'lng' => fake()->randomFloat(7, 31.15, 31.45),
            'accuracy' => fake()->randomFloat(2, 2, 25),
            'recorded_at' => now()->subMinutes(fake()->numberBetween(0, 1440)),
            'received_at' => now(),
        ];
    }
}
