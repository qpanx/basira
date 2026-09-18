<?php

namespace App\Models;

use App\Enums\FamilyPermission;
use Database\Factories\BlindUserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlindUser extends Model
{
    /** @use HasFactory<BlindUserFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ngo_id',
        'created_by',
        'name',
        'phone',
        'emergency_notes',
        'is_active',
    ];

    public function scopeAccessibleTo(
        Builder $query,
        User $actor,
        ?FamilyPermission $permission = null,
    ): Builder {
        if ($actor->isSuperAdmin()) {
            return $query;
        }

        if ($actor->isNgoStaff()) {
            return $actor->ngo_id === null
                ? $query->whereRaw('1 = 0')
                : $query->where('ngo_id', $actor->ngo_id);
        }

        if (! $actor->isFamily()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('familyMembers', function (Builder $familyQuery) use ($actor, $permission): void {
            $familyQuery->whereKey($actor->getKey());

            if ($permission !== null) {
                $familyQuery->whereJsonContains(
                    'family_blind_user.permissions->'.$permission->value,
                    true,
                );
            }
        });
    }

    public function scopeVisibleTo(
        Builder $query,
        User $actor,
        ?FamilyPermission $permission = null,
    ): Builder {
        return $this->scopeAccessibleTo($query, $actor, $permission);
    }

    /**
     * @return BelongsTo<Ngo, $this>
     */
    public function ngo(): BelongsTo
    {
        return $this->belongsTo(Ngo::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<User, $this, FamilyBlindUser>
     */
    public function familyMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'family_blind_user')
            ->using(FamilyBlindUser::class)
            ->withPivot(['id', 'relationship', 'permissions'])
            ->withTimestamps();
    }

    /**
     * Family members that opted in to operational alerts for this person.
     *
     * @return BelongsToMany<User, $this, FamilyBlindUser>
     */
    public function familyMembersWhoReceiveAlerts(): BelongsToMany
    {
        return $this->familyMembers()->whereJsonContains(
            'family_blind_user.permissions->'.FamilyPermission::ReceiveAlerts->value,
            true,
        );
    }

    /**
     * Family members allowed to view the current location.
     *
     * @return BelongsToMany<User, $this, FamilyBlindUser>
     */
    public function familyMembersWhoCanViewLocation(): BelongsToMany
    {
        return $this->familyMembers()->whereJsonContains(
            'family_blind_user.permissions->'.FamilyPermission::ViewLocation->value,
            true,
        );
    }

    /** @return HasMany<Device, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /** @return HasMany<SafeZone, $this> */
    public function safeZones(): HasMany
    {
        return $this->hasMany(SafeZone::class);
    }

    /** @return HasMany<Location, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /** @return HasMany<Alert, $this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /** @return HasMany<BatteryLog, $this> */
    public function batteryLogs(): HasMany
    {
        return $this->hasMany(BatteryLog::class);
    }

    /** @return HasMany<ArrivalConfirmation, $this> */
    public function arrivalConfirmations(): HasMany
    {
        return $this->hasMany(ArrivalConfirmation::class);
    }

    /** @return HasMany<DeviceAssignment, $this> */
    public function deviceAssignments(): HasMany
    {
        return $this->hasMany(DeviceAssignment::class);
    }

    /** @return HasMany<UserFeedback, $this> */
    public function feedback(): HasMany
    {
        return $this->hasMany(UserFeedback::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emergency_notes' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }
}
