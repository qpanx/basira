<?php

namespace App\Actions;

use App\Enums\DeviceStatus;
use App\Enums\PowerState;
use App\Models\BatteryLog;
use App\Models\Device;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordDeviceHeartbeat
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Device $device, array $data, bool $fromCloud = false): BatteryLog
    {
        return DB::transaction(function () use ($device, $data, $fromCloud): BatteryLog {
            $lockedDevice = Device::query()->whereKey($device->getKey())->lockForUpdate()->firstOrFail();
            $recordedAt = isset($data['recorded_at']) ? Carbon::parse($data['recorded_at'])->utc() : now();
            $messageId = $data['message_id'] ?? (string) Str::uuid();

            $batteryLog = $lockedDevice->batteryLogs()->firstOrCreate(
                ['message_id' => $messageId],
                [
                    'blind_user_id' => $lockedDevice->blind_user_id,
                    'battery_level' => $data['battery_level'],
                    'voltage' => $data['voltage'] ?? null,
                    'power_state' => $data['power_state'] ?? PowerState::Unknown->value,
                    'recorded_at' => $recordedAt,
                    'received_at' => now(),
                ],
            );

            if ($fromCloud) {
                if ($batteryLog->wasRecentlyCreated) {
                    $latestBatteryLog = $lockedDevice->batteryLogs()->orderByDesc('recorded_at')->orderByDesc('id')->first();
                    $hasNewerBattery = $latestBatteryLog !== null && $latestBatteryLog->recorded_at->greaterThan($recordedAt);

                    if (! $hasNewerBattery) {
                        $lockedDevice->update(['battery_level' => $data['battery_level']]);
                    }

                    $lockedDevice->recordCloudActivity($recordedAt);
                }
            } else {
                $lockedDevice->update([
                    'battery_level' => $data['battery_level'],
                    'firmware_version' => $data['firmware_version'],
                    'last_seen_at' => now(),
                    'status' => $lockedDevice->status === DeviceStatus::Maintenance
                        ? DeviceStatus::Maintenance
                        : DeviceStatus::Active,
                ]);
            }

            $device->setRawAttributes($lockedDevice->getAttributes(), true)->syncChanges();

            return $batteryLog;
        });
    }
}
