<?php

namespace App\Services;

use App\Actions\RecordDeviceLocation;
use App\Enums\NavigationStatus;
use App\Exceptions\AssistiveServiceException;
use App\Models\Device;
use App\Models\Location;
use App\Models\NavigationSession;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NavigationService
{
    public function __construct(
        private NavigationProviders $providers,
        private RecordDeviceLocation $recordLocation,
        private RouteGeometry $geometry,
        private HaversineDistance $distance,
    ) {}

    public function active(Device $device): ?NavigationSession
    {
        return $device->navigationSessions()->where('blind_user_id', $device->blind_user_id)
            ->where('status', NavigationStatus::Active)->latest('id')->first();
    }

    public function start(Device $device, string $placeId, string $language): NavigationSession
    {
        return $this->locked($device, function () use ($device, $placeId, $language): NavigationSession {
            $active = $this->active($device);
            if ($active !== null) {
                if ($active->destination_place_id !== $placeId) {
                    throw new AssistiveServiceException('navigation_already_active', 409);
                }

                return $active;
            }

            $origin = $this->latestLocation($device);
            $provider = $this->providers->selected();
            $destination = $this->providers->destinations($provider)->place($placeId, $language);
            $route = $this->providers->routing($provider)->walkingRoute($origin, $destination, $language);

            return DB::transaction(function () use ($device, $origin, $destination, $route, $language, $provider): NavigationSession {
                $this->checkAssignment($device);
                $active = $this->active($device);
                if ($active !== null) {
                    if ($active->destination_place_id !== $destination['place_id']) {
                        throw new AssistiveServiceException('navigation_already_active', 409);
                    }

                    return $active;
                }
                $device->navigationSessions()->where('status', NavigationStatus::Active)
                    ->update(['status' => NavigationStatus::Cancelled, 'cancelled_at' => now()]);

                return $device->navigationSessions()->create([
                    'routing_provider' => $provider,
                    'blind_user_id' => $device->blind_user_id,
                    'origin_location_id' => $origin->id,
                    'last_location_id' => $origin->id,
                    'destination_place_id' => $destination['place_id'],
                    'destination' => $destination,
                    'language' => $language,
                    'status' => NavigationStatus::Active,
                    'started_at' => now(),
                    'last_reroute_at' => now(),
                    ...$route,
                ])->refresh();
            });
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Device $device, array $data): NavigationSession
    {
        return $this->locked($device, function () use ($device, $data): NavigationSession {
            $session = $this->active($device);
            if ($session === null) {
                throw new AssistiveServiceException('navigation_not_active', 404);
            }

            DB::transaction(function () use ($device, $data): void {
                $this->checkAssignment($device);
                $this->recordLocation->handle($device, $data);
            });
            $location = $this->latestLocation($device);
            $previous = $session->lastLocation;
            if ($location->id === $previous->id || $location->recorded_at->lessThanOrEqualTo($previous->recorded_at)) {
                return $session;
            }

            $session->last_location_id = $location->id;
            $latitude = (float) $location->lat;
            $longitude = (float) $location->lng;
            $threshold = max((float) config('smart-cane.navigation.deviation_meters'), 2 * (float) $location->accuracy);
            $session->off_route = $this->geometry->distanceFromRoute($latitude, $longitude, $session->route_coordinates) > $threshold;

            if ($session->off_route) {
                $canRecalculate = $session->last_reroute_at === null || $session->last_reroute_at->lessThanOrEqualTo(now()->subSeconds(config('smart-cane.navigation.reroute_cooldown_seconds')));
                if ($canRecalculate) {
                    $session->last_reroute_at = now();
                    $this->save($device, $session);
                    $session->fill($this->providers->routing($session->routing_provider)->walkingRoute($location, $session->destination, $session->language));
                    $session->current_step_index = 0;
                    $session->off_route = $this->geometry->distanceFromRoute($latitude, $longitude, $session->route_coordinates) > $threshold;
                }
            } else {
                $steps = $session->steps;
                $index = $session->current_step_index;
                $radius = (float) config('smart-cane.navigation.arrival_radius_meters');
                while (isset($steps[$index]) && $this->distance->meters($latitude, $longitude, $steps[$index]['end_latitude'], $steps[$index]['end_longitude']) <= $radius) {
                    $index++;
                }
                $session->current_step_index = $index;
                if ($index >= count($steps)) {
                    $session->status = NavigationStatus::Completed;
                    $session->completed_at = now();
                }
            }

            return $this->save($device, $session);
        });
    }

    public function stop(Device $device): ?NavigationSession
    {
        return $this->locked($device, function () use ($device): ?NavigationSession {
            $session = $this->active($device);
            if ($session === null) {
                return null;
            }
            $session->status = NavigationStatus::Cancelled;
            $session->cancelled_at = now();

            return $this->save($device, $session);
        });
    }

    private function latestLocation(Device $device): Location
    {
        $location = $device->locations()->where('blind_user_id', $device->blind_user_id)
            ->where('recorded_at', '<=', now())
            ->orderByDesc('recorded_at')->orderByDesc('id')->first();
        if ($location === null || $location->recorded_at->lessThan(now()->subSeconds(config('smart-cane.navigation.location_max_age_seconds')))
            || $location->accuracy === null || (float) $location->accuracy > (float) config('smart-cane.navigation.max_accuracy_meters')) {
            throw new AssistiveServiceException('recent_location_required', 422);
        }

        return $location;
    }

    private function checkAssignment(Device $device): void
    {
        $current = Device::query()->lockForUpdate()->findOrFail($device->id);
        if (! $current->is_enabled || $current->blind_user_id !== $device->blind_user_id) {
            throw new AssistiveServiceException('device_assignment_changed', 409);
        }
    }

    private function save(Device $device, NavigationSession $session): NavigationSession
    {
        return DB::transaction(function () use ($device, $session): NavigationSession {
            $this->checkAssignment($device);
            $session->save();

            return $session;
        });
    }

    /** @param Closure(): (NavigationSession|null) $callback */
    private function locked(Device $device, Closure $callback): ?NavigationSession
    {
        $lock = Cache::lock('device-navigation:'.$device->id, 90);
        if (! $lock->get()) {
            throw new AssistiveServiceException('navigation_busy', 409);
        }
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, mixed> */
    public function response(NavigationSession $session): array
    {
        $steps = array_map(fn (array $step): array => [
            'instruction' => $step['instruction'], 'distance_meters' => $step['distance_meters'],
        ], $session->steps);

        return [
            'session_id' => $session->id,
            'status' => $session->status->value,
            'destination' => $session->destination,
            'language' => $session->language,
            'distance_meters' => $session->distance_meters,
            'estimated_duration_seconds' => $session->estimated_duration_seconds,
            'steps' => $steps,
            'current_step_index' => $session->current_step_index,
            'instruction' => $session->status === NavigationStatus::Active && ! $session->off_route ? ($steps[$session->current_step_index]['instruction'] ?? null) : null,
            'off_route' => $session->off_route,
            'warnings' => $session->warnings,
            'routing_provider' => $session->routing_provider,
            'attribution' => $this->providers->routing($session->routing_provider)->attribution(),
            'safety_notice' => __('assistive.navigation_notice', [], $session->language),
            'started_at' => $session->started_at->toISOString(),
            'completed_at' => $session->completed_at?->toISOString(),
            'cancelled_at' => $session->cancelled_at?->toISOString(),
        ];
    }
}
