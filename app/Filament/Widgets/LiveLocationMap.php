<?php

namespace App\Filament\Widgets;

use App\Enums\FamilyPermission;
use App\Models\Device;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class LiveLocationMap extends Widget
{
    protected static string $view = 'filament.widgets.live-location-map';

    protected int|string|array $columnSpan = 'full';

    public function getDevices(): array
    {
        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            return [];
        }

        return Device::query()
            ->visibleTo($actor, FamilyPermission::ViewLocation)
            ->with(['blindUser', 'locations' => fn ($query) => $query->latest('recorded_at')->limit(1)])
            ->get()
            ->map(fn (Device $device): array => [
                'id' => $device->getKey(),
                'name' => $device->blindUser?->name ?? $device->serial_number,
                'serial_number' => $device->serial_number,
                'lat' => $device->locations->first()?->lat === null ? null : (float) $device->locations->first()->lat,
                'lng' => $device->locations->first()?->lng === null ? null : (float) $device->locations->first()->lng,
                'status' => $device->status->value,
                'blind_user_id' => $device->blind_user_id,
            ])->values()->all();
    }
}
