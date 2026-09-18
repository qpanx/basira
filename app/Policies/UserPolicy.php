<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isNgoStaff() || $user->isFamily();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin()
            || $user->getKey() === $model->getKey()
            || ($user->isNgoStaff()
                && $model->role === UserRole::Family
                && $this->belongsToNgo($model, $user));
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isNgoStaff();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSuperAdmin()
            || $user->getKey() === $model->getKey()
            || ($user->isNgoStaff()
                && $model->role !== UserRole::SuperAdmin
                && $this->belongsToNgo($model, $user));
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->getKey() !== $model->getKey();
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->getKey() !== $model->getKey();
    }

    private function belongsToNgo(User $model, User $staff): bool
    {
        return $staff->ngo_id !== null
            && ($model->ngo_id === $staff->ngo_id
                || $model->blindUsers()->where('blind_users.ngo_id', $staff->ngo_id)->exists());
    }
}
