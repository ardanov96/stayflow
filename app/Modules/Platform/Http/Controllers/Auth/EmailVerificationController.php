<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Platform\Application\Identity\MarkEmailVerifiedAction;
use App\Modules\Platform\Application\Identity\SendEmailVerificationAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Verify the user's email via a signed URL (`/email/verify/{id}/{hash}`)
 * and resend the verification link from the settings page.
 *
 * The signed middleware rejects tampered URLs with 403. The
 * `hash` parameter is sha1($user->email) so any email change
 * after the link was issued invalidates it. Re-issued links
 * re-sign against the new email automatically.
 */
final class EmailVerificationController extends Controller
{
    public function verify(
        Request $request,
        int $id,
        string $hash,
        MarkEmailVerifiedAction $action,
    ): RedirectResponse {
        $user = User::query()->find($id);
        if ($user === null) {
            throw new AuthorizationException();
        }
        if (! hash_equals(sha1((string) $user->email), $hash)) {
            throw new AuthorizationException();
        }
        if (! $user->hasVerifiedEmail()) {
            $action->execute($id);
        }

        return redirect()->route('dashboard')->with('status', __('auth.verify.success'));
    }

    public function resend(
        Request $request,
        SendEmailVerificationAction $action,
    ): RedirectResponse {
        $user = $request->user();
        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $action->execute((int) $user->getKey());
        }

        return back()->with('status', __('auth.verify.resent'));
    }
}