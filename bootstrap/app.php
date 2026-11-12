<?php

use App\Http\Middleware\SecurityHeaders;
use App\Modules\Access\Http\Middleware\LogOutDeactivatedUsers;
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
        // The portal is private: every response says "do not index" and carries basic hardening headers.
        $middleware->append(SecurityHeaders::class);
        // AuthenticateSession: a password change signs the person out on their other devices.
        $middleware->web(append: [AuthenticateSession::class, LogOutDeactivatedUsers::class]);
        $middleware->api(append: [LogOutDeactivatedUsers::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
