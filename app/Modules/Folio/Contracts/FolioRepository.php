<?php

declare(strict_types=1);

namespace App\Modules\Folio\Contracts;

use App\Modules\Folio\Domain\Folio;
use App\Modules\Folio\Domain\FolioEntry;
use App\Support\Money\Money;

/**
 * The folio of a reservation and its ledger.
 *
 * The ledger is append-only: the repository has no way to update or to
 * delete an entry.
 */
interface FolioRepository
{
    public function find(int $organizationId, int $folioId): ?Folio;

    public function findForReservation(int $organizationId, int $reservationId): ?Folio;

    /**
     * The folio of a reservation, opened when it does not exist yet.
     */
    public function open(int $organizationId, int $propertyId, int $reservationId, string $currency): Folio;

    /**
     * Write the folio: its status and its balance.
     */
    public function save(Folio $folio): void;

    /**
     * Append an entry to the ledger and return it with its identifier.
     */
    public function appendEntry(Folio $folio, FolioEntry $entry): FolioEntry;

    /**
     * Record a payment. The idempotency key makes sure the same payment
     * is never taken twice.
     */
    public function recordPayment(
        int $organizationId,
        int $folioId,
        string $method,
        Money $amount,
        string $idempotencyKey,
        ?string $gatewayRef = null,
    ): void;

    public function businessDateIsClosed(int $organizationId, int $propertyId, string $date): bool;

    public function closeBusinessDate(int $organizationId, int $propertyId, string $date): void;
}
