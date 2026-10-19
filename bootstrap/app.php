<?php

use App\Http\Middleware\EnsureFaceIsEnrolled;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trusts whatever reverse proxy forwards to this app (nginx on the
        // deployed servers terminating TLS, or a local HTTPS-terminating
        // proxy for LAN device testing) to report the original client IP
        // and scheme via X-Forwarded-For/-Proto. Safe to trust broadly
        // here because PHP-FPM is never reachable directly from the
        // public internet — only that one upstream proxy can reach it.
        // Scoping this to a single hardcoded IP (e.g. 127.0.0.1) silently
        // breaks the moment the real proxy's address doesn't match it:
        // Laravel then treats genuinely-HTTPS requests as plain HTTP,
        // which showed up as login/logout needing a manual refresh to
        // take effect (the session cookie's Secure flag and generated
        // redirect URLs disagreeing with what the browser actually used).
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'face.enrolled' => EnsureFaceIsEnrolled::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
