<?php

namespace App\Policies;

use App\Enums\FamilyPermission;
use App\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isNgoStaff() || $user->isFamily();
    }

    public function view(User $user, Alert $alert): bool
    {
        return $alert->blindUser !== null && $user->canAccessBlindUser($alert->blindUser, FamilyPermission::ReceiveAlerts);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Alert $alert): bool
    {
        return $user->isSuperAdmin()
            || ($user->isNgoStaff() && $alert->blindUser !== null && $user->canAccessBlindUser($alert->blindUser, FamilyPermission::ReceiveAlerts));
    }

    public function delete(User $user, Alert $alert): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, Alert $alert): bool
    {
        return false;
    }

    public function forceDelete(User $user, Alert $alert): bool
    {
        return $user->isSuperAdmin();
    }

    public function resolve(User $user, Alert $alert): bool
    {
        return $alert->blindUser !== null && $user->canAccessBlindUser($alert->blindUser, FamilyPermission::ReceiveAlerts);
    }

    public function markFalseAlarm(User $user, Alert $alert): bool
    {
        return $user->isFamily()
            && $alert->blindUser !== null
            && $user->canAccessBlindUser($alert->blindUser, FamilyPermission::ReceiveAlerts);
    }
}
