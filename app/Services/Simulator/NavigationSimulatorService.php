<?php

namespace App\Services\Simulator;

use App\Models\Location;
use App\Services\HaversineDistance;
use App\Services\NavigationProviders;
use App\Services\RouteGeometry;

/**
 * In-memory navigation simulation for the dashboard.
 *
 * Route computation reuses the existing OSRM walking provider, but sessions
 * live only in component state: no Location rows, NavigationSession rows, or
 * device telemetry are ever written, so production sessions cannot be touched.
 */
class NavigationSimulatorService
{
    public function __construct(
        private NavigationProviders $providers,
        private HaversineDistance $distance,
        private RouteGeometry $geometry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function start(float $originLatitude, float $originLongitude, float $destinationLatitude, float $destinationLongitude, string $language): array
    {
        $destination = $this->providers->destinations('osrm')->place(
            $this->placeId($destinationLatitude, $destinationLongitude),
            $language,
        );
        $origin = new Location(['lat' => $originLatitude, 'lng' => $originLongitude]);
        $route = $this->providers->routing('osrm')->walkingRoute($origin, $destination, $language);

        $steps = $route['steps'];

        return [
            'simulated' => true,
            'status' => 'active',
            'routing_provider' => 'osrm',
            'attribution' => $this->providers->routing('osrm')->attribution(),
            'language' => $language,
            'destination' => $destination,
            'steps' => $steps,
            'route_coordinates' => $route['route_coordinates'],
            'warnings' => $route['warnings'],
            'distance_meters' => $route['distance_meters'],
            'estimated_duration_seconds' => $route['estimated_duration_seconds'],
            'current_step_index' => 0,
            'current_instruction' => $steps[0]['instruction'] ?? null,
            'last_spoken_instruction' => null,
            'spoken_count' => 0,
            'off_route' => false,
            'current_latitude' => $originLatitude,
            'current_longitude' => $originLongitude,
            'device_id' => null,
            'device_label' => null,
            'production_session_active' => false,
        ];
    }

    /**
     * Advance the simulation with a simulated GPS fix. Purely in-memory.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function updateGps(array $state, float $latitude, float $longitude): array
    {
        $state['current_latitude'] = $latitude;
        $state['current_longitude'] = $longitude;

        if ($state['status'] !== 'active') {
            return $state;
        }

        $threshold = (float) config('smart-cane.navigation.deviation_meters');
        $state['off_route'] = $this->geometry->distanceFromRoute($latitude, $longitude, $state['route_coordinates']) > $threshold;
        if ($state['off_route']) {
            $state['current_instruction'] = null;

            return $state;
        }

        $steps = $state['steps'];
        $index = (int) $state['current_step_index'];
        $radius = (float) config('smart-cane.navigation.arrival_radius_meters');
        while (isset($steps[$index]) && $this->distance->meters($latitude, $longitude, $steps[$index]['end_latitude'], $steps[$index]['end_longitude']) <= $radius) {
            $index++;
        }
        $state['current_step_index'] = $index;

        if ($index >= count($steps)) {
            $state['status'] = 'completed';
            $state['current_instruction'] = null;
        } else {
            $state['current_instruction'] = $steps[$index]['instruction'];
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function stop(array $state): array
    {
        $state['status'] = 'cancelled';
        $state['current_instruction'] = null;
        $state['off_route'] = false;

        return $state;
    }

    private function placeId(float $latitude, float $longitude): string
    {
        return sprintf(
            'coordinates_%d_%d',
            (int) round($latitude * 10_000_000),
            (int) round($longitude * 10_000_000),
        );
    }
}
