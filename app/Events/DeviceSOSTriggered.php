<?php

namespace App\Events;

use App\Models\Alert;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceSOSTriggered implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Alert $alert) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('family.'.$this->alert->blind_user_id)];
    }

    public function broadcastAs(): string
    {
        return 'device-sos.triggered';
    }

    public function broadcastWith(): array
    {
        return [
            'alert' => [
                'id' => $this->alert->getKey(),
                'device_id' => $this->alert->device_id,
                'severity' => $this->alert->severity->value,
                'lat' => $this->alert->lat === null ? null : (float) $this->alert->lat,
                'lng' => $this->alert->lng === null ? null : (float) $this->alert->lng,
                'created_at' => $this->alert->created_at->toISOString(),
            ],
        ];
    }
}
