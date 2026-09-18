<?php

namespace App\Actions;

use App\Enums\AlertType;
use App\Events\DeviceAlertTriggered;
use App\Events\DeviceSOSTriggered;
use App\Jobs\SendSosNotifications;
use App\Models\Alert;
use App\Models\Device;
use App\Notifications\DeviceAlertNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class TriggerDeviceAlert
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Device $device, array $data): Alert
    {
        $alert = DB::transaction(function () use ($device, $data): Alert {
            $lockedDevice = Device::query()->lockForUpdate()->findOrFail($device->getKey());
            $messageId = $data['message_id'] ?? (string) Str::uuid();
            $existingAlert = $lockedDevice->alerts()->where('message_id', $messageId)->first();

            if ($existingAlert !== null) {
                return $existingAlert;
            }

            $alert = $lockedDevice->alerts()->create([
                'blind_user_id' => $lockedDevice->blind_user_id,
                'message_id' => $messageId,
                'type' => $data['type'],
                'severity' => $data['severity'],
                'distance_cm' => $data['distance_cm'] ?? null,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'occurred_at' => isset($data['timestamp']) ? Carbon::parse($data['timestamp']) : now(),
            ]);

            $lockedDevice->update(['last_seen_at' => now()]);

            return $alert;
        });

        if (! $alert->wasRecentlyCreated) {
            return $alert;
        }

        event(new DeviceAlertTriggered($alert));

        if ($alert->type === AlertType::Sos) {
            event(new DeviceSOSTriggered($alert));
            SendSosNotifications::dispatch($alert)->onQueue('sos');
        } else {
            Notification::send(
                $alert->blindUser->familyMembersWhoReceiveAlerts()->get(),
                new DeviceAlertNotification($alert),
            );
        }

        return $alert;
    }
}
