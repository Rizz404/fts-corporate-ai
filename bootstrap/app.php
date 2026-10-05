<?php

use App\Http\Middleware\ApplyCompanySettings;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\SetLocale;
use App\Support\Http\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->web(append: [SetLocale::class, ApplyCompanySettings::class]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('office.reception'));

        // Signed-in, active account (password change page and logout use this).
        $middleware->group('account.active', [
            'auth',
            AuthenticateSession::class,
            EnsureAccountIsActive::class,
        ]);

        // Every business page and API: active account with its own password.
        $middleware->group('account', [
            'auth',
            AuthenticateSession::class,
            EnsureAccountIsActive::class,
            EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (ApiExceptionRenderer::shouldRender($request)) {
                return ApiExceptionRenderer::render($e);
            }
        });
    })->create();
