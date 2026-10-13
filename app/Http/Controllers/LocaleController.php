<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

final class LocaleController extends Controller
{
    /**
     * Persist the chosen locale in a cookie and redirect back.
     *
     * Validation is the supported-locales list (config/app.php); anything
     * else is rejected with 422 so a malformed client cannot set a
     * locale Laravel doesn't ship translations for.
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var array<int, string> $supported */
        $supported = config('app.supported_locales', ['en']);

        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $supported)],
        ]);

        App::setLocale($validated['locale']);

        return back()->cookie(
            'locale',
            $validated['locale'],
            /** minutes */ 60 * 24 * 365,
            '/',
            null,
            (bool) config('session.secure', false),
            true, // httpOnly
            false,
            'Lax',
        );
    }
}
