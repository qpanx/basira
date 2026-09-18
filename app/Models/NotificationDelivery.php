<?php

namespace App\Models;

use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'alert_id',
        'recipient_user_id',
        'notification_id',
        'channel',
        'provider',
        'status',
        'provider_reference',
        'attempt_count',
        'destination_last_four',
        'queued_at',
        'accepted_at',
        'delivered_at',
        'failed_at',
        'failure_code',
        'failure_message',
    ];

    public function scopeAccessibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->isSuperAdmin()) {
            return $query;
        }

        if ($actor->isFamily()) {
            return $query->where('recipient_user_id', $actor->getKey());
        }

        if ($actor->isNgoStaff() && $actor->ngo_id !== null) {
            return $query->whereHas(
                'alert.blindUser',
                fn (Builder $blindUserQuery): Builder => $blindUserQuery->where('ngo_id', $actor->ngo_id),
            );
        }

        return $query->whereRaw('1 = 0');
    }

    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        return $this->scopeAccessibleTo($query, $actor);
    }

    /** @return BelongsTo<Alert, $this> */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => DeliveryChannel::class,
            'status' => DeliveryStatus::class,
            'attempt_count' => 'integer',
            'queued_at' => 'datetime',
            'accepted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
