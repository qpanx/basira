<?php

namespace App\Http\Controllers\Api\Device;

use App\Actions\RecordDeviceHeartbeat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Device\HeartbeatRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class HeartbeatController extends Controller
{
    public function __invoke(HeartbeatRequest $request, RecordDeviceHeartbeat $recordHeartbeat): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $batteryLog = $recordHeartbeat->handle($device, $request->validated());

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
