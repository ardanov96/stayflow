<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable for password-reset links. The link is the full URL the
 * SPA / form posts to, including the token in the query string.
 * Token validity is enforced by the password broker
 * (default 60 minutes in config/auth.php).
 */
class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $resetUrl,
        public string $userEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('email.reset_password_subject'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.auth.reset-password',
            with: [
                'resetUrl' => $this->resetUrl,
                'userEmail' => $this->userEmail,
            ],
        );
    }
}
