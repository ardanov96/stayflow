<?php

declare(strict_types=1);

namespace App\Modules\Folio\Contracts;

use App\Modules\Folio\Contracts\DTOs\FolioDTO;

/**
 * The folio of a reservation: what the guest owes and what the guest
 * paid.
 */
interface FolioService
{
    /**
     * The folio of a reservation, opened when it does not exist yet.
     */
    public function openForReservation(int $reservationId): FolioDTO;

    /**
     * Post a charge: what the guest owes.
     */
    public function charge(
        int $reservationId,
        int $amountMinor,
        string $businessDate,
        ?string $source = null,
        ?int $userId = null,
    ): FolioDTO;

    /**
     * Post a tax on a charge.
     */
    public function postTax(
        int $reservationId,
        int $amountMinor,
        string $businessDate,
        ?string $source = null,
        ?int $userId = null,
    ): FolioDTO;

    /**
     * Post a payment. The idempotency key makes sure the same payment is
     * never taken twice.
     */
    public function payment(
        int $reservationId,
        int $amountMinor,
        string $method,
        string $idempotencyKey,
        string $businessDate,
        ?string $gatewayRef = null,
        ?int $userId = null,
    ): FolioDTO;

    /**
     * Post a refund: what is given back to the guest.
     */
    public function refund(
        int $reservationId,
        int $amountMinor,
        string $businessDate,
        ?int $userId = null,
    ): FolioDTO;

    /**
     * Post an adjustment, in either direction.
     */
    public function adjust(
        int $reservationId,
        int $amountMinor,
        string $businessDate,
        ?int $userId = null,
    ): FolioDTO;

    /**
     * Reverse an entry. The reversal never exceeds what is left to
     * reverse of the entry it reverses.
     */
    public function reverse(
        int $reservationId,
        int $entryId,
        int $amountMinor,
        string $businessDate,
        ?int $userId = null,
    ): FolioDTO;

    /**
     * What the guest still owes, in minor units.
     */
    public function balance(int $reservationId): int;

    /**
     * The ledger of the folio.
     *
     * @return list<array<string, mixed>>
     */
    public function entries(int $reservationId): array;

    /**
     * Close the folio: it accepts no new entry.
     */
    public function close(int $reservationId): FolioDTO;

    /**
     * Close a business date of a property: it accepts no new entry.
     */
    public function closeBusinessDate(int $propertyId, string $date): void;
}
