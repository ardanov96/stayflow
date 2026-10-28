<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Subscriptions;

use App\Modules\Platform\Contracts\DTOs\BillingEvent;
use App\Modules\Platform\Contracts\SubscriptionRepository;
use App\Modules\Platform\Domain\Subscription\Subscription;
use App\Modules\Platform\Domain\Subscription\SubscriptionStatus;
use App\Support\Date\UtcTimestamp;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The subscriptions of the organizations, on the database.
 *
 * The local table is the single source of truth (ADR-0013).
 */
final class EloquentSubscriptionRepository implements SubscriptionRepository
{
    public function find(int $organizationId): ?Subscription
    {
        $row = DB::table('subscriptions')->where('organization_id', $organizationId)->first();

        return $row === null ? null : $this->toSubscription($row);
    }

    public function findByGatewayReference(string $reference): ?Subscription
    {
        $row = DB::table('subscriptions')->where('gateway_reference', $reference)->first();

        return $row === null ? null : $this->toSubscription($row);
    }

    public function save(Subscription $subscription): void
    {
        DB::table('subscriptions')->updateOrInsert(
            ['organization_id' => $subscription->organizationId],
            [
                'plan' => $subscription->plan,
                'status' => $subscription->status->value,
                'trial_ends_at' => $subscription->trialEndsAt !== null ? UtcTimestamp::of($subscription->trialEndsAt) : null,
                'current_period_ends_at' => $subscription->currentPeriodEndsAt !== null ? UtcTimestamp::of($subscription->currentPeriodEndsAt) : null,
                'gateway' => $subscription->gateway,
                'gateway_reference' => $subscription->gatewayReference,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function transition(int $organizationId, SubscriptionStatus $to): ?Subscription
    {
        $subscription = $this->find($organizationId);

        if ($subscription === null || ! $subscription->status->canTransitionTo($to)) {
            return null;
        }

        $subscription = new Subscription(
            organizationId: $subscription->organizationId,
            plan: $subscription->plan,
            status: $to,
            trialEndsAt: $subscription->trialEndsAt,
            currentPeriodEndsAt: $subscription->currentPeriodEndsAt,
            gateway: $subscription->gateway,
            gatewayReference: $subscription->gatewayReference,
        );

        $this->save($subscription);

        return $subscription;
    }

    public function recordEvent(int $organizationId, BillingEvent $event): bool
    {
        try {
            DB::table('billing_events')->insert([
                'organization_id' => $organizationId,
                'gateway' => $event->gateway,
                'reference' => $event->reference,
                'type' => $event->type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            // The event was already handled: the provider delivered it
            // twice. It must not charge twice.
            return false;
        }

        return true;
    }

    public function issueInvoice(
        int $organizationId,
        BillingEvent $event,
        int $amountMinor,
        string $currency,
        string $periodReference,
    ): bool {
        $subscription = $this->find($organizationId);

        if ($subscription === null) {
            return false;
        }

        try {
            DB::table('subscription_invoices')->insert([
                'organization_id' => $organizationId,
                'subscription_id' => $this->subscriptionId($organizationId),
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'status' => $event->type === BillingEvent::CHARGE_SUCCEEDED ? 'paid' : 'failed',
                'period_reference' => $periodReference,
                'paid_at' => $event->type === BillingEvent::CHARGE_SUCCEEDED ? now() : null,
                'failure_reason' => $event->failureReason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            // The period was already invoiced.
            return false;
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function invoicesFor(int $organizationId): array
    {
        return DB::table('subscription_invoices')
            ->where('organization_id', $organizationId)
            ->orderByDesc('id')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    private function subscriptionId(int $organizationId): int
    {
        return (int) DB::table('subscriptions')->where('organization_id', $organizationId)->value('id');
    }

    private function toSubscription(object $row): Subscription
    {
        return new Subscription(
            organizationId: (int) $row->organization_id,
            plan: (string) $row->plan,
            status: SubscriptionStatus::from((string) $row->status),
            trialEndsAt: $this->date($row->trial_ends_at ?? null),
            currentPeriodEndsAt: $this->date($row->current_period_ends_at ?? null),
            gateway: (string) $row->gateway,
            gatewayReference: $row->gateway_reference !== null ? (string) $row->gateway_reference : null,
        );
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new DateTimeImmutable((string) $value);
    }
}
