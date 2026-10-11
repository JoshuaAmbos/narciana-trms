<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureRole::class,          // ->middleware('role:Owner,Manager')
            'active' => EnsureUserIsActive::class, // logs out users deactivated mid-session
        ]);

        // Not logged in: go to the login page. If this browser was logged in before (cookie set at login),
        // the session must have expired, so the login page explains the inactivity logout.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->cookie(LoginController::SEEN_COOKIE)
            ? route('login', ['timeout' => 1])
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
