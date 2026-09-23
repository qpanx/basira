<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::post('/locale', LocaleController::class)->name('locale.update');
Route::get('/locale/{locale}', function (string $locale): RedirectResponse {
    abort_unless(in_array($locale, config('smart-cane.supported_locales', ['en']), true), 404);

    session()->put('locale', $locale);

    return redirect()->back();
})->name('locale.switch');

Route::get('/', function (): Response {
    return Inertia::render('welcome', [
        'copy' => __('landing'),
        'locale' => app()->getLocale(),
        'authenticated' => auth()->check(),
        'year' => now()->year,
    ]);
})->name('home');
