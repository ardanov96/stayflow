<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;

/**
 * Mark the user's email as verified. Called when the user clicks
 * the signed verification link. The signature check is the
 * controller's responsibility; this action just performs the
 * idempotent state change.
 */
final readonly class MarkEmailVerifiedAction
{
    public function __construct(private UserIdentityGateway $users) {}

    public function execute(int $userId): void
    {
        $this->users->markEmailVerified($userId);
    }
}
