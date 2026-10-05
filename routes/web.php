<?php

use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OfficeController;
use App\Http\Middleware\EnsureDevPreviewEnabled;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('account.active')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('password/change', [PasswordController::class, 'edit'])->name('password.change');
    Route::post('password/change', [PasswordController::class, 'update'])->name('password.update');
});

Route::middleware('account')->group(function () {
    Route::prefix('office')->name('office.')->group(function () {
        Route::get('/', [OfficeController::class, 'reception'])->name('reception');
        Route::get('knowledge', [OfficeController::class, 'knowledge'])->name('knowledge');
        Route::get('hr', [OfficeController::class, 'hr'])->name('hr');
        Route::get('it', [OfficeController::class, 'it'])->name('it');
        Route::get('requests', [OfficeController::class, 'requests'])->name('requests');
    });

    Route::prefix('admin')->name('admin.')->middleware('can:admin.access')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::post('users/{user}/reset-password', [UserPasswordController::class, 'store'])->name('users.reset-password');
        Route::resource('departments', DepartmentController::class)->except(['show', 'destroy']);
        Route::get('settings', [CompanySettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('settings', [CompanySettingsController::class, 'update'])->name('settings.update');
    });
});

// JSON endpoints share the web group (session + CSRF), see TECHNICAL_CONTRACTS.md.
Route::prefix('api/v1')->name('api.')->group(base_path('routes/internal.php'));

if (app()->environment(EnsureDevPreviewEnabled::ENVIRONMENTS)) {
    Route::prefix('dev')->name('dev.')->middleware(EnsureDevPreviewEnabled::class)->group(base_path('routes/dev.php'));
}
