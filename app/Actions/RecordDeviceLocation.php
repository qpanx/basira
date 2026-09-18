<?php

namespace App\Actions;

use App\Events\LocationUpdated;
use App\Events\SafeZoneEntered;
use App\Events\SafeZoneExited;
use App\Models\Device;
use App\Models\Location;
use App\Notifications\SafeZoneTransitionNotification;
use App\Services\SafeZoneMonitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class RecordDeviceLocation
{
    public function __construct(private SafeZoneMonitor $safeZoneMonitor) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Device $device, array $data): Location
    {
        $result = DB::transaction(function () use ($device, $data): array {
            $lockedDevice = Device::query()->lockForUpdate()->findOrFail($device->getKey());
            $messageId = $data['message_id'] ?? (string) Str::uuid();
            $existingLocation = $lockedDevice->locations()->where('message_id', $messageId)->first();

            if ($existingLocation !== null) {
                return ['location' => $existingLocation, 'is_newest' => false, 'transitions' => ['entered' => [], 'exited' => []]];
            }

            $recordedAt = Carbon::parse($data['timestamp']);
            $location = $lockedDevice->locations()->create([
                'blind_user_id' => $lockedDevice->blind_user_id,
                'message_id' => $messageId,
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'accuracy' => $data['accuracy'] ?? null,
                'recorded_at' => $recordedAt,
                'received_at' => now(),
            ]);
            $isNewest = $lockedDevice->last_location_recorded_at === null
                || $recordedAt->greaterThan($lockedDevice->last_location_recorded_at);

            $lockedDevice->update([
                'last_seen_at' => now(),
                ...($isNewest ? [
                    'last_location_recorded_at' => $recordedAt,
                    'geofence_evaluated_at' => $recordedAt,
                ] : []),
            ]);

            return [
                'location' => $location,
                'is_newest' => $isNewest,
                'transitions' => $isNewest
                    ? $this->safeZoneMonitor->evaluate($lockedDevice, $location)
                    : ['entered' => [], 'exited' => []],
            ];
        });

        /** @var Location $location */
        $location = $result['location'];

        if (! $result['is_newest']) {
            return $location;
        }

        event(new LocationUpdated($location));
        $recipients = $location->blindUser->familyMembersWhoReceiveAlerts()->get();

        foreach ($result['transitions']['entered'] as $safeZone) {
            event(new SafeZoneEntered($location, $safeZone));
            Notification::send($recipients, new SafeZoneTransitionNotification($location, $safeZone, true));
        }

        foreach ($result['transitions']['exited'] as $safeZone) {
            event(new SafeZoneExited($location, $safeZone));
            Notification::send($recipients, new SafeZoneTransitionNotification($location, $safeZone, false));
        }

        return $location;
    }
}
