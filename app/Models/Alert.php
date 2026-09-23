<?php

namespace App\Models;

use App\Enums\AlertResolution;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\FamilyPermission;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property AlertType $type
 * @property AlertSeverity $severity
 * @property Carbon|null $occurred_at
 */
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'blind_user_id',
        'resolved_by',
        'message_id',
        'type',
        'severity',
        'distance_cm',
        'sensor_value',
        'sensor_unit',
        'lat',
        'lng',
        'occurred_at',
        'resolution',
        'resolution_notes',
        'resolved_at',
    ];

    public function scopeAccessibleTo(Builder $query, User $actor): Builder
    {
        return $query->whereHas(
            'blindUser',
            fn (Builder $blindUserQuery): Builder => $blindUserQuery->accessibleTo($actor, FamilyPermission::ReceiveAlerts),
        );
    }

    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        return $this->scopeAccessibleTo($query, $actor);
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }

    public function scopeFalsePositive(Builder $query): Builder
    {
        return $query->where('resolution', AlertResolution::FalsePositive);
    }

    public function scopeSos(Builder $query): Builder
    {
        return $query->where('type', AlertType::Sos);
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function isFalsePositive(): bool
    {
        return $this->resolution === AlertResolution::FalsePositive;
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return BelongsTo<BlindUser, $this> */
    public function blindUser(): BelongsTo
    {
        return $this->belongsTo(BlindUser::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return HasMany<NotificationDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'severity' => AlertSeverity::class,
            'distance_cm' => 'integer',
            'sensor_value' => 'decimal:3',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'occurred_at' => 'datetime',
            'resolution' => AlertResolution::class,
            'resolved_at' => 'datetime',
        ];
    }
}
