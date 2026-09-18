<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isNgoStaff() || $user->isFamily();
    }

    public function view(User $user, Device $device): bool
    {
        return $user->isSuperAdmin()
            || ($device->blindUser !== null && $user->canAccessBlindUser($device->blindUser));
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isNgoStaff();
    }

    public function update(User $user, Device $device): bool
    {
        return $user->isSuperAdmin()
            || ($device->blindUser !== null && $user->isNgoStaff() && $user->canAccessBlindUser($device->blindUser));
    }

    public function delete(User $user, Device $device): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, Device $device): bool
    {
        return false;
    }

    public function forceDelete(User $user, Device $device): bool
    {
        return $user->isSuperAdmin();
    }
}
