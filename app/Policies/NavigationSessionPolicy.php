<?php

namespace App\Policies;

use App\Enums\FamilyPermission;
use App\Models\NavigationSession;
use App\Models\User;

class NavigationSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isNgoStaff() || $user->isFamily();
    }

    public function view(User $user, NavigationSession $session): bool
    {
        return $session->blindUser !== null && $user->canAccessBlindUser($session->blindUser, FamilyPermission::ViewLocation);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, NavigationSession $session): bool
    {
        return false;
    }

    public function delete(User $user, NavigationSession $session): bool
    {
        return false;
    }
}
