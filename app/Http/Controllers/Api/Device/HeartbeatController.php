<?php

namespace App\Http\Controllers\Api\Device;

use App\Actions\RecordDeviceHeartbeat;
use App\Actions\RecordDeviceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Device\HeartbeatRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class HeartbeatController extends Controller
{
    public function __invoke(HeartbeatRequest $request, RecordDeviceHeartbeat $recordHeartbeat, RecordDeviceStatus $recordStatus): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        if ($request->isStatusPayload()) {
            $recordStatus->handle($device, [
                'timestamp' => (string) $request->validated('timestamp'),
                'online' => (bool) $request->validated('online'),
            ]);

            return response()->json([
                'message' => __('api.heartbeat.recorded'),
                'data' => [
                    'status' => $device->status->value,
                    'recorded_at' => $device->last_status_recorded_at?->toISOString(),
                ],
                'meta' => ['server_time' => now()->toISOString()],
            ]);
        }

        $batteryLog = $recordHeartbeat->handle($device, $request->ingestionData(), $request->isCloudPayload());

        return response()->json([
            'message' => __('api.heartbeat.recorded'),
            'data' => [
                'battery_log_id' => $batteryLog->getKey(),
                'battery_level' => $device->battery_level,
                'status' => $device->status->value,
                'configuration_version' => $device->configuration_version,
            ],
            'meta' => ['server_time' => now()->toISOString()],
        ]);
    }
}
