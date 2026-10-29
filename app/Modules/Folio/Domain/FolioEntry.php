<?php

declare(strict_types=1);

namespace App\Modules\Folio\Domain;

use App\Support\Money\Money;

/**
 * One entry of the ledger of a folio.
 *
 * An entry is never updated and never deleted: a correction is another
 * entry that reverses it. The database refuses the update and the delete.
 */
final readonly class FolioEntry
{
    /**
     * @param  array<string, mixed>|null  $taxSnapshot
     */
    public function __construct(
        public int $id,
        public int $folioId,
        public FolioEntryType $type,
        public Money $amount,
        public string $businessDate,
        public ?int $reversalOfId = null,
        public ?string $source = null,
        public ?array $taxSnapshot = null,
        public ?int $createdBy = null,
    ) {}

    /**
     * Whether the entry raises what the guest owes.
     */
    public function raisesTheBalance(): bool
    {
        return ! $this->type->lowersTheBalance();
    }
}
