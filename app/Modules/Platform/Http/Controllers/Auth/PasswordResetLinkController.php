<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Application\Identity\RequestPasswordResetAction;
use App\Modules\Platform\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Forgot password" form (GET) and reset-link request (POST).
 * Always responds with the same status, regardless of whether the
 * email is known, to prevent user enumeration.
 */
final class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(ForgotPasswordRequest $request, RequestPasswordResetAction $action): RedirectResponse
    {
        $action->execute($request->email());

        return back()->with('status', __('auth.forgot.generic_success'));
    }
}
