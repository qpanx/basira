<?php

namespace App\Http\Controllers\Api\Device;

use App\Actions\RecordDeviceLocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Device\StoreLocationRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LocationController extends Controller
{
    public function __invoke(StoreLocationRequest $request, RecordDeviceLocation $recordLocation): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $location = $recordLocation->handle($device, $request->validated());

        return response()->json([
            'message' => __('api.location.recorded'),
            'data' => [
                'id' => $location->getKey(),
                'recorded_at' => $location->recorded_at->toISOString(),
            ],
        ], $location->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
