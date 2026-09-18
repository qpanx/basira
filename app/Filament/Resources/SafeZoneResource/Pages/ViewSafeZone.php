<?php

namespace App\Filament\Resources\SafeZoneResource\Pages;

use App\Filament\Resources\SafeZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSafeZone extends ViewRecord
{
    protected static string $resource = SafeZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
