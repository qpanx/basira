<?php

namespace App\Services;

use App\Contracts\RoutingProvider;
use App\Exceptions\AssistiveServiceException;
use App\Models\Location;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OsrmRoutingProvider implements RoutingProvider
{
    public function walkingRoute(Location $origin, array $destination, string $language): array
    {
        $url = config('services.osrm.url');
        if (! is_string($url) || $url === '' || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new AssistiveServiceException('provider_not_configured');
        }
        $profile = config('services.osrm.profile', 'foot');
        if (config('services.osrm.walking_enabled') !== true
            || ! is_string($profile) || ! preg_match('/^[A-Za-z0-9_-]+$/D', $profile)) {
            throw new AssistiveServiceException('walking_routing_unavailable');
        }

        $coordinates = sprintf('%.7F,%.7F;%.7F,%.7F', $origin->lng, $origin->lat, $destination['longitude'], $destination['latitude']);
        $snapRadius = max(1, min(1000, (int) config('services.osrm.max_snap_distance_meters', 50)));
        try {
            $response = Http::acceptJson()->connectTimeout(3)
                ->timeout(max(1, min(20, (int) config('services.osrm.timeout', 10))))
                ->withoutRedirecting()
                ->get(rtrim($url, '/').'/route/v1/'.$profile.'/'.$coordinates, [
                    'steps' => 'true', 'geometries' => 'geojson', 'overview' => 'full',
                    'alternatives' => 'false', 'radiuses' => "{$snapRadius};{$snapRadius}",
                ]);
        } catch (ConnectionException) {
            Log::warning('OSRM connection failed.');
            throw new AssistiveServiceException('provider_unavailable');
        }

        $data = $response->json();
        if (in_array($response->status(), [200, 400], true)
            && in_array(data_get($data, 'code'), ['NoRoute', 'NoSegment'], true)) {
            throw new AssistiveServiceException('route_not_found', 422);
        }
        if (! $response->successful()) {
            Log::warning('OSRM request failed.', ['status' => $response->status()]);
            throw new AssistiveServiceException('provider_unavailable');
        }
        if (! is_array($data) || ($data['code'] ?? null) !== 'Ok') {
            throw new AssistiveServiceException('invalid_provider_response', 502);
        }
        $route = data_get($data, 'routes.0');
        if (! is_array($route) || Validator::make($route, [
            'distance' => ['required', 'numeric', 'between:0,4294967295'],
            'duration' => ['required', 'numeric', 'between:0,4294967295'],
            'geometry.type' => ['required', 'in:LineString'],
            'geometry.coordinates' => ['required', 'array', 'min:2', 'max:50000'],
            'geometry.coordinates.*' => ['required', 'array', 'size:2'],
            'geometry.coordinates.*.0' => ['required', 'numeric', 'between:-180,180'],
            'geometry.coordinates.*.1' => ['required', 'numeric', 'between:-90,90'],
            'legs' => ['required', 'array', 'size:1'],
            'legs.*.steps' => ['required', 'array', 'min:1', 'max:2000'],
            'legs.*.steps.*.distance' => ['required', 'numeric', 'between:0,4294967295'],
            'legs.*.steps.*.mode' => ['required', 'string'],
            'legs.*.steps.*.name' => ['sometimes', 'string', 'max:1000'],
            'legs.*.steps.*.maneuver.type' => ['required', 'string', 'max:100'],
            'legs.*.steps.*.maneuver.modifier' => ['sometimes', 'in:uturn,sharp right,right,slight right,straight,slight left,left,sharp left'],
            'legs.*.steps.*.maneuver.exit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'legs.*.steps.*.geometry.type' => ['required', 'in:LineString'],
            'legs.*.steps.*.geometry.coordinates' => ['required', 'array', 'min:1', 'max:50000'],
            'legs.*.steps.*.geometry.coordinates.*' => ['required', 'array', 'size:2'],
            'legs.*.steps.*.geometry.coordinates.*.0' => ['required', 'numeric', 'between:-180,180'],
            'legs.*.steps.*.geometry.coordinates.*.1' => ['required', 'numeric', 'between:-90,90'],
        ])->fails()) {
            throw new AssistiveServiceException('invalid_provider_response', 502);
        }
        if (! array_is_list($route['legs']) || ! array_is_list($route['legs'][0]['steps'])
            || ! array_is_list($route['geometry']['coordinates'])) {
            throw new AssistiveServiceException('invalid_provider_response', 502);
        }

        $steps = [];
        foreach ($route['legs'][0]['steps'] as $step) {
            // The URL profile name cannot prove which Lua profile prepared the server's data.
            if ($step['mode'] !== 'walking') {
                throw new AssistiveServiceException('walking_routing_unavailable');
            }
            $geometry = $step['geometry']['coordinates'];
            if ($geometry === [] || ! array_is_list($geometry)) {
                throw new AssistiveServiceException('invalid_provider_response', 502);
            }
            $end = $geometry[array_key_last($geometry)];
            $steps[] = [
                'instruction' => $this->instruction($step, $language),
                'distance_meters' => (int) round($step['distance']),
                'end_latitude' => (float) $end[1],
                'end_longitude' => (float) $end[0],
            ];
        }

        return [
            'distance_meters' => (int) round($route['distance']),
            'estimated_duration_seconds' => (int) ceil($route['duration']),
            'route_coordinates' => $route['geometry']['coordinates'],
            'steps' => $steps,
            'warnings' => [__('assistive.osrm.warning', [], $language)],
        ];
    }

    /** @param array<string, mixed> $step */
    private function instruction(array $step, string $language): string
    {
        $maneuver = $step['maneuver'];
        $type = $maneuver['type'];
        $modifier = $maneuver['modifier'] ?? null;
        $key = match ($type) {
            'depart' => 'depart',
            'arrive' => 'arrive',
            'roundabout', 'rotary' => isset($maneuver['exit']) ? 'roundabout' : null,
            'exit roundabout', 'exit rotary' => 'exit_roundabout',
            'new name', 'notification' => $modifier === null ? 'depart' : 'directions.'.$modifier,
            default => $modifier === null ? null : 'directions.'.$modifier,
        };
        if ($key === null) {
            throw new AssistiveServiceException('route_instructions_unavailable', 422);
        }
        $instruction = __('assistive.osrm.'.$key, ['exit' => $maneuver['exit'] ?? ''], $language);
        $road = trim(strip_tags($step['name'] ?? ''));
        if ($road !== '' && $type !== 'arrive') {
            $instruction .= ' '.__('assistive.osrm.road', ['road' => $road], $language);
        }

        return $instruction;
    }

    public function attribution(): string
    {
        return '© OpenStreetMap contributors';
    }
}
