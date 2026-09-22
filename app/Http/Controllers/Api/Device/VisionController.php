<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Device\ReadImageRequest;
use App\Models\Device;
use App\Services\VisionService;
use Illuminate\Http\JsonResponse;

class VisionController extends Controller
{
    public function __invoke(ReadImageRequest $request, VisionService $vision): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        return response()->json([
            'success' => true,
            'data' => $vision->detect($device, $request->file('image'), $request->validated('language', app()->getLocale())),
            'meta' => ['safety_notice' => __('assistive.vision_notice')],
        ]);
    }
}
