<?php

namespace App\Models;

use App\Enums\FamilyPermission;
use App\Enums\NavigationStatus;
use Carbon\CarbonInterface;
use Database\Factories\NavigationSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property NavigationStatus $status
 * @property int $last_location_id
 * @property array{place_id: string, name: string, address: string, latitude: float, longitude: float} $destination
 * @property list<array{instruction: string, distance_meters: int, end_latitude: float, end_longitude: float}> $steps
 * @property list<array{0: float, 1: float}> $route_coordinates
 * @property list<string> $warnings
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $cancelled_at
 * @property CarbonInterface|null $last_reroute_at
 */
class NavigationSession extends Model
{
    /** @use HasFactory<NavigationSessionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'routing_provider',
        'device_id', 'blind_user_id', 'origin_location_id', 'last_location_id',
        'destination_place_id', 'destination', 'language', 'status', 'distance_meters',
        'estimated_duration_seconds', 'steps', 'route_coordinates', 'warnings',
        'current_step_index', 'off_route', 'started_at', 'completed_at', 'cancelled_at', 'last_reroute_at',
    ];

    /**
     * @param  Builder<NavigationSession>  $query
     * @return Builder<NavigationSession>
     */
    public function scopeAccessibleTo(Builder $query, User $actor): Builder
    {
        return $query->whereIn('blind_user_id', BlindUser::query()->accessibleTo($actor, FamilyPermission::ViewLocation)->select('id'));
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

    /** @return BelongsTo<Location, $this> */
    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function lastLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'last_location_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'destination' => 'array', 'steps' => 'array', 'route_coordinates' => 'array', 'warnings' => 'array',
            'status' => NavigationStatus::class, 'current_step_index' => 'integer', 'off_route' => 'boolean',
            'distance_meters' => 'integer', 'estimated_duration_seconds' => 'integer',
            'started_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime', 'last_reroute_at' => 'datetime',
        ];
    }
}
