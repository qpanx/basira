<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $device = $request->user();

        if (! $device instanceof Device || ! $device->currentAccessToken() instanceof PersonalAccessToken) {
            return response()->json([
                'success' => false,
                'message' => __('api.auth.device_token_required'),
                'code' => 'device_token_required',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $device->is_enabled) {
            return response()->json([
                'success' => false,
                'message' => __('api.auth.device_disabled'),
                'code' => 'device_disabled',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($device->blind_user_id === null) {
            return response()->json([
                'success' => false,
                'message' => __('api.auth.device_unassigned'),
                'code' => 'device_unassigned',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($device->tokenCant($ability)) {
            return response()->json([
                'success' => false,
                'message' => __('api.auth.missing_ability'),
                'code' => 'missing_token_ability',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
