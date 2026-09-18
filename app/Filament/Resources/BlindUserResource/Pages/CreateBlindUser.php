<?php

namespace App\Filament\Resources\BlindUserResource\Pages;

use App\Filament\Resources\BlindUserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateBlindUser extends CreateRecord
{
    protected static string $resource = BlindUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = Filament::auth()->user();

        if ($actor instanceof User) {
            $data['created_by'] = $actor->getKey();
            if ($actor->isNgoStaff()) {
                $data['ngo_id'] = $actor->ngo_id;
            }
        }

        return $data;
    }
}
