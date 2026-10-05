<?php

use App\Http\Controllers\LandingController;
use App\Http\Middleware\EnsureDevPreviewEnabled;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

// JSON endpoints share the web group (session + CSRF), see TECHNICAL_CONTRACTS.md.
Route::prefix('api/v1')->name('api.')->group(base_path('routes/internal.php'));

if (app()->environment(EnsureDevPreviewEnabled::ENVIRONMENTS)) {
    Route::prefix('dev')->name('dev.')->middleware(EnsureDevPreviewEnabled::class)->group(base_path('routes/dev.php'));
}
