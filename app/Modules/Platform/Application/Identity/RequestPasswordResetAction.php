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
    ) {}

    public function execute(string $email): void
    {
        // The gateway always returns a string (Laravel's broker
        // returns a throwaway token when the email is unknown);
        // we still gate the mail on the user's existence so an
        // attacker can't probe by counting which addresses send.
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return;
        }
        $token = $this->users->createPasswordResetToken($email);
        $this->mailer->sendPasswordResetLink($email, $token);
    }
}
