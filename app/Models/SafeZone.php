<?php

namespace App\Models;

use App\Enums\FamilyPermission;
use Database\Factories\SafeZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SafeZone extends Model
{
    /** @use HasFactory<SafeZoneFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'blind_user_id',
        'name',
        'center_lat',
        'center_lng',
        'radius_meters',
        'is_active',
    ];

    public function scopeAccessibleTo(
        Builder $query,
        User $actor,
        ?FamilyPermission $permission = null,
    ): Builder {
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return BelongsTo<BlindUser, $this> */
    public function blindUser(): BelongsTo
    {
        return $this->belongsTo(BlindUser::class);
    }

    /** @return HasMany<DeviceSafeZoneState, $this> */
    public function deviceStates(): HasMany
    {
        return $this->hasMany(DeviceSafeZoneState::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'center_lat' => 'decimal:7',
            'center_lng' => 'decimal:7',
            'radius_meters' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
