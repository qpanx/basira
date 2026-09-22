<?php

namespace App\Services;

use App\Contracts\DestinationProvider;
use App\Exceptions\AssistiveServiceException;

/** Coordinate-only destination selection for routing backends without a geocoder. */
class CoordinateDestinationProvider implements DestinationProvider
{
    public function search(string $query, string $language): array
    {
        if (! preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/D', $query, $matches)) {
            throw new AssistiveServiceException('coordinate_destination_required', 422);
        }
        $latitude = (float) $matches[1];
        $longitude = (float) $matches[2];
        if (abs($latitude) > 90 || abs($longitude) > 180) {
            throw new AssistiveServiceException('coordinate_destination_required', 422);
        }

        return [$this->destination($latitude, $longitude, $language)];
    }

    public function place(string $placeId, string $language): array
    {
        if (! preg_match('/^coordinates_(-?\d+)_(\-?\d+)$/D', $placeId, $matches)
            || abs((float) $matches[1]) > 900000000 || abs((float) $matches[2]) > 1800000000) {
            throw new AssistiveServiceException('destination_not_found', 422);
        }

        return $this->destination((float) $matches[1] / 10000000, (float) $matches[2] / 10000000, $language);
    }

    /** @return array{place_id: string, name: string, address: string, latitude: float, longitude: float} */
    private function destination(float $latitude, float $longitude, string $language): array
    {
        $latitudeUnits = (int) round($latitude * 10000000);
        $longitudeUnits = (int) round($longitude * 10000000);

        return [
            'place_id' => "coordinates_{$latitudeUnits}_{$longitudeUnits}",
            'name' => __('assistive.coordinate_destination', ['latitude' => $latitudeUnits / 10000000, 'longitude' => $longitudeUnits / 10000000], $language),
            'address' => '',
            'latitude' => $latitudeUnits / 10000000,
            'longitude' => $longitudeUnits / 10000000,
        ];
    }

    public function attribution(): string
    {
        return 'User-supplied coordinates';
    }
}
