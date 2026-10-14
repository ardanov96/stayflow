<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity\Ports;

/**
 * Aggregate mailer port. The Infrastructure adapter dispatches to
 * Laravel `Mailable` classes; the Application stays mailer-agnostic
 * so the verification URL / template can change without touching
 * the actions.
 */
interface AuthMailer
{
    /**
     * Send the email-verification link to the user. Returns false
     * silently if the email is unknown (no enumeration through
     * the mail channel either).
     */
    public function sendVerificationLink(int $userId): void;

    /**
     * Send the password-reset link. Returns false silently if the
     * email is unknown.
     */
    public function sendPasswordResetLink(string $email, string $token): void;

    /**
     * Send a one-time notification that one of the user's recovery
     * codes was used. Lets the user notice unauthorized recovery
     * code consumption.
     */
    public function sendRecoveryCodeUsedNotice(int $userId, string $remainingCodeCount): void;
}