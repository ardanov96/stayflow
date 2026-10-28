<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\DTOs;

/**
 * What a channel did with one notification.
 *
 * The outcome is the same for every channel: it worked, or it did not,
 * and when it did not, why.
 */
final readonly class DeliveryOutcome
{
    public function __construct(
        public bool $sent,
        public ?string $providerMessageId = null,
        public ?string $error = null,
    ) {}

    public static function sent(?string $providerMessageId = null): self
    {
        return new self(sent: true, providerMessageId: $providerMessageId);
    }

    public static function failed(string $error): self
    {
        return new self(sent: false, error: $error);
    }
}
