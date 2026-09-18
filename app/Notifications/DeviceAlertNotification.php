<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeviceAlertNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Alert $alert) {}

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
            'alert_id' => $this->alert->getKey(),
            'device_id' => $this->alert->device_id,
            'blind_user_id' => $this->alert->blind_user_id,
            'type' => $this->alert->type->value,
            'severity' => $this->alert->severity->value,
            'distance_cm' => $this->alert->distance_cm,
            'lat' => $this->alert->lat === null ? null : (float) $this->alert->lat,
            'lng' => $this->alert->lng === null ? null : (float) $this->alert->lng,
            'created_at' => $this->alert->created_at?->toISOString(),
        ];
    }
}
