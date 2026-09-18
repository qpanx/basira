<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Models\DeviceAssignment;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateDevice extends CreateRecord
{
    protected static string $resource = DeviceResource::class;

    protected function afterCreate(): void
    {
        $actor = Filament::auth()->user();

        if ($actor instanceof User && $this->record->blind_user_id !== null) {
            DeviceAssignment::query()->create([
                'device_id' => $this->record->getKey(),
                'blind_user_id' => $this->record->blind_user_id,
                'assigned_by' => $actor->getKey(),
                'assigned_at' => now(),
            ]);
        }
    }
}
