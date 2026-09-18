<?php

namespace App\Policies;

use App\Enums\FamilyPermission;
use App\Models\BlindUser;
use App\Models\SafeZone;
use App\Models\User;

class SafeZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isNgoStaff() || $user->isFamily();
    }

    public function view(User $user, SafeZone $safeZone): bool
    {
        return $safeZone->blindUser !== null && $user->canAccessBlindUser($safeZone->blindUser);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isNgoStaff()
            || ($user->isFamily() && BlindUser::query()->accessibleTo($user, FamilyPermission::ManageSafeZones)->exists());
    }

    public function update(User $user, SafeZone $safeZone): bool
    {
        return $safeZone->blindUser !== null
            && ($user->isSuperAdmin() || $user->canAccessBlindUser($safeZone->blindUser, FamilyPermission::ManageSafeZones));
    }

    public function delete(User $user, SafeZone $safeZone): bool
    {
        return $safeZone->blindUser !== null
            && ($user->isSuperAdmin() || $user->canAccessBlindUser($safeZone->blindUser, FamilyPermission::ManageSafeZones));
    }

    public function restore(User $user, SafeZone $safeZone): bool
    {
        return false;
    }

    public function forceDelete(User $user, SafeZone $safeZone): bool
    {
        return $user->isSuperAdmin();
    }
}
