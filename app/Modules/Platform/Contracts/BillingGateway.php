<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

use App\Modules\Platform\Contracts\DTOs\BillingEvent;

/**
 * A billing gateway (ADR-0013).
 *
 * Xendit is the primary: it supports the payment methods of OQ-05
 * (virtual accounts, QRIS, the e-wallets, the cards) with the
 * Merchant-Initiated Transactions. Midtrans is the secondary, for the
 * cards.
 */
interface BillingGateway
{
    /**
     * The name of the gateway: xendit, midtrans.
     */
    public function name(): string;

    /**
     * The payment channels the gateway can pay with.
     *
     * @return list<string>
     */
    public function channels(): array;

    /**
     * Whether the payload comes from the provider.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function verify(array $payload, array $headers = []): bool;

    /**
     * Turn the payload of the provider into the event of Stayflow.
     *
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload): ?BillingEvent;
}
