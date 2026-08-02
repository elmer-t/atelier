<?php

use App\Http\Middleware\EnsureProjectAccessible;
use App\Http\Middleware\EnsureUserIsCreator;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Console\Scheduling\Schedule;
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
    ->withSchedule(function (Schedule $schedule): void {
        // Enforce time-based client-data retention (no-op unless a retention window
        // is configured; see config/atelier.php). Requires the schedule:run cron.
        $schedule->command('atelier:prune-client-data')->daily();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'project.accessible' => EnsureProjectAccessible::class,
            'creator' => EnsureUserIsCreator::class,
        ]);

        // Security headers (CSP, nosniff, framing) on every app-origin response.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        // Trusted proxies are read at request time from config('trustedproxy.proxies')
        // by the framework's TrustProxies middleware (config/trustedproxy.php) — cannot
        // be set here, as config() is not yet bound when this closure runs. Behind a
        // CDN/LB this is what keeps request()->ip() (and every per-IP rate limit) honest
        // rather than collapsing every visitor onto the proxy's address (#43).
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
