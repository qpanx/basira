<?php

namespace App\Filament\Resources\BlindUserResource\Pages;

use App\Filament\Resources\BlindUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlindUser extends EditRecord
{
    protected static string $resource = BlindUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
