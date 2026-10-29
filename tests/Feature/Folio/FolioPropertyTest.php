<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Folio\Contracts\FolioService;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use App\Modules\Reservations\Infrastructure\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * The ledger of a folio, under a random sequence (task P2-09).
 *
 * The property is the one the invariant 3 of docs/DOMAIN_MODEL.md states:
 * **the balance is always the sum of the entries**. Whatever the guest
 * does — charges, taxes, payments, refunds, corrections and reversals —
 * the two never drift apart, and an entry is never touched.
 *
 * The sequence is generated with a fixed seed, so a failure is
 * reproducible.
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

it('keeps the balance equal to the sum of the entries, whatever the sequence', function (): void {
    mt_srand(20261029);

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        function (): void {
            $expected = 0;

            for ($step = 0; $step < 60; $step++) {
                $roll = mt_rand(1, 100);
                $amount = mt_rand(1, 500) * 1000;
                $date = sprintf('2026-11-%02d', mt_rand(1, 28));

                if ($roll <= 35) {
                    $this->folio->charge($this->reservation->id, $amount, $date, 'room');
                    $expected += $amount;
                } elseif ($roll <= 55) {
                    $this->folio->postTax($this->reservation->id, $amount, $date, 'tax');
                    $expected += $amount;
                } elseif ($roll <= 75) {
                    $this->folio->payment(
                        $this->reservation->id,
                        $amount,
                        'cash',
                        'payment-'.$step,
                        $date,
                    );
                    $expected -= $amount;
                } elseif ($roll <= 85) {
                    $this->folio->refund($this->reservation->id, $amount, $date);
                    $expected -= $amount;
                } elseif ($roll <= 95) {
                    $this->folio->adjust($this->reservation->id, $amount, $date);
                    $expected += $amount;
                } else {
                    $reversed = reverseSomething($this, $amount);

                    if ($reversed === null) {
                        continue;
                    }

                    $expected -= $reversed;
                }

                // The invariant, after every single step.
                $folioId = (int) DB::table('folios')
                    ->where('reservation_id', $this->reservation->id)
                    ->value('id');

                $entries = DB::table('folio_entries')->where('folio_id', $folioId)->get();
                $balance = (int) DB::table('folios')->where('id', $folioId)->value('balance_minor');

                expect($entries->sum('amount_minor'))->toBe($expected)
                    ->and($balance)->toBe($expected)
                    ->and($this->folio->balance($this->reservation->id))->toBe($expected);
            }
        },
    );
});

/**
 * Reverse a random entry, never more than what is left to reverse of it.
 *
 * @return int|null The amount that was reversed, null when nothing could be.
 */
function reverseSomething(object $test, int $amount): ?int
{
    $entries = $test->folio->entries($test->reservation->id);

    $candidates = [];

    foreach ($entries as $entry) {
        if ($entry['type'] !== 'charge' && $entry['type'] !== 'tax') {
            continue;
        }

        $reversed = 0;

        foreach ($entries as $other) {
            if ($other['reversal_of_id'] === $entry['id']) {
                $reversed += abs((int) $other['amount_minor']);
            }
        }

        $left = (int) $entry['amount_minor'] - $reversed;

        if ($left > 0) {
            $candidates[] = ['id' => $entry['id'], 'left' => $left];
        }
    }

    if ($candidates === []) {
        return null;
    }

    $chosen = $candidates[mt_rand(0, count($candidates) - 1)];
    $reversed = min($amount, $chosen['left']);

    $test->folio->reverse(
        $test->reservation->id,
        $chosen['id'],
        $reversed,
        '2026-11-28',
    );

    return $reversed;
}
