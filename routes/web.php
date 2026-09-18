<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::post('/locale', LocaleController::class)->name('locale.update');
Route::get('/locale/{locale}', function (string $locale): RedirectResponse {
    abort_unless(in_array($locale, config('smart-cane.supported_locales', ['en']), true), 404);

    session()->put('locale', $locale);

    return redirect()->back();
})->name('locale.switch');

Route::inertia('/', 'welcome')->name('home');
