<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

use App\Modules\Platform\Application\Notifications\Notification;
use App\Modules\Platform\Contracts\DTOs\DeliveryOutcome;

/**
 * A channel a notification can go through.
 *
 * Mail is the only channel that is implemented today; the contract is
 * there so that WhatsApp (and the rest) can be added without touching
 * the dispatcher, the delivery log or the callers.
 */
interface NotificationChannel
{
    /**
     * The name of the channel: mail, whatsapp, ...
     */
    public function channel(): string;

    /**
     * Whether the channel is ready to deliver.
     *
     * A channel that is not configured is skipped, not failed: the
     * notification is not lost, it is not sent.
     */
    public function available(): bool;

    /**
     * Deliver one notification to one recipient.
     */
    public function deliver(Notification $notification, string $recipient): DeliveryOutcome;
}
