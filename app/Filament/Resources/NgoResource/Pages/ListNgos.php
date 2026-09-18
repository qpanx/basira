<?php

namespace App\Filament\Resources\NgoResource\Pages;

use App\Filament\Resources\NgoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNgos extends ListRecords
{
    protected static string $resource = NgoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
