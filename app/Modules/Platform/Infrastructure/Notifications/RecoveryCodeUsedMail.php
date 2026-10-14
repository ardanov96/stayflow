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
 * Mailable that tells a user one of their recovery codes was just
 * used to sign in. This is a "did you do this?" notification —
 * the only channel a user has to notice recovery-code abuse, so
 * it should arrive as soon as the queue worker picks it up
 * (default mailer is `log` in tests, so the assertion is on the
 * notifiable / subject line).
 */
class RecoveryCodeUsedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $remainingCount,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('email.recovery_code_used_subject'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.auth.recovery-code-used',
            with: [
                'userName' => $this->userName,
                'remainingCount' => $this->remainingCount,
            ],
        );
    }
}