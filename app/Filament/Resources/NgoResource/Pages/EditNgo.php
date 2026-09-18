<?php

namespace App\Filament\Resources\NgoResource\Pages;

use App\Filament\Resources\NgoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNgo extends EditRecord
{
    protected static string $resource = NgoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
