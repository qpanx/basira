<?php

namespace App\Notifications\Channels;

use App\Contracts\EmergencyGateway;
use App\Enums\DeliveryStatus;
use App\Models\NotificationDelivery;
use App\Notifications\Messages\EmergencyMessage;
use Illuminate\Notifications\Notification;
use Throwable;

class VoiceCallChannel
{
    public function send(object $notifiable, Notification $notification): ?NotificationDelivery
    {
        if (! method_exists($notification, 'toVoice')) {
            return null;
        }

        $destination = $notifiable->routeNotificationFor('voice', $notification);

        if (! is_string($destination) || $destination === '' || ! method_exists($notification, 'alert')) {
            return null;
        }

        $alert = $notification->alert();
        $gateway = app(EmergencyGateway::class);
        $delivery = NotificationDelivery::query()->firstOrCreate(
            [
                'alert_id' => $alert->getKey(),
                'recipient_user_id' => $notifiable->getKey(),
                'channel' => 'voice',
            ],
            [
                'notification_id' => $notification->id,
                'provider' => $gateway->name(),
                'status' => DeliveryStatus::Queued,
                'destination_last_four' => substr($destination, -4),
                'queued_at' => now(),
            ],
        );

        if ($delivery->status === DeliveryStatus::Delivered) {
            return $delivery;
        }

        $delivery->increment('attempt_count');

        try {
            $message = $notification->toVoice($notifiable);
            $message = $message instanceof EmergencyMessage ? $message : new EmergencyMessage((string) $message);
            $result = $gateway->placeVoiceCall($destination, $message);

            $delivery->forceFill([
                'provider' => $gateway->name(),
                'provider_reference' => $result->providerReference,
                'status' => $result->status,
                'accepted_at' => in_array($result->status, [DeliveryStatus::Accepted, DeliveryStatus::Delivered], true) ? now() : null,
                'delivered_at' => $result->status === DeliveryStatus::Delivered ? now() : null,
                'failed_at' => $result->status === DeliveryStatus::Failed ? now() : null,
                'failure_code' => $result->failureCode,
                'failure_message' => $result->failureMessage,
            ])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => DeliveryStatus::Failed,
                'failed_at' => now(),
                'failure_code' => 'channel_exception',
                'failure_message' => $exception->getMessage(),
            ])->save();
        }

        return $delivery->fresh();
    }
}
