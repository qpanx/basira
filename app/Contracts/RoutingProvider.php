<?php

namespace App\Contracts;

use App\Models\Location;

interface RoutingProvider
{
    /**
     * @param  array{place_id: string, name: string, address: string, latitude: float, longitude: float}  $destination
     * @return array{distance_meters: int, estimated_duration_seconds: int, steps: list<array<string, mixed>>, route_coordinates: list<array{0: float, 1: float}>, warnings: list<string>}
     */
    public function walkingRoute(Location $origin, array $destination, string $language): array;

    public function attribution(): string;
}
