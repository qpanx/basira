<?php

namespace App\Notifications;

use App\Models\ArrivalConfirmation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ArrivedSafelyNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly ArrivalConfirmation $confirmation) {}

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
            'blind_user_id' => $this->confirmation->blind_user_id,
            'device_id' => $this->confirmation->device_id,
            'confirmation_id' => $this->confirmation->getKey(),
            'lat' => $this->confirmation->lat === null ? null : (float) $this->confirmation->lat,
            'lng' => $this->confirmation->lng === null ? null : (float) $this->confirmation->lng,
            'confirmed_at' => $this->confirmation->confirmed_at->toISOString(),
        ];
    }
}
