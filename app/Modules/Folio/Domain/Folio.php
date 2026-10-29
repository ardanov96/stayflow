<?php

declare(strict_types=1);

namespace App\Modules\Folio\Domain;

use App\Modules\Folio\Domain\Exceptions\ClosedBusinessDateException;
use App\Modules\Folio\Domain\Exceptions\ClosedFolioException;
use App\Modules\Folio\Domain\Exceptions\UnbalancedFolioException;
use App\Support\Money\Money;
use InvalidArgumentException;

/**
 * The folio of a reservation: the ledger of what the guest owes and of
 * what the guest paid.
 *
 * The ledger is **append-only**. A correction is never an update: it is a
 * new entry that reverses the old one, so the trail of the folio says
 * exactly what happened and when.
 *
 * Three rules, and they are the invariant 3 and the invariant 4 of
 * docs/DOMAIN_MODEL.md section 3:
 *
 *  - the balance is always the sum of the entries;
 *  - a reversal never exceeds what is left to reverse of its entry;
 *  - a closed business date accepts no new entry.
 */
final class Folio
{
    /**
     * @var list<FolioEntry>
     */
    private array $entries = [];

    /**
     * @var array<string, true>
     */
    private array $closedBusinessDates = [];

    private Money $balance;

    /**
     * @param  list<FolioEntry>  $entries
     * @param  array<string, true>  $closedBusinessDates
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly int $propertyId,
        public readonly int $reservationId,
        public readonly string $currency,
        public readonly ?int $id = null,
        private FolioStatus $status = FolioStatus::Open,
        ?Money $balance = null,
        array $entries = [],
        array $closedBusinessDates = [],
    ) {
        $this->balance = $balance ?? Money::zero($currency);
        $this->entries = $entries;
        $this->closedBusinessDates = $closedBusinessDates;
    }

    public function balance(): Money
    {
        return $this->balance;
    }

    /**
     * The currency of the folio. Every amount of the ledger is in it.
     */
    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * @return list<FolioEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function status(): FolioStatus
    {
        return $this->status;
    }

    /**
     * Whether the balance is exactly the sum of the entries.
     */
    public function isBalanced(): bool
    {
        return $this->balance->equals($this->sumOfEntries());
    }

    /**
     * What the guest owes: the room, the extras.
     *
     * @param  array<string, mixed>|null  $taxSnapshot
     */
    public function postCharge(
        Money $amount,
        string $businessDate,
        ?string $source = null,
        ?int $actorId = null,
        ?array $taxSnapshot = null,
    ): FolioEntry {
        return $this->append(
            FolioEntryType::Charge,
            $amount,
            $businessDate,
            null,
            $source,
            $taxSnapshot,
            $actorId,
        );
    }

    /**
     * A tax on a charge.
     *
     * @param  array<string, mixed>|null  $taxSnapshot
     */
    public function postTax(
        Money $amount,
        string $businessDate,
        ?string $source = null,
        ?int $actorId = null,
        ?array $taxSnapshot = null,
    ): FolioEntry {
        return $this->append(
            FolioEntryType::Tax,
            $amount,
            $businessDate,
            null,
            $source,
            $taxSnapshot,
            $actorId,
        );
    }

    /**
     * What the guest paid. It lowers the balance.
     */
    public function postPayment(
        Money $amount,
        string $businessDate,
        ?string $source = null,
        ?int $actorId = null,
    ): FolioEntry {
        return $this->append(
            FolioEntryType::Payment,
            $amount->negate(),
            $businessDate,
            null,
            $source,
            null,
            $actorId,
        );
    }

    /**
     * What is given back to the guest. It lowers the balance.
     */
    public function postRefund(
        Money $amount,
        string $businessDate,
        ?string $source = null,
        ?int $actorId = null,
    ): FolioEntry {
        return $this->append(
            FolioEntryType::Refund,
            $amount->negate(),
            $businessDate,
            null,
            $source,
            null,
            $actorId,
        );
    }

    /**
     * A correction, in either direction.
     */
    public function postAdjustment(
        Money $amount,
        string $businessDate,
        ?string $source = null,
        ?int $actorId = null,
    ): FolioEntry {
        return $this->append(
            FolioEntryType::Adjustment,
            $amount,
            $businessDate,
            null,
            $source,
            null,
            $actorId,
        );
    }

    /**
     * Reverse an entry.
     *
     * The reversal never exceeds what is left to reverse of the entry it
     * reverses: the sum of the reversals of an entry can never be bigger
     * than the entry itself.
     */
    public function reverse(
        FolioEntry $entry,
        Money $amount,
        string $businessDate,
        ?string $source = null,
        ?int $actorId = null,
    ): FolioEntry {
        if ($entry->folioId !== ($this->id ?? 0) && $this->id !== null) {
            throw new InvalidArgumentException('The entry does not belong to this folio.');
        }

        $remaining = $this->remainingToReverse($entry);

        if ($amount->isNegative() || $amount->amountMinor > $remaining->amountMinor) {
            throw UnbalancedFolioException::reversalTooLarge($entry->id);
        }

        return $this->append(
            FolioEntryType::Reversal,
            $amount->negate(),
            $businessDate,
            $entry->id,
            $source,
            null,
            $actorId,
        );
    }

    /**
     * Close the folio: it accepts no new entry.
     */
    public function close(): void
    {
        $this->status = FolioStatus::Closed;
    }

    /**
     * Close a business date: it accepts no new entry.
     */
    public function closeBusinessDate(string $date): void
    {
        $this->closedBusinessDates[$date] = true;
    }

    public function isBusinessDateClosed(string $date): bool
    {
        return isset($this->closedBusinessDates[$date]);
    }

    /**
     * The identifier the database gave to an entry.
     *
     * The entry is created before it is written, so its identifier is
     * known only after the insert. The position in the ledger does not
     * change: an entry is never removed.
     */
    public function assignEntryId(int $position, int $id): void
    {
        $entry = $this->entries[$position] ?? null;

        if ($entry === null || $entry->id === $id) {
            return;
        }

        $this->entries[$position] = new FolioEntry(
            id: $id,
            folioId: $entry->folioId,
            type: $entry->type,
            amount: $entry->amount,
            businessDate: $entry->businessDate,
            reversalOfId: $entry->reversalOfId,
            source: $entry->source,
            taxSnapshot: $entry->taxSnapshot,
            createdBy: $entry->createdBy,
        );
    }

    /**
     * What is left to reverse of an entry.
     */
    public function remainingToReverse(FolioEntry $entry): Money
    {
        $reversed = Money::zero($this->currency);

        foreach ($this->entries as $candidate) {
            if ($candidate->reversalOfId !== $entry->id) {
                continue;
            }

            $reversed = $reversed->plus($candidate->amount->abs());
        }

        $left = $entry->amount->abs()->minus($reversed);

        return $left->isNegative() ? Money::zero($this->currency) : $left;
    }

    /**
     * @param  array<string, mixed>|null  $taxSnapshot
     */
    private function append(
        FolioEntryType $type,
        Money $amount,
        string $businessDate,
        ?int $reversalOfId,
        ?string $source,
        ?array $taxSnapshot,
        ?int $actorId,
    ): FolioEntry {
        if (! $this->status->acceptsEntries()) {
            throw ClosedFolioException::forFolio($this->id ?? 0);
        }

        if ($this->isBusinessDateClosed($businessDate)) {
            throw ClosedBusinessDateException::forDate($businessDate);
        }

        if ($amount->getCurrency()->getCurrencyCode() !== $this->currency) {
            throw UnbalancedFolioException::currencyMismatch($this->currency, $amount->getCurrency()->getCurrencyCode());
        }

        if ($amount->isZero()) {
            throw new InvalidArgumentException('An entry of the ledger cannot be zero.');
        }

        $entry = new FolioEntry(
            id: 0,
            folioId: $this->id ?? 0,
            type: $type,
            amount: $amount,
            businessDate: $businessDate,
            reversalOfId: $reversalOfId,
            source: $source,
            taxSnapshot: $taxSnapshot,
            createdBy: $actorId,
        );

        $this->entries[] = $entry;
        $this->balance = $this->balance->plus($amount);

        return $entry;
    }

    private function sumOfEntries(): Money
    {
        $sum = Money::zero($this->currency);

        foreach ($this->entries as $entry) {
            $sum = $sum->plus($entry->amount);
        }

        return $sum;
    }
}
