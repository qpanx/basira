<?php

namespace App\Filament\Resources\NavigationSessionResource\Pages;

use App\Filament\Resources\NavigationSessionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageNavigationSessions extends ManageRecords
{
    protected static string $resource = NavigationSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
