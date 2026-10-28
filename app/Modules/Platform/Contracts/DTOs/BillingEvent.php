<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\DTOs;

/**
 * An event of a billing gateway, in the shape of Stayflow.
 *
 * Whatever the provider calls it, the application sees the same thing:
 * a type, the identifier of the event at the provider (the
 * idempotency key), and what it means for the subscription.
 */
final readonly class BillingEvent
{
    public const CHARGE_SUCCEEDED = 'charge.succeeded';

    public const CHARGE_FAILED = 'charge.failed';

    public const SUBSCRIPTION_CANCELED = 'subscription.canceled';

    public function __construct(
        public string $gateway,
        public string $type,
        public string $reference,
        public ?int $amountMinor = null,
        public string $currency = 'IDR',
        public ?string $periodReference = null,
        public ?string $organizationReference = null,
        public ?string $failureReason = null,
    ) {}
}
