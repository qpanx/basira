<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $device->load(['blindUser.safeZones' => fn ($query) => $query->where('is_active', true)->orderBy('name')]);

        $etag = sprintf('"device-%d-config-%d"', $device->getKey(), $device->configuration_version);

        if ($request->header('If-None-Match') === $etag) {
            return response()->json(status: 304)->withHeaders(['ETag' => $etag]);
        }

        return response()->json([
            'message' => __('api.config.loaded'),
            'data' => [
                'configuration_version' => $device->configuration_version,
                'preferred_language' => $device->preferred_language?->value ?? config('app.fallback_locale', 'en'),
                'settings' => $device->settings ?? [],
                'safe_zones' => $device->blindUser->safeZones->map(fn ($zone): array => [
                    'id' => $zone->getKey(),
                    'name' => $zone->name,
                    'center_lat' => (float) $zone->center_lat,
                    'center_lng' => (float) $zone->center_lng,
                    'radius_meters' => $zone->radius_meters,
                ])->values(),
            ],
            'meta' => ['server_time' => now()->toISOString()],
        ])->withHeaders(['ETag' => $etag]);
    }
}
