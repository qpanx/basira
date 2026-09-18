<?php

use App\Enums\FamilyPermission;
use App\Models\BlindUser;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['web', 'auth']]);

Broadcast::channel('App.Models.User.{id}', function (User $user, int|string $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('family.{blindUserId}', function (User $user, int|string $blindUserId): bool {
    $blindUser = BlindUser::query()->find($blindUserId);

    return $blindUser !== null
        && $user->canAccessBlindUser($blindUser, FamilyPermission::ViewLocation);
});
