<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceSafeZoneState;
use App\Models\Location;
use App\Models\SafeZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceSafeZoneState>
 */
class DeviceSafeZoneStateFactory extends Factory
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
            'safe_zone_id' => SafeZone::factory(),
            'last_location_id' => Location::factory(),
            'is_inside' => fake()->boolean(),
            'evaluated_at' => now(),
            'entered_at' => fake()->optional()->dateTimeBetween('-1 month'),
            'exited_at' => fake()->optional()->dateTimeBetween('-1 month'),
        ];
    }
}
