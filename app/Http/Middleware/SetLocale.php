<?php

namespace App\Http\Middleware;

use App\Support\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active locale: valid ?lang= → session → default.
 *
 * Invalid ?lang= is a 422 on JSON endpoints and silently ignored on pages.
 * A valid choice by a signed-in user is also saved as preferred_locale,
 * which seeds the session locale at the next login.
 */
class SetLocale
{
    public const SESSION_KEY = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('corporate.locales');
        $requested = $request->query('lang');
        $session = $request->hasSession() ? $request->session() : null;

        if ($requested !== null && ! in_array($requested, $supported, true)) {
            if ($request->is('api/*') || $request->expectsJson()) {
                app()->setLocale($this->fallback($session?->get(self::SESSION_KEY), $supported));

                return ApiResponse::error('invalid_locale', __('ui.errors.invalid_locale'), 422, [
                    'lang' => [__('ui.errors.invalid_locale')],
                ]);
            }

            $requested = null;
        }

        if ($requested !== null) {
            $session?->put(self::SESSION_KEY, $requested);
            $locale = $requested;

            $user = $request->user();
            if ($user !== null && $user->preferred_locale !== $requested) {
                $user->forceFill(['preferred_locale' => $requested])->saveQuietly();
            }
        } else {
            $locale = $this->fallback($session?->get(self::SESSION_KEY), $supported);
        }

        app()->setLocale($locale);

        return $next($request);
    }

    /**
     * @param  list<string>  $supported
     */
    private function fallback(mixed $sessionLocale, array $supported): string
    {
        return in_array($sessionLocale, $supported, true)
            ? $sessionLocale
            : config('corporate.default_locale');
    }
}
