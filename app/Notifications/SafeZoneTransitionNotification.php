<?php

namespace App\Notifications;

use App\Models\Location;
use App\Models\SafeZone;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SafeZoneTransitionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Location $location,
        public readonly SafeZone $safeZone,
        public readonly bool $entered,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toBroadcast(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'blind_user_id' => $this->location->blind_user_id,
            'safe_zone_id' => $this->safeZone->getKey(),
            'safe_zone_name' => $this->safeZone->name,
            'transition' => $this->entered ? 'entered' : 'exited',
            'location_id' => $this->location->getKey(),
            'recorded_at' => $this->location->recorded_at->toISOString(),
        ];
    }
}
