<?php

namespace App\Http\Controllers\Api\Device;

use App\Actions\ConfirmDeviceArrival;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Device\StoreArrivalRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ArrivalController extends Controller
{
    public function __invoke(StoreArrivalRequest $request, ConfirmDeviceArrival $confirmArrival): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $confirmation = $confirmArrival->handle($device, $request->validated());

        return response()->json([
            'message' => __('api.arrival.recorded'),
            'data' => [
                'id' => $confirmation->getKey(),
                'confirmed_at' => $confirmation->confirmed_at->toISOString(),
            ],
        ], $confirmation->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
