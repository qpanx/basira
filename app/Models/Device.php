<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Enums\FamilyPermission;
use App\Enums\Language;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasApiTokens, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'blind_user_id',
        'serial_number',
        'firmware_version',
        'battery_level',
        'preferred_language',
        'status',
        'is_enabled',
        'settings',
        'configuration_version',
        'last_seen_at',
        'last_location_recorded_at',
        'geofence_evaluated_at',
    ];

    public function scopeAccessibleTo(
        Builder $query,
        User $actor,
        ?FamilyPermission $permission = null,
    ): Builder {
        if ($actor->isSuperAdmin()) {
            return $query;
        }

        return $query->whereHas(
            'blindUser',
            fn (Builder $blindUserQuery): Builder => $blindUserQuery->accessibleTo($actor, $permission),
        );
    }

    public function scopeVisibleTo(
        Builder $query,
        User $actor,
        ?FamilyPermission $permission = null,
    ): Builder {
        return $this->scopeAccessibleTo($query, $actor, $permission);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('status', DeviceStatus::Active);
    }

    /** @return BelongsTo<BlindUser, $this> */
    public function blindUser(): BelongsTo
    {
        return $this->belongsTo(BlindUser::class);
    }

    /** @return HasMany<DeviceAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(DeviceAssignment::class);
    }

    /** @return HasOne<DeviceAssignment, $this> */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(DeviceAssignment::class)->whereNull('ended_at');
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

    /** @return HasMany<DeviceSafeZoneState, $this> */
    public function safeZoneStates(): HasMany
    {
        return $this->hasMany(DeviceSafeZoneState::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'battery_level' => 'integer',
            'preferred_language' => Language::class,
            'status' => DeviceStatus::class,
            'is_enabled' => 'boolean',
            'settings' => 'array',
            'configuration_version' => 'integer',
            'last_seen_at' => 'datetime',
            'last_location_recorded_at' => 'datetime',
            'geofence_evaluated_at' => 'datetime',
        ];
    }
}
