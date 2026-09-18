<?php

namespace App\Models;

use Database\Factories\DeviceSafeZoneStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSafeZoneState extends Model
{
    /** @use HasFactory<DeviceSafeZoneStateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'safe_zone_id',
        'last_location_id',
        'is_inside',
        'evaluated_at',
        'entered_at',
        'exited_at',
    ];

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return BelongsTo<SafeZone, $this> */
    public function safeZone(): BelongsTo
    {
        return $this->belongsTo(SafeZone::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function lastLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'last_location_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_inside' => 'boolean',
            'evaluated_at' => 'datetime',
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }
}
