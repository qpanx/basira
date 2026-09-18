<?php

namespace App\Events;

use App\Models\Location;
use App\Models\SafeZone;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SafeZoneEntered implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Location $location, public SafeZone $safeZone) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('family.'.$this->location->blind_user_id)];
    }

    public function broadcastAs(): string
    {
        return 'safe-zone.entered';
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastWith(): array
    {
        return [
            'safe_zone' => ['id' => $this->safeZone->getKey(), 'name' => $this->safeZone->name],
            'location_id' => $this->location->getKey(),
            'recorded_at' => $this->location->recorded_at->toISOString(),
        ];
    }
}
