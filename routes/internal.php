<?php

use App\Http\Controllers\Api\PreferenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal JSON API (/api/v1)
|--------------------------------------------------------------------------
|
| Loaded inside the web middleware group by routes/web.php, so endpoints use
| the session and CSRF protection. Responses use App\Support\Http\ApiResponse.
| Business endpoints are added by the plan that owns them.
|
*/

Route::middleware('account')->group(function () {
    Route::post('preferences', [PreferenceController::class, 'update'])->name('preferences.update');
});
