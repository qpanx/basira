<?php

namespace Database\Factories;

use App\Enums\NavigationStatus;
use App\Models\Device;
use App\Models\Location;
use App\Models\NavigationSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NavigationSession> */
class NavigationSessionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'blind_user_id' => fn (array $attributes): int => Device::query()->whereKey($attributes['device_id'])->firstOrFail()->blind_user_id,
            'origin_location_id' => fn (array $attributes): int => Location::factory()->create([
                'device_id' => $attributes['device_id'], 'blind_user_id' => $attributes['blind_user_id'],
                'lat' => 30, 'lng' => 31, 'accuracy' => 5, 'recorded_at' => now(),
            ])->id,
            'last_location_id' => fn (array $attributes): int => $attributes['origin_location_id'],
            'destination_place_id' => 'test-destination',
            'destination' => ['place_id' => 'test-destination', 'name' => 'Synthetic test destination', 'address' => 'Test fixture', 'latitude' => 30.001, 'longitude' => 31.0],
            'language' => 'en', 'status' => NavigationStatus::Active,
            'distance_meters' => 111, 'estimated_duration_seconds' => 90,
            'steps' => [['instruction' => 'Walk straight', 'distance_meters' => 111, 'end_latitude' => 30.001, 'end_longitude' => 31.0]],
            'route_coordinates' => [[31.0, 30.0], [31.0, 30.001]],
            'warnings' => [], 'current_step_index' => 0, 'off_route' => false,
            'started_at' => now(), 'last_reroute_at' => now(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => NavigationStatus::Cancelled, 'cancelled_at' => now()]);
    }
}
