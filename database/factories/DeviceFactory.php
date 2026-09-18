<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Enums\Language;
use App\Models\BlindUser;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
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
            'serial_number' => 'SAC-'.fake()->unique()->regexify('[A-Z0-9]{10}'),
            'firmware_version' => fake()->randomElement(['1.2.0', '1.3.1', '1.4.0-beta']),
            'battery_level' => fake()->numberBetween(20, 100),
            'preferred_language' => Language::English,
            'status' => DeviceStatus::Active,
            'is_enabled' => true,
            'settings' => [
                'obstacle_thresholds_cm' => [200, 100, 30],
                'location_interval_seconds' => 30,
                'haptic_intensity' => 80,
            ],
            'configuration_version' => 1,
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(0, 3)),
            'last_location_recorded_at' => now()->subMinutes(fake()->numberBetween(0, 3)),
            'geofence_evaluated_at' => now()->subMinutes(fake()->numberBetween(0, 3)),
        ];
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeviceStatus::Offline,
            'last_seen_at' => now()->subHours(2),
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeviceStatus::Maintenance,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
