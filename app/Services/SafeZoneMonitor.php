<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceSafeZoneState;
use App\Models\Location;
use App\Models\SafeZone;

class SafeZoneMonitor
{
    public function __construct(private HaversineDistance $distance) {}

    /**
     * @return array{entered: array<int, SafeZone>, exited: array<int, SafeZone>}
     */
    public function evaluate(Device $device, Location $location): array
    {
        $transitions = ['entered' => [], 'exited' => []];
        $safeZones = SafeZone::query()
            ->where('blind_user_id', $location->blind_user_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($safeZones as $safeZone) {
            $state = DeviceSafeZoneState::query()->firstOrNew([
                'device_id' => $device->getKey(),
                'safe_zone_id' => $safeZone->getKey(),
            ]);

            if ($state->evaluated_at?->greaterThanOrEqualTo($location->recorded_at)) {
                continue;
            }

            $isInside = $this->distance->meters(
                (float) $location->lat,
                (float) $location->lng,
                (float) $safeZone->center_lat,
                (float) $safeZone->center_lng,
            ) <= $safeZone->radius_meters;

            $previousState = $state->is_inside;
            $state->fill([
                'is_inside' => $isInside,
                'last_location_id' => $location->getKey(),
                'evaluated_at' => $location->recorded_at,
                'entered_at' => $isInside && $previousState === false ? $location->recorded_at : $state->entered_at,
                'exited_at' => ! $isInside && $previousState === true ? $location->recorded_at : $state->exited_at,
            ])->save();

            if ($previousState === null || $previousState === $isInside) {
                continue;
            }

            $transitions[$isInside ? 'entered' : 'exited'][] = $safeZone;
        }

        return $transitions;
    }
}
