<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Subscription;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * The subscription of an organization.
 *
 * The local subscription is the single source of truth (ADR-0013): the
 * gateway is told what to do, it is never asked what the state is.
 */
final readonly class Subscription
{
    public function __construct(
        public int $organizationId,
        public string $plan,
        public SubscriptionStatus $status,
        public ?DateTimeImmutable $trialEndsAt = null,
        public ?DateTimeImmutable $currentPeriodEndsAt = null,
        public string $gateway = 'xendit',
        public ?string $gatewayReference = null,
    ) {}

    /**
     * The plan of the subscription.
     */
    public function plan(): Plan
    {
        $plans = (array) config('plans.plans', []);

        if (! isset($plans[$this->plan])) {
            throw new InvalidArgumentException("The plan \"{$this->plan}\" does not exist.");
        }

        return Plan::fromConfig($this->plan, (array) $plans[$this->plan]);
    }

    /**
     * Whether the organization may use the product at all.
     */
    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }

    /**
     * Whether the organization has the module.
     *
     * A module that the organization does not pay for is not reachable,
     * whatever the state of the subscription is.
     */
    public function hasModule(string $module): bool
    {
        return $this->grantsAccess() && $this->plan()->hasModule($module);
    }

    public function hasFeature(string $feature): bool
    {
        return $this->grantsAccess() && $this->plan()->hasFeature($feature);
    }

    public function isTrialing(): bool
    {
        return $this->status === SubscriptionStatus::Trialing;
    }

    /**
     * Whether the trial is over.
     */
    public function trialExpired(DateTimeInterface $now): bool
    {
        if (! $this->isTrialing() || $this->trialEndsAt === null) {
            return false;
        }

        return $this->trialEndsAt->getTimestamp() <= $now->getTimestamp();
    }
}
