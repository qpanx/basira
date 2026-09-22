<?php

namespace App\Services;

use App\Contracts\DestinationProvider;
use App\Contracts\RoutingProvider;
use App\Models\Location;

class GoogleRoutingProvider implements DestinationProvider, RoutingProvider
{
    public function __construct(private GoogleMapsService $maps) {}

    public function search(string $query, string $language): array
    {
        return $this->maps->search($query, $language);
    }

    public function place(string $placeId, string $language): array
    {
        return $this->maps->place($placeId, $language);
    }

    public function walkingRoute(Location $origin, array $destination, string $language): array
    {
        return $this->maps->walkingRoute($origin, $destination['place_id'], $language);
    }

    public function attribution(): string
    {
        return 'Google Maps';
    }
}
