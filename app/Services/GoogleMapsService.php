<?php

namespace App\Services;

use App\Exceptions\AssistiveServiceException;
use App\Models\Location;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GoogleMapsService
{
    /** @return list<array{place_id: string, name: string, address: string, latitude: float, longitude: float}> */
    public function search(string $query, string $language): array
    {
        $data = $this->request('POST', 'https://places.googleapis.com/v1/places:searchText', [
            'textQuery' => $query,
            'languageCode' => $language,
            'pageSize' => 5,
        ], 'places.id,places.displayName,places.formattedAddress,places.location');

        if (! isset($data['places'])) {
            return [];
        }
        if (! is_array($data['places']) || ! array_is_list($data['places'])) {
            throw new AssistiveServiceException('invalid_provider_response', 502);
        }

        return array_map(fn (mixed $place): array => $this->normalizePlace($place), $data['places']);
    }

    /** @return array{place_id: string, name: string, address: string, latitude: float, longitude: float} */
    public function place(string $placeId, string $language): array
    {
        return $this->normalizePlace($this->request('GET', 'https://places.googleapis.com/v1/places/'.rawurlencode($placeId), [
            'languageCode' => $language,
        ], 'id,displayName,formattedAddress,location'));
    }

    /** @return array{place_id: string, name: string, address: string, latitude: float, longitude: float} */
    private function normalizePlace(mixed $place): array
    {
        if (! is_array($place) || Validator::make($place, [
            'id' => ['required', 'string', 'max:255'],
            'displayName.text' => ['required', 'string', 'max:1000'],
            'formattedAddress' => ['sometimes', 'string', 'max:2000'],
            'location.latitude' => ['required', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required', 'numeric', 'between:-180,180'],
        ])->fails()) {
            throw new AssistiveServiceException('invalid_provider_response', 502);
        }

        return [
            'place_id' => $place['id'],
            'name' => $place['displayName']['text'],
            'address' => $place['formattedAddress'] ?? '',
            'latitude' => (float) $place['location']['latitude'],
            'longitude' => (float) $place['location']['longitude'],
        ];
    }

    /**
     * @return array{distance_meters: int, estimated_duration_seconds: int, steps: list<array<string, mixed>>, route_coordinates: list<array{0: float, 1: float}>, warnings: list<string>}
     */
    public function walkingRoute(Location $origin, string $destinationPlaceId, string $language): array
    {
        $data = $this->request('POST', 'https://routes.googleapis.com/directions/v2:computeRoutes', [
            'origin' => ['location' => ['latLng' => ['latitude' => (float) $origin->lat, 'longitude' => (float) $origin->lng]]],
            'destination' => ['placeId' => $destinationPlaceId],
            'travelMode' => 'WALK',
            'languageCode' => $language,
            'units' => 'METRIC',
            'polylineEncoding' => 'GEO_JSON_LINESTRING',
            'polylineQuality' => 'HIGH_QUALITY',
        ], 'routes.distanceMeters,routes.duration,routes.legs.steps.distanceMeters,routes.legs.steps.navigationInstruction,routes.legs.steps.endLocation,routes.polyline.geoJsonLinestring,routes.warnings');

        if ($data === [] || ($data['routes'] ?? null) === []) {
            throw new AssistiveServiceException('route_not_found', 422);
        }
        $route = data_get($data, 'routes.0');
        if (! is_array($route) || Validator::make($route, [
            'distanceMeters' => ['required', 'integer', 'min:0'],
            'duration' => ['required', 'string', 'regex:/^\d+(\.\d+)?s$/'],
            'legs' => ['required', 'array', 'min:1'],
            'legs.*.steps' => ['required', 'array', 'min:1', 'max:2000'],
            'legs.*.steps.*.distanceMeters' => ['sometimes', 'integer', 'min:0'],
            'legs.*.steps.*.navigationInstruction.instructions' => ['required', 'string', 'max:4000'],
            'legs.*.steps.*.endLocation.latLng.latitude' => ['required', 'numeric', 'between:-90,90'],
            'legs.*.steps.*.endLocation.latLng.longitude' => ['required', 'numeric', 'between:-180,180'],
            'polyline.geoJsonLinestring.coordinates' => ['required', 'array', 'min:2', 'max:50000'],
            'polyline.geoJsonLinestring.coordinates.*' => ['required', 'array', 'size:2'],
            'polyline.geoJsonLinestring.coordinates.*.0' => ['required', 'numeric', 'between:-180,180'],
            'polyline.geoJsonLinestring.coordinates.*.1' => ['required', 'numeric', 'between:-90,90'],
            'warnings' => ['sometimes', 'array'],
            'warnings.*' => ['string', 'max:4000'],
        ])->fails()) {
            throw new AssistiveServiceException('invalid_provider_response', 502);
        }

        $steps = [];
        foreach ($route['legs'] as $leg) {
            foreach ($leg['steps'] as $step) {
                $steps[] = [
                    'instruction' => trim(strip_tags(html_entity_decode($step['navigationInstruction']['instructions'], ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                    'distance_meters' => (int) ($step['distanceMeters'] ?? 0),
                    'end_latitude' => (float) $step['endLocation']['latLng']['latitude'],
                    'end_longitude' => (float) $step['endLocation']['latLng']['longitude'],
                ];
            }
        }

        return [
            'distance_meters' => (int) $route['distanceMeters'],
            'estimated_duration_seconds' => (int) ceil((float) rtrim($route['duration'], 's')),
            'steps' => $steps,
            'route_coordinates' => $route['polyline']['geoJsonLinestring']['coordinates'],
            'warnings' => array_values($route['warnings'] ?? []),
        ];
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $payload, string $fieldMask): array
    {
        $key = config('services.google_maps.api_key');
        if (! is_string($key) || $key === '') {
            throw new AssistiveServiceException('provider_not_configured');
        }

        try {
            $client = Http::acceptJson()->connectTimeout(3)
                ->timeout(max(1, min(20, (int) config('services.google_maps.timeout', 10))))
                ->withoutRedirecting()
                ->withHeaders(['X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => $fieldMask]);
            $response = $method === 'GET' ? $client->get($url, $payload) : $client->post($url, $payload);
        } catch (ConnectionException) {
            Log::warning('Google Maps connection failed.');
            throw new AssistiveServiceException('provider_unavailable');
        }

        if ($response->status() === 404) {
            throw new AssistiveServiceException('destination_not_found', 422);
        }
        if (! $response->successful()) {
            Log::warning('Google Maps request failed.', ['status' => $response->status()]);
            throw new AssistiveServiceException('provider_unavailable');
        }
        $data = $response->json();
        if (! is_array($data) || array_key_exists('error', $data)) {
            throw new AssistiveServiceException('invalid_provider_response', 502);
        }

        return $data;
    }
}
