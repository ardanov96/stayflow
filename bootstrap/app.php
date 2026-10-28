<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\SetLocale;
use App\Modules\Platform\Http\Middleware\AuthorizePermission;
use App\Modules\Platform\Http\Middleware\SetTenantContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Hold expiry: every minute, per organization (the command
        // fans the work out so every job keeps its tenant context).
        $schedule->command('inventory:expire-holds')
            ->everyMinute()
            ->withoutOverlapping();

        // Inventory days are materialized for a rolling window; refresh
        // them once a night so the next stay dates always exist.
        $schedule->command('inventory:materialize-days')
            ->dailyAt('02:00')
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AssignRequestId::class,
            SetLocale::class,
            SetTenantContext::class,
        ]);
        // The `locale` cookie is non-sensitive and is set/read by both
        // LocaleController (PHP) and LocaleSwitcher (browser). Keeping it
        // plaintext means the e2e Playwright suite can pre-seed it without
        // having to roundtrip through the encrypted Laravel cookie protocol,
        // and the value is just a 2-letter code (id/en/zh).
        $middleware->alias([
            'permission' => AuthorizePermission::class,
        ]);

        $middleware->encryptCookies(except: [
            'locale',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The Sentry package does not capture the exceptions on its own
        // (since v4 the application has to call it), so every reported
        // exception is handed to Sentry here.
        //
        // The tenant of the event is attached by the `before_send` hook of
        // `config/sentry.php` (`SentryTenantContext`): it reads the tenant
        // that is active when the event is sent, so it works the same for
        // a request and for a queued job.
        $exceptions->reportable(function (Throwable $e): void {
            Integration::captureUnhandledException($e);
        });
    })->create();
