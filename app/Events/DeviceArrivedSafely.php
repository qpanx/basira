<?php

namespace App\Events;

use App\Models\ArrivalConfirmation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceArrivedSafely implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ArrivalConfirmation $confirmation) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('family.'.$this->confirmation->blind_user_id)];
    }

    public function broadcastAs(): string
    {
        return 'device.arrived-safely';
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastWith(): array
    {
        return [
            'confirmation' => [
                'id' => $this->confirmation->getKey(),
                'device_id' => $this->confirmation->device_id,
                'lat' => $this->confirmation->lat === null ? null : (float) $this->confirmation->lat,
                'lng' => $this->confirmation->lng === null ? null : (float) $this->confirmation->lng,
                'confirmed_at' => $this->confirmation->confirmed_at->toISOString(),
            ],
        ];
    }
}
