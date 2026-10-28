<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

use App\Modules\Platform\Contracts\DTOs\BillingEvent;
use App\Modules\Platform\Domain\Subscription\Subscription;
use App\Modules\Platform\Domain\Subscription\SubscriptionStatus;

/**
 * The subscriptions of the organizations (ADR-0013).
 *
 * The local table is the single source of truth: the gateway is told
 * what to do, it is never asked what the state is.
 */
interface SubscriptionRepository
{
    public function find(int $organizationId): ?Subscription;

    /**
     * The subscription of the organization the gateway refers to.
     */
    public function findByGatewayReference(string $reference): ?Subscription;

    public function save(Subscription $subscription): void;

    /**
     * Move a subscription to another state, when the state machine allows
     * it. Returns null when the move is not allowed.
     */
    public function transition(int $organizationId, SubscriptionStatus $to): ?Subscription;

    /**
     * Record that an event of the gateway was handled.
     *
     * Returns **false** when the event was already handled: that is the
     * replay, and it must not charge twice.
     */
    public function recordEvent(int $organizationId, BillingEvent $event): bool;

    /**
     * Issue the invoice of a period. Returns false when the period was
     * already invoiced.
     */
    public function issueInvoice(
        int $organizationId,
        BillingEvent $event,
        int $amountMinor,
        string $currency,
        string $periodReference,
    ): bool;

    /**
     * The invoices of an organization, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function invoicesFor(int $organizationId): array;
}
