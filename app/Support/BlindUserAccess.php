<?php

namespace App\Support;

use App\Enums\FamilyPermission;
use App\Models\BlindUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class BlindUserAccess
{
    public static function canViewLocation(User $user, BlindUser $blindUser): bool
    {
        return $user->canAccessBlindUser($blindUser, FamilyPermission::ViewLocation);
    }

    public static function canReceiveAlerts(User $user, BlindUser $blindUser): bool
    {
        return $user->canAccessBlindUser($blindUser, FamilyPermission::ReceiveAlerts);
    }

    /**
     * @return Collection<int, User>
     */
    public static function alertRecipients(BlindUser $blindUser): Collection
    {
        return $blindUser->familyMembersWhoReceiveAlerts()->where('users.is_active', true)->get();
    }
}
