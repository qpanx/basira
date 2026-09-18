<?php

namespace App\Models;

use App\Enums\FamilyPermission;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class FamilyBlindUser extends Pivot
{
    public $incrementing = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'blind_user_id',
        'relationship',
        'permissions',
    ];

    public function allows(FamilyPermission $permission): bool
    {
        return (bool) data_get($this->permissions, $permission->value, false);
    }

    /**
     * @return array{can_view_location: bool, can_receive_alerts: bool, can_manage_safe_zones: bool}
     */
    public static function defaultPermissions(): array
    {
        return [
            FamilyPermission::ViewLocation->value => true,
            FamilyPermission::ReceiveAlerts->value => true,
            FamilyPermission::ManageSafeZones->value => false,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<BlindUser, $this>
     */
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
            'permissions' => 'array',
        ];
    }
}
