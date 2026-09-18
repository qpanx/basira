<?php

namespace App\Models;

use Database\Factories\NgoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ngo extends Model
{
    /** @use HasFactory<NgoFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'address',
        'contact_name',
        'contact_email',
        'contact_phone',
        'is_active',
    ];

    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->isSuperAdmin()) {
            return $query;
        }

        if ($actor->isNgoStaff() && $actor->ngo_id !== null) {
            return $query->whereKey($actor->ngo_id);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<BlindUser, $this>
     */
    public function blindUsers(): HasMany
    {
        return $this->hasMany(BlindUser::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
