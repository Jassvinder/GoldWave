<?php

use App\Console\Scheduling;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStoreOwnership;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
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
    // DOMAIN_LOGIC.md §19's actual job registrations live in
    // `App\Console\Scheduling::register()`, not inline here, so the wiring
    // is directly unit-testable against a plain `new Schedule()` instance
    // (Docs/TASKS.md T-019's own testing note — resolving `Schedule::class`
    // through the container only fires this via Laravel's `Artisan::starting`
    // mechanism, unreliable to assert against directly in a test process
    // that runs multiple Artisan invocations).
    ->withSchedule(fn (Schedule $schedule) => Scheduling::register($schedule))
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'store-owner' => EnsureStoreOwnership::class,
        ]);

        $middleware->validateCsrfTokens(except: ['payments/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
