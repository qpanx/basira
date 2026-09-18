<?php

namespace App\Filament\Resources\BlindUserResource\Pages;

use App\Filament\Resources\BlindUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBlindUser extends ViewRecord
{
    protected static string $resource = BlindUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
