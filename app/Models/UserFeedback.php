<?php

namespace App\Models;

use Database\Factories\UserFeedbackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeedback extends Model
{
    /** @use HasFactory<UserFeedbackFactory> */
    use HasFactory;

    protected $table = 'user_feedback';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'blind_user_id',
        'submitted_by',
        'rating',
        'comment',
        'period_start',
        'period_end',
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

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }
}
