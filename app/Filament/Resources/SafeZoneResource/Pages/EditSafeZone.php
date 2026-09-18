<?php

namespace App\Filament\Resources\SafeZoneResource\Pages;

use App\Filament\Resources\SafeZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSafeZone extends EditRecord
{
    protected static string $resource = SafeZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
