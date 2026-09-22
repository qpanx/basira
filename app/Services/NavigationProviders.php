<?php

namespace App\Services;

use App\Contracts\DestinationProvider;
use App\Contracts\RoutingProvider;
use App\Exceptions\AssistiveServiceException;

class NavigationProviders
{
    public function __construct(
        private GoogleRoutingProvider $google,
        private OsrmRoutingProvider $osrm,
        private CoordinateDestinationProvider $coordinates,
    ) {}

    public function selected(): string
    {
        $name = config('services.navigation.provider', 'google');
        if (! in_array($name, ['google', 'osrm'], true)) {
            throw new AssistiveServiceException('navigation_provider_invalid');
        }

        return $name;
    }

    public function routing(?string $name = null): RoutingProvider
    {
        return match ($name ?? $this->selected()) {
            'google' => $this->google,
            'osrm' => $this->osrm,
            default => throw new AssistiveServiceException('navigation_provider_invalid'),
        };
    }

    public function destinations(?string $name = null): DestinationProvider
    {
        return match ($name ?? $this->selected()) {
            'google' => $this->google,
            'osrm' => $this->coordinates,
            default => throw new AssistiveServiceException('navigation_provider_invalid'),
        };
    }
}
