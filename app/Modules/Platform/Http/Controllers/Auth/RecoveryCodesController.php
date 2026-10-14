<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Application\Identity\RegenerateRecoveryCodesAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Render and regenerate the recovery codes. Show-uses the user's
 * stored hashes; we never expose them to the view (we'd just be
 * showing the hashes the user already has indirectly). Regenerate
 * returns the new plaintext codes, which the view MUST show
 * exactly once.
 */
final class RecoveryCodesController extends Controller
{
    public function show(Request $request, UserIdentityGateway $users): Response
    {
        $userId = (int) $request->user()->getKey();
        $hashes = $users->getRecoveryCodeHashes($userId);

        return Inertia::render('Auth/TwoFactorRecoveryCodes', [
            'codes' => [],
            'justGenerated' => false,
            'count' => count($hashes),
        ]);
    }

    public function regenerate(
        Request $request,
        RegenerateRecoveryCodesAction $action,
    ): Response {
        $userId = (int) $request->user()->getKey();
        $codes = $action->execute($userId);

        return Inertia::render('Auth/TwoFactorRecoveryCodes', [
            'codes' => array_map(static fn ($c): string => (string) $c, $codes),
            'justGenerated' => true,
            'count' => count($codes),
        ]);
    }
}