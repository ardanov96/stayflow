<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Application\Identity\BeginTwoFactorEnrollmentAction;
use App\Modules\Platform\Application\Identity\ConfirmTwoFactorEnrollmentAction;
use App\Modules\Platform\Application\Identity\Ports\TotpGenerator;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Domain\Identity\RecoveryCode;
use App\Modules\Platform\Http\Requests\Auth\TwoFactorSetupConfirmRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 2FA enrollment flow. Three steps:
 *
 *   GET  /two-factor/setup        — render the form (begin or confirm)
 *   POST /two-factor/setup        — begin: generate a secret, stash it
 *                                   on the user row, return the
 *                                   `otpauth://` URI for QR rendering
 *   POST /two-factor/setup/confirm — verify the code, promote the
 *                                    secret to confirmed, issue
 *                                    recovery codes
 *
 * The "create" response branches based on whether the user is
 * already enrolled: if yes, it shows the manage-recovery-codes
 * view; if no, it shows the begin-form. The form submission
 * preserves the secret on the user row across the begin/confirm
 * pair (no session state needed for the secret itself).
 */
final class TwoFactorSetupController extends Controller
{
    public function create(
        Request $request,
        UserIdentityGateway $users,
    ): Response {
        $userId = (int) $request->user()->getKey();
        $user = $users->findById($userId);
        $isEnrolled = $user !== null && $user->twoFactorConfirmed;

        return Inertia::render('Auth/TwoFactorSetup', [
            'alreadyEnrolled' => $isEnrolled,
        ]);
    }

    public function store(
        Request $request,
        BeginTwoFactorEnrollmentAction $begin,
        UserIdentityGateway $users,
        TotpGenerator $totp,
    ): Response {
        $userId = (int) $request->user()->getKey();
        $user = $users->findById($userId);
        if ($user === null) {
            abort(404);
        }

        $secret = $begin->execute($userId);
        $otpauthUrl = $totp->otpauthUrl(
            $secret,
            (string) config('app.name'),
            $user->email,
        );

        return Inertia::render('Auth/TwoFactorSetup', [
            'alreadyEnrolled' => false,
            'secret' => $secret->value,
            'otpauthUrl' => $otpauthUrl,
        ]);
    }

    public function confirm(
        TwoFactorSetupConfirmRequest $request,
        ConfirmTwoFactorEnrollmentAction $confirm,
    ): Response|RedirectResponse {
        $userId = (int) $request->user()->getKey();
        $enrollment = $confirm->execute($userId, $request->code());

        if ($enrollment === null) {
            return back()->withErrors([
                'code' => __('auth.two_factor.generic_error'),
            ]);
        }

        return Inertia::render('Auth/TwoFactorRecoveryCodes', [
            'codes' => array_map(static fn (RecoveryCode $c): string => (string) $c, $enrollment->plaintextRecoveryCodes),
            'justGenerated' => true,
        ]);
    }
}
