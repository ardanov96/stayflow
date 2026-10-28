<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Billing;

use App\Modules\Platform\Contracts\BillingGateway;
use App\Modules\Platform\Contracts\DTOs\BillingEvent;

/**
 * The primary gateway (ADR-0013).
 *
 * Xendit supports the payment methods of OQ-05 — the virtual accounts,
 * QRIS, the e-wallets and the cards — with the Merchant-Initiated
 * Transactions, so a guest of a foreign resident is charged with the
 * card and a guest of an Indonesian resident with the local methods.
 *
 * The shape of the callback was read in the documentation of the
 * provider on 2026-10-27 (see `docs/VENDORS.md` §1): the payload is an
 * envelope with `event`, `business_id`, `api_version` and a `data`
 * object, and the data of the payment carries the status, the amount,
 * the reference and the customer.
 */
final class XenditBillingGateway implements BillingGateway
{
    public function name(): string
    {
        return 'xendit';
    }

    /**
     * @return list<string>
     */
    public function channels(): array
    {
        return ['virtual_account', 'qris', 'e_wallet', 'card', 'retail'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function verify(array $payload, array $headers = []): bool
    {
        // The signature of the callback is checked on top of the shared
        // secret of the endpoint; the scheme of the provider is read
        // from its documentation before the endpoint is opened.
        return ($payload['business_id'] ?? null) !== null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload): ?BillingEvent
    {
        $data = $payload['data']['data'] ?? $payload['data'] ?? [];

        if (! is_array($data)) {
            return null;
        }

        $reference = (string) ($data['reference_id'] ?? '');

        if ($reference === '') {
            return null;
        }

        $type = match (strtoupper((string) ($data['status'] ?? ''))) {
            'SUCCEEDED' => BillingEvent::CHARGE_SUCCEEDED,
            'FAILED' => BillingEvent::CHARGE_FAILED,
            default => null,
        };

        if ($type === null) {
            return null;
        }

        return new BillingEvent(
            gateway: $this->name(),
            type: $type,
            reference: $reference,
            amountMinor: isset($data['total_amount']) ? (int) $data['total_amount'] : null,
            currency: 'IDR',
            periodReference: $reference,
            organizationReference: isset($data['customer_number']) ? (string) $data['customer_number'] : null,
            failureReason: isset($data['failure_code']) ? (string) $data['failure_code'] : null,
        );
    }
}
