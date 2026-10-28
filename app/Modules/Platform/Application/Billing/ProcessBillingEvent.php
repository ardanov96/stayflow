<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Billing;

use App\Modules\Platform\Contracts\DTOs\BillingEvent;
use App\Modules\Platform\Contracts\DTOs\BillingOutcome;
use App\Modules\Platform\Contracts\SubscriptionRepository;
use App\Modules\Platform\Domain\Subscription\SubscriptionStatus;
use App\Modules\Platform\Infrastructure\Billing\BillingGatewaySelector;

/**
 * Handles an event of a billing gateway.
 *
 * The webhook of the provider is delivered **at least once**, so the
 * same event can arrive twice. The event is recorded before it is
 * applied: the second delivery finds the event, and nothing is charged
 * twice.
 */
final readonly class ProcessBillingEvent
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private BillingGatewaySelector $gateways,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function handle(string $organizationReference, array $payload, array $headers = []): BillingOutcome
    {
        $subscription = $this->subscriptions->findByGatewayReference($organizationReference);

        if ($subscription === null) {
            return BillingOutcome::Unknown;
        }

        // The organization chose its gateway: the application speaks to
        // both the same way.
        $gateway = $this->gateways->for($subscription->gateway);

        if (! $gateway->verify($payload, $headers)) {
            return BillingOutcome::Rejected;
        }

        $event = $gateway->normalize($payload);

        if ($event === null) {
            return BillingOutcome::Ignored;
        }

        // The replay: the provider delivered the same event twice.
        if (! $this->subscriptions->recordEvent($subscription->organizationId, $event)) {
            return BillingOutcome::Replayed;
        }

        match ($event->type) {
            BillingEvent::CHARGE_SUCCEEDED => $this->chargeSucceeded($subscription->organizationId, $event),
            BillingEvent::CHARGE_FAILED => $this->chargeFailed($subscription->organizationId, $event),
            BillingEvent::SUBSCRIPTION_CANCELED => $this->subscriptions->transition(
                $subscription->organizationId,
                SubscriptionStatus::Canceled,
            ),
            default => null,
        };

        return BillingOutcome::Handled;
    }

    private function chargeSucceeded(int $organizationId, BillingEvent $event): void
    {
        $this->subscriptions->issueInvoice(
            organizationId: $organizationId,
            event: $event,
            amountMinor: $event->amountMinor ?? 0,
            currency: $event->currency,
            periodReference: $event->periodReference ?? $event->reference,
        );

        // A trial that was paid becomes an active subscription.
        $subscription = $this->subscriptions->find($organizationId);

        if ($subscription !== null && $subscription->isTrialing()) {
            $this->subscriptions->transition($organizationId, SubscriptionStatus::Active);
        }
    }

    private function chargeFailed(int $organizationId, BillingEvent $event): void
    {
        $this->subscriptions->issueInvoice(
            organizationId: $organizationId,
            event: $event,
            amountMinor: $event->amountMinor ?? 0,
            currency: $event->currency,
            periodReference: $event->periodReference ?? $event->reference,
        );

        // A cycle that failed does not end the subscription: it makes it
        // past due, and the organization keeps the modules until the
        // grace period is over.
        $this->subscriptions->transition($organizationId, SubscriptionStatus::PastDue);
    }
}
