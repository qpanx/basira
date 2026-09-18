<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\VoiceCallChannel;
use App\Notifications\Messages\EmergencyMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class SosAlertNotification extends Notification
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
        $channels = ['database', 'broadcast', SmsChannel::class];

        if (config('smart-cane.emergency.voice_calls_enabled', true)) {
            $channels[] = VoiceCallChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toBroadcast(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    public function toSms(object $notifiable): EmergencyMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->preferredLocale() : config('app.fallback_locale', 'en');

        return new EmergencyMessage(Lang::get('api.notifications.sos_message', [
            'name' => $this->alert->blindUser->name,
        ], $locale));
    }

    public function toVoice(object $notifiable): EmergencyMessage
    {
        return $this->toSms($notifiable);
    }

    public function alert(): Alert
    {
        return $this->alert;
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
            'blind_user_id' => $this->alert->blind_user_id,
            'device_id' => $this->alert->device_id,
            'type' => $this->alert->type->value,
            'severity' => $this->alert->severity->value,
            'lat' => $this->alert->lat === null ? null : (float) $this->alert->lat,
            'lng' => $this->alert->lng === null ? null : (float) $this->alert->lng,
            'created_at' => $this->alert->created_at?->toISOString(),
        ];
    }
}
