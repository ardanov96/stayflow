<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Application\Identity\DisableTwoFactorAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Disable 2FA. Idempotent. The user is NOT signed out; the next
 * page render reflects the disabled state immediately.
 */
final class TwoFactorDisableController extends Controller
{
    public function destroy(
        Request $request,
        DisableTwoFactorAction $action,
    ): RedirectResponse {
        $userId = (int) $request->user()->getKey();
        $action->execute($userId);

        return back()->with('status', __('auth.two_factor.disabled'));
    }
}