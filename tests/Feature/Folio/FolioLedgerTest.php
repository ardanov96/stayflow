<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Folio\Contracts\FolioService;
use App\Modules\Folio\Domain\Exceptions\ClosedBusinessDateException;
use App\Modules\Folio\Domain\Exceptions\ClosedFolioException;
use App\Modules\Folio\Domain\Exceptions\UnbalancedFolioException;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use App\Modules\Reservations\Infrastructure\Models\Reservation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * The ledger of a folio (task P2-09).
 *
 * The invariant 3 and the invariant 4 of docs/DOMAIN_MODEL.md section 3.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->context = app(OrganizationContext::class);

    $this->property = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => Property::factory()->create(['organization_id' => $this->org->id]),
    );

    $this->reservation = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => Reservation::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
        ]),
    );

    $this->folio = app(FolioService::class);
});

it('keeps the balance equal to the sum of the entries', function (): void {
    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        function () use (&$serviceBalance): void {
            $this->folio->charge($this->reservation->id, 1_000_000, '2026-11-01', 'room');
            $this->folio->postTax($this->reservation->id, 100_000, '2026-11-01', 'tax');
            $this->folio->payment(
                $this->reservation->id,
                500_000,
                'cash',
                'payment-1',
                '2026-11-01',
            );

            $serviceBalance = $this->folio->balance($this->reservation->id);
        },
    );

    $folioId = (int) DB::table('folios')->where('reservation_id', $this->reservation->id)->value('id');
    $entries = DB::table('folio_entries')->where('folio_id', $folioId)->get();
    $balance = (int) DB::table('folios')->where('id', $folioId)->value('balance_minor');

    expect($entries)->toHaveCount(3)
        ->and($balance)->toBe(600_000)
        ->and($balance)->toBe((int) $entries->sum('amount_minor'))
        ->and($serviceBalance)->toBe(600_000);
});

it('never updates and never deletes an entry', function (): void {
    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->folio->charge($this->reservation->id, 1_000_000, '2026-11-01', 'room'),
    );

    $entryId = (int) DB::table('folio_entries')->value('id');

    expect(fn () => DB::table('folio_entries')->where('id', $entryId)->update(['amount_minor' => 1]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('folio_entries')->where('id', $entryId)->delete())
        ->toThrow(QueryException::class);

    // The entry is exactly as it was posted.
    expect((int) DB::table('folio_entries')->where('id', $entryId)->value('amount_minor'))->toBe(1_000_000);
});

it('never reverses more than what is left to reverse', function (): void {
    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        function (): void {
            $this->folio->charge($this->reservation->id, 1_000_000, '2026-11-01', 'room');

            $entryId = (int) DB::table('folio_entries')->value('id');

            // The whole entry, then not a cent more.
            $this->folio->reverse($this->reservation->id, $entryId, 1_000_000, '2026-11-02');

            expect(fn () => $this->folio->reverse(
                $this->reservation->id,
                $entryId,
                1,
                '2026-11-02',
            ))->toThrow(UnbalancedFolioException::class);

            expect($this->folio->balance($this->reservation->id))->toBe(0);
        },
    );
});

it('accepts no new entry on a closed business date', function (): void {
    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        function (): void {
            $this->folio->closeBusinessDate($this->property->id, '2026-10-31');

            expect(fn () => $this->folio->charge(
                $this->reservation->id,
                1_000_000,
                '2026-10-31',
                'room',
            ))->toThrow(ClosedBusinessDateException::class);

            // Another day is fine.
            $this->folio->charge($this->reservation->id, 1_000_000, '2026-11-01', 'room');

            expect($this->folio->balance($this->reservation->id))->toBe(1_000_000);
        },
    );
});

it('accepts no new entry on a closed folio', function (): void {
    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        function (): void {
            $this->folio->charge($this->reservation->id, 1_000_000, '2026-11-01', 'room');
            $this->folio->close($this->reservation->id);

            expect(fn () => $this->folio->charge(
                $this->reservation->id,
                500_000,
                '2026-11-01',
                'room',
            ))->toThrow(ClosedFolioException::class);
        },
    );
});
