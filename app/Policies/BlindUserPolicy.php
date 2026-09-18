<?php

namespace App\Policies;

use App\Models\BlindUser;
use App\Models\User;

class BlindUserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isNgoStaff() || $user->isFamily();
    }

    public function view(User $user, BlindUser $blindUser): bool
    {
        return $user->canAccessBlindUser($blindUser);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isNgoStaff();
    }

    public function update(User $user, BlindUser $blindUser): bool
    {
        return $user->isSuperAdmin() || ($user->isNgoStaff() && $user->canAccessBlindUser($blindUser));
    }

    public function delete(User $user, BlindUser $blindUser): bool
    {
        return $user->isSuperAdmin() || ($user->isNgoStaff() && $user->canAccessBlindUser($blindUser));
    }

    public function restore(User $user, BlindUser $blindUser): bool
    {
        return false;
    }

    public function forceDelete(User $user, BlindUser $blindUser): bool
    {
        return $user->isSuperAdmin();
    }
}
