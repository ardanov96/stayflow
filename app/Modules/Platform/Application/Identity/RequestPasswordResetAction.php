<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Modules\Platform\Application\Identity\Ports\AuthMailer;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;

/**
 * Request a password-reset link. Always returns true; the controller
 * MUST respond with an identical "we sent you a link" message
 * regardless of whether the email is known, to avoid user
 * enumeration.
 */
final readonly class RequestPasswordResetAction
{
    public function __construct(
        private UserIdentityGateway $users,
        private AuthMailer $mailer,
    ) {
    }

    public function execute(string $email): void
    {
        $token = $this->users->createPasswordResetToken($email);
        if ($token !== null) {
            $this->mailer->sendPasswordResetLink($email, $token);
        }
    }
}