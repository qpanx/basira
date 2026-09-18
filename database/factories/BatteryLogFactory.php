<?php

namespace Database\Factories;

use App\Enums\PowerState;
use App\Models\BatteryLog;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatteryLog>
 */
class BatteryLogFactory extends Factory
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
            'blind_user_id' => fn (array $attributes): int => Device::query()->findOrFail($attributes['device_id'])->blind_user_id,
            'message_id' => fake()->uuid(),
            'battery_level' => fake()->numberBetween(5, 100),
            'power_state' => PowerState::Discharging,
            'recorded_at' => now()->subMinutes(fake()->numberBetween(0, 1440)),
            'received_at' => now(),
        ];
    }
}
