<?php

namespace App\Policies;

use App\Models\Ngo;
use App\Models\User;

class NgoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Ngo $ngo): bool
    {
        return $user->isSuperAdmin() || ($user->isNgoStaff() && $user->ngo_id === $ngo->getKey());
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Ngo $ngo): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Ngo $ngo): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, Ngo $ngo): bool
    {
        return false;
    }

    public function forceDelete(User $user, Ngo $ngo): bool
    {
        return $user->isSuperAdmin();
    }
}
