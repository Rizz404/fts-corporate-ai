<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Experience\Support\CompanySettings;
use App\Modules\Identity\CurrentActor;
use App\Modules\Identity\Presenters\AdminNavigation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentActor::class);
        $this->app->scoped(CompanySettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Entry to the /admin area (admin, editor, or staff). Each page still
        // authorizes its own resource through a Policy.
        Gate::define('admin.access', fn (User $user) => app(CurrentActor::class)->for($user)->canAccessAdminArea());

        // Admin pages receive their sidebar items from server-side abilities.
        View::composer('admin.*', function ($view) {
            $user = request()->user();

            $view->with('navItems', $user === null ? [] : AdminNavigation::items(
                app(CurrentActor::class)->for($user),
                request()->route()?->getName(),
            ));
        });
    }
}
