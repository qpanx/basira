<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Enums\FamilyPermission;
use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class LiveMap extends Page
{
    use InteractsWithRecord;

    protected static string $resource = DeviceResource::class;

    protected static string $view = 'filament.resources.device-resource.pages.live-map';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $canView = $this->record instanceof Device && static::getResource()::canView($this->record);
        $actor = Filament::auth()->user();
        $canViewLocation = $actor instanceof User
            && $this->record instanceof Device
            && ($actor->isSuperAdmin()
                || ($this->record->blindUser !== null
                    && $actor->canAccessBlindUser($this->record->blindUser, FamilyPermission::ViewLocation)));

        abort_unless($canView && $canViewLocation, 404);
    }

    public function getDevice(): Device
    {
        /** @var Device $device */
        $device = $this->record;

        return $device->load(['blindUser', 'locations' => fn ($query) => $query->latest('recorded_at')->limit(1)]);
    }
}
