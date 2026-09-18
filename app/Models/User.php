<?php

namespace App\Models;

use App\Enums\FamilyPermission;
use App\Enums\Language;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ngo_id',
        'name',
        'email',
        'phone',
        'role',
        'preferred_language',
        'is_active',
        'email_verified_at',
        'phone_verified_at',
        'sms_opt_in_at',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->isSuperAdmin()) {
            return $query;
        }

        if ($actor->isFamily()) {
            return $query->whereKey($actor->getKey());
        }

        if ($actor->isNgoStaff() && $actor->ngo_id !== null) {
            return $query
                ->where('role', UserRole::Family)
                ->where(function (Builder $userQuery) use ($actor): void {
                    $userQuery
                        ->where('ngo_id', $actor->ngo_id)
                        ->orWhereHas('blindUsers', function (Builder $blindUserQuery) use ($actor): void {
                            $blindUserQuery->where('ngo_id', $actor->ngo_id);
                        });
                });
        }

        return $query->whereRaw('1 = 0');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active
            && $this->email_verified_at !== null
            && in_array($this->role, UserRole::cases(), true);
    }

    public function preferredLocale(): string
    {
        return $this->preferred_language?->value ?? (string) config('app.fallback_locale', 'en');
    }

    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    public function routeNotificationForVoice(): ?string
    {
        return $this->phone;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isNgoStaff(): bool
    {
        return $this->role === UserRole::NgoStaff;
    }

    public function isFamily(): bool
    {
        return $this->role === UserRole::Family;
    }

    public function canAccessBlindUser(BlindUser $blindUser, ?FamilyPermission $permission = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isNgoStaff()) {
            return $this->ngo_id !== null && $this->ngo_id === $blindUser->ngo_id;
        }

        if (! $this->isFamily()) {
            return false;
        }

        $linkedBlindUser = $this->blindUsers()
            ->whereKey($blindUser->getKey())
            ->first();

        if ($linkedBlindUser === null) {
            return false;
        }

        if ($permission === null) {
            return true;
        }

        $pivot = $linkedBlindUser->pivot;

        return $pivot instanceof FamilyBlindUser && $pivot->allows($permission);
    }

    /**
     * @return BelongsTo<Ngo, $this>
     */
    public function ngo(): BelongsTo
    {
        return $this->belongsTo(Ngo::class);
    }

    /**
     * @return BelongsToMany<BlindUser, $this, FamilyBlindUser>
     */
    public function blindUsers(): BelongsToMany
    {
        return $this->belongsToMany(BlindUser::class, 'family_blind_user')
            ->using(FamilyBlindUser::class)
            ->withPivot(['id', 'relationship', 'permissions'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<BlindUser, $this>
     */
    public function createdBlindUsers(): HasMany
    {
        return $this->hasMany(BlindUser::class, 'created_by');
    }

    /**
     * @return HasMany<DeviceAssignment, $this>
     */
    public function deviceAssignments(): HasMany
    {
        return $this->hasMany(DeviceAssignment::class, 'assigned_by');
    }

    /**
     * @return HasMany<Alert, $this>
     */
    public function resolvedAlerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'resolved_by');
    }

    /**
     * @return HasMany<NotificationDelivery, $this>
     */
    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'recipient_user_id');
    }

    /**
     * @return HasMany<UserFeedback, $this>
     */
    public function submittedFeedback(): HasMany
    {
        return $this->hasMany(UserFeedback::class, 'submitted_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'sms_opt_in_at' => 'datetime',
            'role' => UserRole::class,
            'preferred_language' => Language::class,
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
