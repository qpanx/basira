<?php

namespace App\Models;

use Database\Factories\ArrivalConfirmationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArrivalConfirmation extends Model
{
    /** @use HasFactory<ArrivalConfirmationFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'blind_user_id',
        'device_id',
        'message_id',
        'lat',
        'lng',
        'confirmed_at',
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

    /** @return BelongsTo<BlindUser, $this> */
    public function blindUser(): BelongsTo
    {
        return $this->belongsTo(BlindUser::class);
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'confirmed_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
