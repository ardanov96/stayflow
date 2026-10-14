<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate that ensures the authenticated user has confirmed 2FA
 * whenever their Membership role requires it.
 *
 * Today the "requires 2FA" set is `owner` and `manager`, mirrored
 * in {@see \App\Models\User::requiresTwoFactor()}. P1-03 (RBAC)
 * owns the canonical list of privileged roles; the middleware
 * will read from the role enum then and the in-model check goes
 * away.
 *
 * Behavior:
 *  - No authenticated user: pass through (the `auth` middleware
 *    on the same group already redirected to login).
 *  - Authenticated, role does NOT require 2FA: pass through.
 *  - Authenticated, role requires 2FA, 2FA confirmed: pass through.
 *  - Authenticated, role requires 2FA, 2FA NOT confirmed:
 *    redirect to the setup page with a status message.
 *  - Authenticated, role requires 2FA, but 2FA enrollment is
 *    mid-flight (secret set, not confirmed yet): redirect to
 *    setup with the same status; the user resumes enrollment
 *    rather than starting over.
 */
final class EnsureTwoFactorIsConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $next($request);
        }

        // Eloquent's hasTwoFactor() / requiresTwoFactor() live on
        // the User model. We call them on the Eloquent instance
        // (which is what the guard returns) so the membership
        // relation can be loaded fresh.
        $requires = method_exists($user, 'requiresTwoFactor')
            ? (bool) $user->requiresTwoFactor()
            : false;
        $has = method_exists($user, 'hasTwoFactor')
            ? (bool) $user->hasTwoFactor()
            : false;

        if ($requires && ! $has) {
            return redirect()
                ->route('auth.two-factor.setup')
                ->with('status', __('auth.two_factor.setup_required'));
        }

        return $next($request);
    }
}