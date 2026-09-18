<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = Filament::auth()->user();

        if ($actor instanceof User && ! $actor->isSuperAdmin()) {
            $data['role'] = UserRole::Family;
            $data['ngo_id'] = $actor->ngo_id;
        }

        return $data;
    }
}
