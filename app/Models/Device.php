<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Enums\FamilyPermission;
use App\Enums\Language;
use Carbon\CarbonInterface;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property DeviceStatus $status
 * @property Carbon|null $last_status_recorded_at
 * @property Carbon|null $last_status_received_at
 */
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasApiTokens, HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s.v';

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
        'last_status_recorded_at',
        'last_status_received_at',
        'last_status_online',
        'last_location_recorded_at',
        'geofence_evaluated_at',
    ];

    /** Cloud backlogs are history, not proof that the cane is currently connected. */
    public function recordCloudActivity(CarbonInterface $recordedAt): void
    {
        if ($recordedAt->lessThan(now()->subMinutes((int) config('smart-cane.offline_after_minutes', 10)))
            || ($this->last_status_recorded_at !== null && $recordedAt->lessThan($this->last_status_recorded_at))) {
            return;
        }

        $this->update([
            'last_seen_at' => now(),
            'status' => $this->status === DeviceStatus::Maintenance ? DeviceStatus::Maintenance : DeviceStatus::Active,
        ]);
    }

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

    /** @return HasMany<NavigationSession, $this> */
    public function navigationSessions(): HasMany
    {
        return $this->hasMany(NavigationSession::class);
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
            'last_status_recorded_at' => 'datetime',
            'last_status_received_at' => 'datetime',
            'last_status_online' => 'boolean',
            'last_location_recorded_at' => 'datetime',
            'geofence_evaluated_at' => 'datetime',
        ];
    }
}
