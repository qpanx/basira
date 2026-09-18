<?php

namespace App\Actions;

use App\Events\DeviceArrivedSafely;
use App\Models\ArrivalConfirmation;
use App\Models\Device;
use App\Notifications\ArrivedSafelyNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ConfirmDeviceArrival
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Device $device, array $data): ArrivalConfirmation
    {
        $confirmation = DB::transaction(function () use ($device, $data): ArrivalConfirmation {
            $lockedDevice = Device::query()->lockForUpdate()->findOrFail($device->getKey());
            $messageId = $data['message_id'] ?? (string) Str::uuid();
            $existingConfirmation = $lockedDevice->arrivalConfirmations()->where('message_id', $messageId)->first();

            if ($existingConfirmation !== null) {
                return $existingConfirmation;
            }

            $confirmation = $lockedDevice->arrivalConfirmations()->create([
                'blind_user_id' => $lockedDevice->blind_user_id,
                'message_id' => $messageId,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'confirmed_at' => isset($data['timestamp']) ? Carbon::parse($data['timestamp']) : now(),
                'received_at' => now(),
            ]);

            $lockedDevice->update(['last_seen_at' => now()]);

            return $confirmation;
        });

        if (! $confirmation->wasRecentlyCreated) {
            return $confirmation;
        }

        event(new DeviceArrivedSafely($confirmation));
        Notification::send(
            $confirmation->blindUser->familyMembersWhoReceiveAlerts()->get(),
            new ArrivedSafelyNotification($confirmation),
        );

        return $confirmation;
    }
}
