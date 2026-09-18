<?php

namespace App\Events;

use App\Models\Location;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Location $location) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('family.'.$this->location->blind_user_id)];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastWith(): array
    {
        return [
            'location' => [
                'id' => $this->location->getKey(),
                'device_id' => $this->location->device_id,
                'lat' => (float) $this->location->lat,
                'lng' => (float) $this->location->lng,
                'accuracy' => $this->location->accuracy === null ? null : (float) $this->location->accuracy,
                'recorded_at' => $this->location->recorded_at->toISOString(),
            ],
        ];
    }
}
