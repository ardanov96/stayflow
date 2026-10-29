<?php

declare(strict_types=1);

namespace App\Modules\Folio\Infrastructure\Repositories;

use App\Modules\Folio\Contracts\FolioRepository;
use App\Modules\Folio\Domain\Folio;
use App\Modules\Folio\Domain\FolioEntry;
use App\Modules\Folio\Domain\FolioEntryType;
use App\Modules\Folio\Domain\FolioStatus;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The folio of a reservation, on the database.
 *
 * The ledger is append-only: there is no way to update or to delete an
 * entry here, and the database refuses it as well.
 */
final class EloquentFolioRepository implements FolioRepository
{
    public function find(int $organizationId, int $folioId): ?Folio
    {
        $row = DB::table('folios')
            ->where('organization_id', $organizationId)
            ->find($folioId);

        return $row === null ? null : $this->toFolio($row);
    }

    public function findForReservation(int $organizationId, int $reservationId): ?Folio
    {
        $row = DB::table('folios')
            ->where('organization_id', $organizationId)
            ->where('reservation_id', $reservationId)
            ->first();

        return $row === null ? null : $this->toFolio($row);
    }

    public function open(int $organizationId, int $propertyId, int $reservationId, string $currency): Folio
    {
        $existing = $this->findForReservation($organizationId, $reservationId);

        if ($existing !== null) {
            return $existing;
        }

        $id = DB::table('folios')->insertGetId([
            'organization_id' => $organizationId,
            'property_id' => $propertyId,
            'reservation_id' => $reservationId,
            'status' => FolioStatus::Open->value,
            'currency' => $currency,
            'balance_minor' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->find($organizationId, (int) $id)
            ?? throw new RuntimeException('The folio could not be read back.');
    }

    public function save(Folio $folio): void
    {
        DB::table('folios')
            ->where('id', $folio->id)
            ->update([
                'status' => $folio->status()->value,
                'balance_minor' => $folio->balance()->amountMinor,
                'updated_at' => now(),
            ]);
    }

    public function appendEntry(Folio $folio, FolioEntry $entry): FolioEntry
    {
        $id = DB::table('folio_entries')->insertGetId([
            'organization_id' => $folio->organizationId,
            'folio_id' => $folio->id,
            'type' => $entry->type->value,
            'amount_minor' => $entry->amount->amountMinor,
            'currency' => $entry->amount->getCurrency()->getCurrencyCode(),
            'business_date' => $entry->businessDate,
            'posted_at' => now(),
            'reversal_of_id' => $entry->reversalOfId,
            'source' => $entry->source,
            'tax_snapshot' => $entry->taxSnapshot === null ? null : json_encode($entry->taxSnapshot),
            'created_by' => $entry->createdBy,
            'created_at' => now(),
        ]);

        return new FolioEntry(
            id: (int) $id,
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

    public function recordPayment(
        int $organizationId,
        int $folioId,
        string $method,
        Money $amount,
        string $idempotencyKey,
        ?string $gatewayRef = null,
    ): void {
        DB::table('payments')->insert([
            'organization_id' => $organizationId,
            'folio_id' => $folioId,
            'method' => $method,
            'amount_minor' => $amount->amountMinor,
            'currency' => $amount->getCurrency()->getCurrencyCode(),
            'status' => 'paid',
            'gateway_ref' => $gatewayRef,
            'idempotency_key' => $idempotencyKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function businessDateIsClosed(int $organizationId, int $propertyId, string $date): bool
    {
        return DB::table('business_date_closures')
            ->where('organization_id', $organizationId)
            ->where('property_id', $propertyId)
            ->where('date', $date)
            ->exists();
    }

    public function closeBusinessDate(int $organizationId, int $propertyId, string $date): void
    {
        DB::table('business_date_closures')->insertOrIgnore([
            'organization_id' => $organizationId,
            'property_id' => $propertyId,
            'date' => $date,
            'closed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function toFolio(object $row): Folio
    {
        $entries = DB::table('folio_entries')
            ->where('folio_id', $row->id)
            ->orderBy('id')
            ->get()
            ->map(fn (object $entry): FolioEntry => new FolioEntry(
                id: (int) $entry->id,
                folioId: (int) $entry->folio_id,
                type: FolioEntryType::from((string) $entry->type),
                amount: Money::ofMinor((int) $entry->amount_minor, (string) $entry->currency),
                businessDate: substr((string) $entry->business_date, 0, 10),
                reversalOfId: $entry->reversal_of_id !== null ? (int) $entry->reversal_of_id : null,
                source: $entry->source !== null ? (string) $entry->source : null,
                taxSnapshot: $entry->tax_snapshot !== null ? (array) json_decode((string) $entry->tax_snapshot, true) : null,
                createdBy: $entry->created_by !== null ? (int) $entry->created_by : null,
            ))
            ->all();

        /** @var array<string, true> $closedDates */
        $closedDates = [];

        foreach (DB::table('business_date_closures')
            ->where('organization_id', $row->organization_id)
            ->where('property_id', $row->property_id)
            ->pluck('date') as $date) {
            $closedDates[substr((string) $date, 0, 10)] = true;
        }

        return new Folio(
            organizationId: (int) $row->organization_id,
            propertyId: (int) $row->property_id,
            reservationId: (int) $row->reservation_id,
            currency: (string) $row->currency,
            id: (int) $row->id,
            status: FolioStatus::from((string) $row->status),
            balance: Money::ofMinor((int) $row->balance_minor, (string) $row->currency),
            entries: $entries,
            closedBusinessDates: $closedDates,
        );
    }
}
