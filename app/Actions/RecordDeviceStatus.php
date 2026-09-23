<?php

namespace App\Actions;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordDeviceStatus
{
    /** @param array{timestamp: string, online: bool} $data */
    public function handle(Device $device, array $data): void
    {
        DB::transaction(function () use ($device, $data): void {
            $lockedDevice = Device::query()->whereKey($device->getKey())->lockForUpdate()->firstOrFail();
            $recordedAt = Carbon::parse($data['timestamp'])->utc();

            if ($lockedDevice->last_status_recorded_at !== null
                && $recordedAt->lessThanOrEqualTo($lockedDevice->last_status_recorded_at)) {
                $device->setRawAttributes($lockedDevice->getAttributes(), true);

                return;
            }

            $isNewerThanActivity = $lockedDevice->last_seen_at === null
                || $recordedAt->greaterThan($lockedDevice->last_seen_at);
            $isFresh = $recordedAt->greaterThanOrEqualTo(now()->subMinutes((int) config('smart-cane.offline_after_minutes', 10)));

            $lockedDevice->fill([
                'last_status_recorded_at' => $recordedAt,
                'last_status_received_at' => now(),
                'last_status_online' => (bool) $data['online'],
            ]);

            if ($isNewerThanActivity && (! $data['online'] || $isFresh)) {
                if ($lockedDevice->status !== DeviceStatus::Maintenance) {
                    $lockedDevice->status = $data['online'] ? DeviceStatus::Active : DeviceStatus::Offline;
                }

                if ($data['online']) {
                    $lockedDevice->last_seen_at = now();
                }
            }

            $lockedDevice->save();
            $device->setRawAttributes($lockedDevice->getAttributes(), true)->syncChanges();
        });
    }
}
