<?php

use App\Http\Controllers\Api\Device\AlertController;
use App\Http\Controllers\Api\Device\ArrivalController;
use App\Http\Controllers\Api\Device\ConfigurationController;
use App\Http\Controllers\Api\Device\HeartbeatController;
use App\Http\Controllers\Api\Device\LocationController;
use App\Http\Controllers\Api\Device\NavigationController;
use App\Http\Controllers\Api\Device\NavigationSearchController;
use App\Http\Controllers\Api\Device\OCRController;
use App\Http\Controllers\Api\Device\VisionController;
use App\Http\Controllers\Webhooks\TwilioStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('device')
    ->middleware(['auth:sanctum', 'locale'])
    ->group(function (): void {
        Route::post('vision/detect', VisionController::class)
            ->middleware(['device.token:device:vision', 'throttle:device-vision'])
            ->name('api.device.vision.detect');
        Route::post('ocr/read', OCRController::class)
            ->middleware(['device.token:device:ocr', 'throttle:device-ocr'])
            ->name('api.device.ocr.read');
        Route::prefix('navigation')->middleware('device.token:device:navigation')->group(function (): void {
            Route::get('search', NavigationSearchController::class)
                ->middleware('throttle:device-navigation-search')->name('api.device.navigation.search');
            Route::post('start', [NavigationController::class, 'store'])
                ->middleware('throttle:device-navigation-start')->name('api.device.navigation.start');
            Route::get('active', [NavigationController::class, 'show'])
                ->middleware('throttle:device-navigation-read')->name('api.device.navigation.active');
            Route::post('update', [NavigationController::class, 'update'])
                ->middleware('throttle:device-navigation-update')->name('api.device.navigation.update');
            Route::post('stop', [NavigationController::class, 'destroy'])
                ->middleware('throttle:device-navigation-read')->name('api.device.navigation.stop');
        });
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
