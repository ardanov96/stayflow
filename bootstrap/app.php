<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\SetLocale;
use App\Modules\Platform\Http\Middleware\AuthorizePermission;
use App\Modules\Platform\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
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
        //
    })->create();
