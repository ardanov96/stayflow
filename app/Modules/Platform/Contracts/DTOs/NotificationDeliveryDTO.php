<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\DTOs;

/**
 * One delivery of one notification to one recipient.
 */
final readonly class NotificationDeliveryDTO
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public ?int $propertyId,
        public string $channel,
        public string $template,
        public string $locale,
        public string $recipient,
        public string $status,
        public ?string $providerMessageId,
        public ?string $error,
    ) {}
}
