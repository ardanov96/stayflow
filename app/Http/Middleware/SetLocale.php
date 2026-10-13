<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the active locale for every web request.
 *
 * Order of precedence:
 *   1. `locale` cookie (set by LocaleSwitcher)
 *   2. APP_LOCALE env / config('app.locale')
 *
 * Any locale outside config('app.supported_locales') falls back to the
 * application default; this keeps the value restricted to id/en/zh per
 * OQ-06. We do NOT trust the `Accept-Language` header in MVP — the
 * workspace is small and the UX expectation is "default to property
 * language, switch via header" once P1-01 lands.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<int, string> $supported */
        $supported = config('app.supported_locales', ['en']);
        $default = (string) config('app.locale', 'en');

        $cookieLocale = $request->cookie('locale');

        $locale = is_string($cookieLocale) && in_array($cookieLocale, $supported, true)
            ? $cookieLocale
            : $default;

        App::setLocale($locale);

        return $next($request);
    }
}
