<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Subscription;

/**
 * The state of a subscription.
 *
 * The state machine is the contract with the billing: a cycle that
 * failed does not end the subscription at once, it makes it *past due*,
 * and the organization keeps the modules until the grace period is over.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Expired = 'expired';

    /**
     * Whether the organization may use the product.
     */
    public function grantsAccess(): bool
    {
        return match ($this) {
            self::Trialing, self::Active, self::PastDue => true,
            self::Canceled, self::Expired => false,
        };
    }

    /**
     * Whether the organization has to pay.
     */
    public function isPayable(): bool
    {
        return match ($this) {
            self::Trialing, self::Active, self::PastDue => true,
            self::Canceled, self::Expired => false,
        };
    }

    /**
     * The states a subscription can move to.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Trialing => [self::Active, self::Canceled, self::Expired],
            self::Active => [self::PastDue, self::Canceled, self::Expired],
            self::PastDue => [self::Active, self::Canceled, self::Expired],
            self::Canceled => [self::Expired],
            self::Expired => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
