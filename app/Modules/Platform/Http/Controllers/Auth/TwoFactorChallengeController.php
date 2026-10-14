<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Application\Identity\CompleteTwoFactorLoginAction;
use App\Modules\Platform\Http\Requests\Auth\TwoFactorChallengeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 2FA challenge (step 2 of login). The user lands here after
 * {@see LoginController@store} flagged the outcome as
 * "two factor required"; the session has a
 * `auth.two_factor_user_id` marker that this controller reads
 * before rendering the form or accepting a code.
 *
 * If the marker is missing (e.g. the user navigated directly
 * here), the controller redirects to login — the marker is the
 * bearer credential for "step 1 succeeded; show me step 2".
 */
final class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $userId = $request->session()->get('auth.two_factor_user_id');
        if (! is_int($userId) || $userId < 1) {
            return redirect()->route('auth.login');
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            'status' => session('status'),
        ]);
    }

    public function store(
        TwoFactorChallengeRequest $request,
        CompleteTwoFactorLoginAction $action,
    ): RedirectResponse {
        $userId = $request->session()->get('auth.two_factor_user_id');
        if (! is_int($userId) || $userId < 1) {
            return redirect()->route('auth.login');
        }
        $remember = (bool) $request->session()->get('auth.two_factor_remember', false);

        $outcome = $action->execute(
            userId: $userId,
            input: $request->code(),
            ipAddress: (string) $request->ip(),
            userAgent: $request->userAgent(),
        );

        if ($outcome->isAuthenticated()) {
            $request->session()->forget(['auth.two_factor_user_id', 'auth.two_factor_remember']);
            \Illuminate\Support\Facades\Auth::loginUsingId($outcome->userId, $remember);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'code' => __('auth.two_factor.generic_error'),
        ]);
    }
}