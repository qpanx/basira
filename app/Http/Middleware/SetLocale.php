<?php

namespace App\Http\Middleware;

use BackedEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = config('smart-cane.supported_locales', ['en']);
        $authenticatedLocale = $request->user()?->preferred_language;

        if ($authenticatedLocale instanceof BackedEnum) {
            $authenticatedLocale = $authenticatedLocale->value;
        }

        $requestedLocale = $request->hasHeader('Accept-Language')
            ? $request->getPreferredLanguage($supportedLocales)
            : null;
        $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;
        $candidates = $request->is('api/*')
            ? [$requestedLocale, $authenticatedLocale, $sessionLocale, config('app.fallback_locale')]
            : [$sessionLocale, $authenticatedLocale, $requestedLocale, config('app.fallback_locale')];
        $locale = collect($candidates)
            ->first(fn (mixed $candidate): bool => is_string($candidate) && in_array($candidate, $supportedLocales, true));

        App::setLocale($locale ?? 'en');

        $response = $next($request);

        $response->headers->set('Content-Language', App::getLocale());

        return $response;
    }
}
