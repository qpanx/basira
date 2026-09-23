<?php

namespace App\Http\Controllers\Api\Device;

use App\Actions\TriggerDeviceAlert;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Device\StoreAlertRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AlertController extends Controller
{
    public function __invoke(StoreAlertRequest $request, TriggerDeviceAlert $triggerAlert): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $alert = $triggerAlert->handle($device, $request->ingestionData(), $request->isCloudPayload());

        return response()->json([
            'message' => __('api.alert.recorded'),
            'data' => [
                'id' => $alert->getKey(),
                'type' => $alert->type->value,
                'severity' => $alert->severity->value,
                'created_at' => $alert->created_at->toISOString(),
            ],
        ], $alert->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
