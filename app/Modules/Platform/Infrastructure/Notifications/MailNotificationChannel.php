<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Notifications;

use App\Modules\Platform\Application\Notifications\Notification;
use App\Modules\Platform\Contracts\DTOs\DeliveryOutcome;
use App\Modules\Platform\Contracts\NotificationChannel;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The mail channel.
 *
 * The template is rendered in the locale of the notification, so the
 * subject and the body come out in the language of the reader.
 */
final class MailNotificationChannel implements NotificationChannel
{
    public function channel(): string
    {
        return 'mail';
    }

    public function available(): bool
    {
        return true;
    }

    public function deliver(Notification $notification, string $recipient): DeliveryOutcome
    {
        try {
            $mailable = app()->make($notification->mailable, $notification->arguments);
            $mailable->locale($notification->locale);

            $sent = Mail::to($recipient)->send($mailable);

            return DeliveryOutcome::sent(
                $sent instanceof SentMessage ? $sent->getMessageId() : null,
            );
        } catch (Throwable $e) {
            return DeliveryOutcome::failed($e->getMessage());
        }
    }
}
