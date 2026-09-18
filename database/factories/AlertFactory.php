<?php

namespace Database\Factories;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
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
            'resolved_by' => null,
            'message_id' => fake()->uuid(),
            'type' => fake()->randomElement(AlertType::deviceReportable()),
            'severity' => fake()->randomElement(AlertSeverity::cases()),
            'distance_cm' => fake()->optional(0.7)->numberBetween(10, 250),
            'lat' => fake()->randomFloat(7, 29.95, 30.15),
            'lng' => fake()->randomFloat(7, 31.15, 31.45),
            'occurred_at' => now()->subMinutes(fake()->numberBetween(0, 1440)),
            'resolution' => null,
            'resolution_notes' => null,
            'resolved_at' => null,
        ];
    }

    public function sos(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AlertType::Sos,
            'severity' => AlertSeverity::Critical,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'resolution' => 'confirmed',
            'resolved_at' => now(),
        ]);
    }

    public function falsePositive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'resolution' => 'false_positive',
            'resolved_at' => now(),
        ]);
    }
}
