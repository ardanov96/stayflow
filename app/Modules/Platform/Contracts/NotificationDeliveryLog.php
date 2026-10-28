<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

use App\Modules\Platform\Contracts\DTOs\NotificationDeliveryDTO;

/**
 * The log of the deliveries of the notifications (task P1-15).
 *
 * The log is what makes a failed delivery visible: a notification that
 * failed silently is a notification that never arrives, and nobody
 * notices until a guest complains.
 */
interface NotificationDeliveryLog
{
    /**
     * Record one delivery attempt.
     */
    public function record(
        int $organizationId,
        ?int $propertyId,
        string $channel,
        string $template,
        string $locale,
        string $recipient,
        bool $sent,
        ?string $providerMessageId = null,
        ?string $error = null,
    ): NotificationDeliveryDTO;

    /**
     * The deliveries that did not work, newest first.
     *
     * @return list<NotificationDeliveryDTO>
     */
    public function failedFor(int $organizationId, int $limit = 50): array;

    /**
     * Mark a delivery as bounced or complained, from the webhook of the
     * email provider.
     */
    public function markProviderFailure(
        string $providerMessageId,
        string $recipient,
        string $status,
    ): ?NotificationDeliveryDTO;
}
