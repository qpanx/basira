<?php

namespace App\Services;

class HaversineDistance
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    public function meters(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $latitudeDelta = deg2rad($toLat - $fromLat);
        $longitudeDelta = deg2rad($toLng - $fromLng);
        $fromLatitude = deg2rad($fromLat);
        $toLatitude = deg2rad($toLat);

        $haversine = sin($latitudeDelta / 2) ** 2
            + cos($fromLatitude) * cos($toLatitude) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * asin(sqrt(min(1, $haversine)));
    }
}
