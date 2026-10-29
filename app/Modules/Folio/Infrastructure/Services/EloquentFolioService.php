<?php

declare(strict_types=1);

namespace App\Modules\Folio\Infrastructure\Services;

use App\Modules\Folio\Contracts\DTOs\FolioDTO;
use App\Modules\Folio\Contracts\FolioRepository;
use App\Modules\Folio\Contracts\FolioService;
use App\Modules\Folio\Domain\Folio;
use App\Modules\Folio\Domain\FolioEntry;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Reservations\Contracts\ReservationService;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The folio of a reservation.
 *
 * Every posting happens in one transaction: the entry, the balance of the
 * folio and the payment are written together, or nothing is.
 */
final readonly class EloquentFolioService implements FolioService
{
    public function __construct(
        private FolioRepository $folios,
        private ReservationService $reservations,
        private OrganizationContext $organizationContext,
    ) {}

    public function openForReservation(int $reservationId): FolioDTO
    {
        return DB::transaction(function () use ($reservationId): FolioDTO {
            $folio = $this->folioOf($reservationId);

            $this->folios->save($folio);

            return $this->toDto($folio);
        });
    }

    public function charge(
        int $reservationId,
        int $amountMinor,
        string $businessDate,
        ?string $source = null,
        ?int $userId = null,
    ): FolioDTO {
        return $this->post(
            $reservationId,
            fn (Folio $folio): FolioEntry => $folio->postCharge(
                Money::ofMinor($amountMinor, $folio->currency()),
                $businessDate,
                $source,
                $userId,
            ),
        );
    }

    public function postTax(
        int $reservationId,
        int $amountMinor,
        string $businessDate,
        ?string $source = null,
        ?int $userId = null,
    ): FolioDTO {
        return $this->post(
            $reservationId,
            fn (Folio $folio): FolioEntry => $folio->postTax(
                Money::ofMinor($amountMinor, $folio->currency()),
                $businessDate,
                $source,
                $userId,
            ),
        );
    }

    public function payment(
        int $reservationId,
        int $amountMinor,
        string $method,
        string $idempotencyKey,
        string $businessDate,
        ?string $gatewayRef = null,
        ?int $userId = null,
    ): FolioDTO {
        return DB::transaction(function () use (
            $reservationId,
            $amountMinor,
            $method,
            $idempotencyKey,
            $businessDate,
            $gatewayRef,
            $userId,
        ): FolioDTO {
            $folio = $this->folioOf($reservationId);

            $entry = $folio->postPayment(
                Money::ofMinor($amountMinor, $folio->currency()),
                $businessDate,
                $method,
                $userId,
            );

            $this->persist($folio, $entry);

            $this->folios->recordPayment(
                organizationId: $folio->organizationId,
                folioId: (int) $folio->id,
                method: $method,
                amount: Money::ofMinor($amountMinor, $folio->currency()),
                idempotencyKey: $idempotencyKey,
                gatewayRef: $gatewayRef,
            );

            return $this->toDto($folio);
        });
    }

    public function refund(
        int $reservationId,
        int $amountMinor,
        string $businessDate,
        ?int $userId = null,
    ): FolioDTO {
        return $this->post(
            $reservationId,
            fn (Folio $folio): FolioEntry => $folio->postRefund(
                Money::ofMinor($amountMinor, $folio->currency()),
                $businessDate,
                'refund',
                $userId,
            ),
        );
    }

    public function adjust(
        int $reservationId,
        int $amountMinor,
        string $businessDate,
        ?int $userId = null,
    ): FolioDTO {
        return $this->post(
            $reservationId,
            fn (Folio $folio): FolioEntry => $folio->postAdjustment(
                Money::ofMinor($amountMinor, $folio->currency()),
                $businessDate,
                'adjustment',
                $userId,
            ),
        );
    }

    public function reverse(
        int $reservationId,
        int $entryId,
        int $amountMinor,
        string $businessDate,
        ?int $userId = null,
    ): FolioDTO {
        return $this->post(
            $reservationId,
            function (Folio $folio) use ($entryId, $amountMinor, $businessDate, $userId): FolioEntry {
                $entry = null;

                foreach ($folio->entries() as $candidate) {
                    if ($candidate->id === $entryId) {
                        $entry = $candidate;

                        break;
                    }
                }

                if ($entry === null) {
                    throw new InvalidArgumentException("The entry {$entryId} does not belong to this folio.");
                }

                return $folio->reverse(
                    $entry,
                    Money::ofMinor($amountMinor, $folio->currency()),
                    $businessDate,
                    'reversal',
                    $userId,
                );
            },
        );
    }

    public function balance(int $reservationId): int
    {
        return $this->folioOf($reservationId)->balance()->amountMinor;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function entries(int $reservationId): array
    {
        $entries = [];

        foreach ($this->folioOf($reservationId)->entries() as $entry) {
            $entries[] = [
                'id' => $entry->id,
                'type' => $entry->type->value,
                'amount_minor' => $entry->amount->amountMinor,
                'currency' => $entry->amount->getCurrency()->getCurrencyCode(),
                'business_date' => $entry->businessDate,
                'reversal_of_id' => $entry->reversalOfId,
                'source' => $entry->source,
            ];
        }

        return $entries;
    }

    public function close(int $reservationId): FolioDTO
    {
        return DB::transaction(function () use ($reservationId): FolioDTO {
            $folio = $this->folioOf($reservationId);
            $folio->close();

            $this->folios->save($folio);

            return $this->toDto($folio);
        });
    }

    public function closeBusinessDate(int $propertyId, string $date): void
    {
        $organizationId = $this->organizationContext->current()?->organizationId;

        if ($organizationId === null) {
            throw new InvalidArgumentException('A tenant context is required to close a business date.');
        }

        $this->folios->closeBusinessDate($organizationId, $propertyId, $date);
    }

    /**
     * Post an entry and write it.
     *
     * @param  callable(Folio): FolioEntry  $posting
     */
    private function post(int $reservationId, callable $posting): FolioDTO
    {
        return DB::transaction(function () use ($reservationId, $posting): FolioDTO {
            $folio = $this->folioOf($reservationId);

            $entry = $posting($folio);

            $this->persist($folio, $entry);

            return $this->toDto($folio);
        });
    }

    /**
     * The folio of a reservation, opened when it does not exist yet.
     */
    private function folioOf(int $reservationId): Folio
    {
        $reservation = $this->reservations->find($reservationId);

        if ($reservation === null) {
            throw new InvalidArgumentException("The reservation {$reservationId} does not exist.");
        }

        return $this->folios->open(
            organizationId: $reservation->organizationId,
            propertyId: $reservation->propertyId,
            reservationId: $reservationId,
            currency: $reservation->currency,
        );
    }

    private function persist(Folio $folio, FolioEntry $entry): void
    {
        $this->folios->save($folio);

        $persisted = $this->folios->appendEntry($folio, $entry);

        $folio->assignEntryId(count($folio->entries()) - 1, $persisted->id);
    }

    private function toDto(Folio $folio): FolioDTO
    {
        $entries = [];

        foreach ($folio->entries() as $entry) {
            $entries[] = [
                'id' => $entry->id,
                'type' => $entry->type->value,
                'amount_minor' => $entry->amount->amountMinor,
                'currency' => $entry->amount->getCurrency()->getCurrencyCode(),
                'business_date' => $entry->businessDate,
                'reversal_of_id' => $entry->reversalOfId,
                'source' => $entry->source,
            ];
        }

        return new FolioDTO(
            id: (int) $folio->id,
            organizationId: $folio->organizationId,
            propertyId: $folio->propertyId,
            reservationId: $folio->reservationId,
            status: $folio->status()->value,
            currency: $folio->currency,
            balanceMinor: $folio->balance()->amountMinor,
            entries: $entries,
        );
    }
}
