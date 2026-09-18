<?php

namespace App\Filament\Resources\NgoResource\Pages;

use App\Filament\Resources\NgoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewNgo extends ViewRecord
{
    protected static string $resource = NgoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
