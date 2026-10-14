<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Application\Identity\ResetPasswordAction;
use App\Modules\Platform\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Render the reset form (with the token from the URL) and accept
 * the new password. The token is the bearer credential; the
 * password broker verifies it before the action runs.
 */
final class NewPasswordController extends Controller
{
    public function create(string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) request()->query('email', ''),
        ]);
    }

    public function store(ResetPasswordRequest $request, ResetPasswordAction $action): RedirectResponse
    {
        $action->execute(
            email: $request->email(),
            token: $request->token(),
            newPassword: $request->newPassword(),
        );

        return redirect()->route('auth.login')->with(
            'status',
            __('auth.reset.success'),
        );
    }
}