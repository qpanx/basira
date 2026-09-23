<?php

namespace App\Models;

use App\Enums\PowerState;
use Database\Factories\BatteryLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $recorded_at
 * @property Carbon $received_at
 */
class BatteryLog extends Model
{
    /** @use HasFactory<BatteryLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'blind_user_id',
        'message_id',
        'battery_level',
        'voltage',
        'power_state',
        'recorded_at',
        'received_at',
    ];

    public function scopeAccessibleTo(Builder $query, User $actor): Builder
    {
        return $query->whereHas(
            'blindUser',
            fn (Builder $blindUserQuery): Builder => $blindUserQuery->accessibleTo($actor),
        );
    }

    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        return $this->scopeAccessibleTo($query, $actor);
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'battery_level' => 'integer',
            'voltage' => 'decimal:3',
            'power_state' => PowerState::class,
            'recorded_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
