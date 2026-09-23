<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/basira.svg" type="image/svg+xml">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ __('landing.meta.title') }}</title>
            <meta name="description" content="{{ __('landing.meta.description') }}" data-inertia="description">
            <meta property="og:title" content="{{ __('landing.meta.title') }}" data-inertia="og:title">
            <meta property="og:description" content="{{ __('landing.meta.description') }}" data-inertia="og:description">
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
