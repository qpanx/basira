<?php

use App\Http\Middleware\EnsureDeviceToken;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel 11.56 loads its framework fallback database config before the
 * application config, and that fallback still references a PHP 8.5-deprecated
 * PDO constant. Suppress only that deprecation so JavaScript responses remain
 * valid while the application config uses the replacement Pdo\Mysql constant.
 */
if (PHP_VERSION_ID >= 80500) {
    error_reporting(error_reporting() & ~E_DEPRECATED);
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'device.token' => EnsureDeviceToken::class,
            'locale' => SetLocale::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $exception, Request $request): ?Response {
            if (! $request->is('api/*')) {
                return null;
            }

            $locale = $request->getPreferredLanguage(config('smart-cane.supported_locales', ['en']));
            if (is_string($locale) && in_array($locale, config('smart-cane.supported_locales', ['en']), true)) {
                App::setLocale($locale);
            }

            return response()->json([
                'message' => __('api.auth.device_token_required'),
                'code' => 'device_token_required',
            ], 401);
        });
    })->create();
