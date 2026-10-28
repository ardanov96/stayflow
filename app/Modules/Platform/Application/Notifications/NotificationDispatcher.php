<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Notifications;

use App\Modules\Platform\Contracts\DTOs\NotificationDeliveryDTO;
use App\Modules\Platform\Contracts\NotificationChannel;
use App\Modules\Platform\Contracts\NotificationDeliveryLog;

/**
 * Delivers a notification and records what happened.
 *
 * Every attempt is recorded, successful or not. A notification that
 * failed silently is a notification that never arrives, and nobody
 * notices until a guest complains.
 */
final readonly class NotificationDispatcher
{
    /**
     * @param  list<NotificationChannel>  $channels
     */
    public function __construct(
        private array $channels,
        private NotificationDeliveryLog $log,
    ) {}

    /**
     * @return list<NotificationDeliveryDTO>
     */
    public function dispatch(Notification $notification): array
    {
        $channel = $this->channelFor($notification);

        if ($channel === null) {
            // No channel is ready for this notification. It is not sent,
            // and that is not a failed delivery: there is nothing to retry.
            return [];
        }

        $deliveries = [];

        foreach ($notification->recipients as $recipient) {
            $outcome = $channel->deliver($notification, $recipient);

            $deliveries[] = $this->log->record(
                organizationId: $notification->organizationId,
                propertyId: $notification->propertyId,
                channel: $channel->channel(),
                template: $notification->mailable,
                locale: $notification->locale,
                recipient: $recipient,
                sent: $outcome->sent,
                providerMessageId: $outcome->providerMessageId,
                error: $outcome->error,
            );
        }

        return $deliveries;
    }

    private function channelFor(Notification $notification): ?NotificationChannel
    {
        foreach ($this->channels as $channel) {
            if ($channel->channel() === $notification->channel && $channel->available()) {
                return $channel;
            }
        }

        return null;
    }
}
