<?php

use App\Http\Controllers\Api\Device\AlertController;
use App\Http\Controllers\Api\Device\ArrivalController;
use App\Http\Controllers\Api\Device\ConfigurationController;
use App\Http\Controllers\Api\Device\HeartbeatController;
use App\Http\Controllers\Api\Device\LocationController;
use App\Http\Controllers\Webhooks\TwilioStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('device')
    ->middleware(['auth:sanctum', 'locale'])
    ->group(function (): void {
        Route::post('heartbeat', HeartbeatController::class)
            ->middleware(['device.token:device:heartbeat', 'throttle:device-heartbeat'])
            ->name('api.device.heartbeat');
        Route::post('location', LocationController::class)
            ->middleware(['device.token:device:location', 'throttle:device-location'])
            ->name('api.device.location');
        Route::post('alert', AlertController::class)
            ->middleware(['device.token:device:alert', 'throttle:device-alert'])
            ->name('api.device.alert');
        Route::post('arrived', ArrivalController::class)
            ->middleware(['device.token:device:arrive', 'throttle:device-arrival'])
            ->name('api.device.arrived');
        Route::get('config', ConfigurationController::class)
            ->middleware(['device.token:device:config', 'throttle:device-config'])
            ->name('api.device.config');
    });

Route::post('webhooks/twilio/status', TwilioStatusController::class)
    ->middleware('throttle:60,1')
    ->name('api.webhooks.twilio.status');
