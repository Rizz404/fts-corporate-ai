<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Assistant\Gateway\CompatibleChatCompletionsGateway;
use App\Modules\Assistant\Gateway\ModelGateway;
use App\Modules\Conversation\Listeners\CancelTurnsWhenSessionsRevoked;
use App\Modules\Experience\Support\CompanySettings;
use App\Modules\Identity\CurrentActor;
use App\Modules\Identity\Events\UserSessionsRevoked;
use App\Modules\Identity\Presenters\AdminNavigation;
use App\Modules\Support\Support\TicketAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // Tests and local development may swap in FakeModelGateway.
        $this->app->bind(ModelGateway::class, CompatibleChatCompletionsGateway::class);
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

        // AI turns: 10/min per user with 60/min per IP as a backstop.
        RateLimiter::for('assistant', fn (Request $request) => [
            Limit::perMinute(10)->by('assistant:user:'.$request->user()?->id),
            Limit::perMinute(60)->by('assistant:ip:'.$request->ip()),
        ]);

        // Ticket writes: 20/min per user.
        RateLimiter::for('ticket-writes', fn (Request $request) => Limit::perMinute(20)->by('tickets:user:'.$request->user()?->id));

        // /admin/tickets: current staff membership of an active team; the admin role alone is not enough.
        Gate::define('tickets.staff', fn (User $user) => TicketAccess::handledTeamIds(app(CurrentActor::class)->for($user)) !== []);

        Event::listen(UserSessionsRevoked::class, CancelTurnsWhenSessionsRevoked::class);
    }
}
