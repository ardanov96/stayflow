<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Application\Identity\AuthenticateUserAction;
use App\Modules\Platform\Application\Identity\AuthOutcome;
use App\Modules\Platform\Domain\Identity\LoginFailureReason;
use App\Modules\Platform\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login form (GET) and credential check (POST).
 *
 * The form is a thin view; the credential check is delegated to
 * {@see AuthenticateUserAction}. The controller is responsible
 * only for the HTTP surface: CSRF, redirect, session regen on
 * success, and the response shape (Inertia or redirect).
 */
final class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request, AuthenticateUserAction $action): RedirectResponse
    {
        $outcome = $action->execute(
            email: $request->email(),
            password: $request->password(),
            ipAddress: (string) $request->ip(),
            userAgent: $request->userAgent(),
        );

        if ($outcome->isAuthenticated()) {
            $this->loginUser($request, $outcome->userId, $request->shouldRemember());

            return redirect()->intended(route('dashboard'));
        }

        if ($outcome->isTwoFactorRequired()) {
            $request->session()->put('auth.two_factor_user_id', $outcome->userId);
            $request->session()->put('auth.two_factor_remember', $request->shouldRemember());

            return redirect()->route('auth.two-factor.challenge');
        }

        // Failed: do NOT distinguish unknown-email from wrong-password
        // from locked from rate-limited. One message, one status,
        // one redirect — the action already wrote a row to
        // login_attempts for ops dashboards.
        return back()
            ->withInput($request->only('email', 'remember'))
            ->withErrors([
                'email' => __('auth.login.generic_error'),
            ]);
    }

    private function loginUser(Request $request, int $userId, bool $remember): void
    {
        // Auth::loginUsingId regenerates the session ID as a
        // session-fixation defense (AGENTS.md §3 login flow).
        \Illuminate\Support\Facades\Auth::loginUsingId($userId, $remember);
        $request->session()->regenerate();
    }
}