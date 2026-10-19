<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Modules\Platform\Application\Identity\Ports\AuthMailer;

/**
 * Resend the email verification link. Idempotent; safe to call on
 * already-verified users (the link is short-lived and harmless).
 */
final readonly class SendEmailVerificationAction
{
    public function __construct(private AuthMailer $mailer) {}

    public function execute(int $userId): void
    {
        $this->mailer->sendVerificationLink($userId);
    }
}
