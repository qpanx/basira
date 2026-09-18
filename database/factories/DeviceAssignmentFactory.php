<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceAssignment>
 */
class DeviceAssignmentFactory extends Factory
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
            'assigned_by' => User::factory()->superAdmin(),
            'assigned_at' => now()->subMonths(fake()->numberBetween(1, 12)),
            'ended_at' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }
}
