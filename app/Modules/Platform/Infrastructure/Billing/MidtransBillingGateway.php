<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Billing;

use App\Modules\Platform\Contracts\BillingGateway;
use App\Modules\Platform\Contracts\DTOs\BillingEvent;

/**
 * The secondary gateway (ADR-0013): the cards.
 *
 * The organization chooses its gateway, and the application speaks to
 * both the same way: the payload of the provider becomes the event of
 * Stayflow, and everything else is the same.
 *
 * **The shape of the callback of this provider is to be confirmed
 * against its sandbox** (`docs/VENDORS.md` lists what was verified and
 * what was not). The adapter is written against the fields the
 * documentation of the provider describes; the contract tests pin the
 * shape, so a change of the provider fails a test instead of a charge.
 */
final class MidtransBillingGateway implements BillingGateway
{
    public function name(): string
    {
        return 'midtrans';
    }

    /**
     * @return list<string>
     */
    public function channels(): array
    {
        return ['card'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function verify(array $payload, array $headers = []): bool
    {
        return ($payload['transaction_id'] ?? $payload['order_id'] ?? null) !== null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload): ?BillingEvent
    {
        $reference = (string) ($payload['order_id'] ?? '');

        if ($reference === '') {
            return null;
        }

        $type = match (strtoupper((string) ($payload['transaction_status'] ?? ''))) {
            'CAPTURE', 'SETTLEMENT' => BillingEvent::CHARGE_SUCCEEDED,
            'DENY', 'CANCEL', 'EXPIRE' => BillingEvent::CHARGE_FAILED,
            default => null,
        };

        if ($type === null) {
            return null;
        }

        $amount = $payload['gross_amount'] ?? $payload['amount'] ?? null;

        return new BillingEvent(
            gateway: $this->name(),
            type: $type,
            reference: $reference,
            amountMinor: $amount !== null ? (int) $amount : null,
            currency: 'IDR',
            periodReference: $reference,
            failureReason: isset($payload['failure_code']) ? (string) $payload['failure_code'] : null,
        );
    }
}
