<?php

namespace App\Providers;

use App\Contracts\DestinationProvider;
use App\Contracts\EmergencyGateway;
use App\Contracts\OCRProvider;
use App\Contracts\RoutingProvider;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\HttpOCRProvider;
use App\Services\LogEmergencyGateway;
use App\Services\NavigationProviders;
use App\Services\TwilioEmergencyGateway;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RoutingProvider::class, fn (Application $app): RoutingProvider => $app->make(NavigationProviders::class)->routing());
        $this->app->bind(DestinationProvider::class, fn (Application $app): DestinationProvider => $app->make(NavigationProviders::class)->destinations());
        $this->app->bind(OCRProvider::class, HttpOCRProvider::class);
        $this->app->bind(EmergencyGateway::class, function (Application $app): EmergencyGateway {
            return config('smart-cane.emergency.driver') === 'twilio'
                ? $app->make(TwilioEmergencyGateway::class)
                : $app->make(LogEmergencyGateway::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureDeviceRateLimits();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->role === UserRole::SuperAdmin ? true : null;
        });
    }

    protected function configureDeviceRateLimits(): void
    {
        foreach (['vision', 'ocr', 'navigation-search', 'navigation-start', 'navigation-update', 'navigation-read'] as $bucket) {
            RateLimiter::for('device-'.$bucket, fn (Request $request): Limit => $this->deviceLimit($request, $bucket));
        }
        RateLimiter::for('device-heartbeat', fn (Request $request): Limit => $this->deviceLimit($request, 'heartbeat'));
        RateLimiter::for('device-location', fn (Request $request): Limit => $this->deviceLimit($request, 'location'));
        RateLimiter::for('device-arrival', fn (Request $request): Limit => $this->deviceLimit($request, 'arrival'));
        RateLimiter::for('device-config', fn (Request $request): Limit => $this->deviceLimit($request, 'config'));
        RateLimiter::for('device-alert', function (Request $request): Limit {
            $bucket = $request->string('type')->toString() === 'sos' ? 'sos' : 'alert';

            return $this->deviceLimit($request, $bucket);
        });
    }

    protected function deviceLimit(Request $request, string $bucket): Limit
    {
        $principal = $request->user();
        $identifier = $principal?->getKey() ?? $request->ip();
        $attempts = (int) config("smart-cane.rate_limits.{$bucket}", 30);

        return Limit::perMinute($attempts)->by("{$bucket}:{$identifier}");
    }
}
