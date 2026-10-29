<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Infrastructure\Models\InventoryDay;
use App\Modules\Inventory\Infrastructure\Models\RatePlan;
use App\Modules\Inventory\Infrastructure\Models\RatePrice;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use App\Modules\Reservations\Contracts\ReservationService;
use App\Modules\Reservations\Domain\Exceptions\ReservationNotAvailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Modifying a reservation (task P2-03).
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->context = app(OrganizationContext::class);
    $this->inventory = app(InventoryService::class);
    $this->reservations = app(ReservationService::class);

    $this->property = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => Property::factory()->create(['organization_id' => $this->org->id]),
    );

    $this->roomType = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => RoomType::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'total_units' => 2,
            'base_occupancy' => 2,
        ]),
    );

    $this->otherRoomType = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => RoomType::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'total_units' => 2,
            'base_occupancy' => 2,
        ]),
    );

    $this->ratePlan = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => RatePlan::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'code' => 'BAR',
            'name' => 'Best Available Rate',
        ]),
    );

    foreach (['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05'] as $date) {
        $this->context->swap(
            TenantContext::forOrganization($this->org->id),
            fn () => RatePrice::factory()->create([
                'organization_id' => $this->org->id,
                'property_id' => $this->property->id,
                'rate_plan_id' => $this->ratePlan->id,
                'room_type_id' => $this->roomType->id,
                'date' => $date,
                'base_price_minor' => 500_000,
                'currency' => 'IDR',
                'extra_adult_price_minor' => 100_000,
                'extra_child_price_minor' => 50_000,
            ]),
        );
    }

    // The other room type has its own prices: the reservation may move
    // to it.
    foreach (['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05'] as $date) {
        $this->context->swap(
            TenantContext::forOrganization($this->org->id),
            fn () => RatePrice::factory()->create([
                'organization_id' => $this->org->id,
                'property_id' => $this->property->id,
                'rate_plan_id' => $this->ratePlan->id,
                'room_type_id' => $this->otherRoomType->id,
                'date' => $date,
                'base_price_minor' => 700_000,
                'currency' => 'IDR',
                'extra_adult_price_minor' => 100_000,
                'extra_child_price_minor' => 50_000,
            ]),
        );
    }

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->inventory->materializeDays(
            propertyId: $this->property->id,
            startDate: '2026-11-01',
            endDate: '2026-11-06',
        ),
    );
});

/**
 * A reservation of two nights.
 */
function twoNightReservation(object $test): mixed
{
    return $test->context->swap(
        TenantContext::forOrganizationAndProperty($test->org->id, $test->property->id),
        fn () => $test->reservations->create(
            propertyId: $test->property->id,
            roomTypeId: $test->roomType->id,
            ratePlanId: $test->ratePlan->id,
            arrival: '2026-11-01',
            departure: '2026-11-03',
            adults: 2,
            children: 0,
            guestId: 7,
            idempotencyKey: 'modify-base-'.$test->org->id,
        ),
    );
}

function soldUnits(object $test, int $roomTypeId, string $date): ?int
{
    $day = $test->context->swap(
        TenantContext::forOrganizationAndProperty($test->org->id, $test->property->id),
        fn () => InventoryDay::query()
            ->where('room_type_id', $roomTypeId)
            ->where('date', $date)
            ->first(),
    );

    return $day?->sold_units;
}

it('extends the stay and takes the extra night', function (): void {
    $reservation = twoNightReservation($this);

    $modification = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->modify(
            reservationId: $reservation->id,
            departure: '2026-11-04',
        ),
    );

    expect($modification->nightsAdded)->toBe(['2026-11-03'])
        ->and($modification->nightsRemoved)->toBe([])
        ->and($modification->totalMinor)->toBe(1_500_000)
        ->and($modification->priceDifferenceMinor())->toBe(500_000)
        ->and($modification->reservation->departure)->toBe('2026-11-04');

    expect(soldUnits($this, $this->roomType->id, '2026-11-03'))->toBe(1)
        ->and(soldUnits($this, $this->roomType->id, '2026-11-01'))->toBe(1);
});

it('shortens the stay and gives the night back', function (): void {
    $reservation = twoNightReservation($this);

    $modification = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->modify(
            reservationId: $reservation->id,
            departure: '2026-11-02',
        ),
    );

    expect($modification->nightsRemoved)->toBe(['2026-11-02'])
        ->and($modification->nightsAdded)->toBe([])
        ->and($modification->totalMinor)->toBe(500_000)
        ->and($modification->priceDifferenceMinor())->toBe(-500_000);

    // The night went back to the pool.
    expect(soldUnits($this, $this->roomType->id, '2026-11-02'))->toBe(0)
        ->and(soldUnits($this, $this->roomType->id, '2026-11-01'))->toBe(1);
});

it('moves the reservation to another room type', function (): void {
    $reservation = twoNightReservation($this);

    $modification = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->modify(
            reservationId: $reservation->id,
            roomTypeId: $this->otherRoomType->id,
        ),
    );

    expect($modification->nightsRemoved)->toBe(['2026-11-01', '2026-11-02'])
        ->and($modification->nightsAdded)->toBe(['2026-11-01', '2026-11-02']);

    // The old room type has its nights back, the new one has them sold.
    expect(soldUnits($this, $this->roomType->id, '2026-11-01'))->toBe(0)
        ->and(soldUnits($this, $this->otherRoomType->id, '2026-11-01'))->toBe(1);
});

it('leaves the reservation intact when the new nights are not available', function (): void {
    $reservation = twoNightReservation($this);

    // The other room type has no room at all.
    $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => $this->otherRoomType->update(['total_units' => 0]),
    );

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->inventory->materializeDays(
            propertyId: $this->property->id,
            startDate: '2026-11-01',
            endDate: '2026-11-06',
        ),
    );

    expect(fn (): mixed => $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->modify(
            reservationId: $reservation->id,
            roomTypeId: $this->otherRoomType->id,
        ),
    ))->toThrow(ReservationNotAvailableException::class);

    // The reservation is exactly as it was.
    $after = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->find($reservation->id),
    );

    expect($after->arrival)->toBe('2026-11-01')
        ->and($after->departure)->toBe('2026-11-03')
        ->and($after->totalMinor)->toBe(1_000_000)
        ->and($after->rooms[0]['room_type_id'])->toBe($this->roomType->id);

    // The inventory is exactly as it was.
    expect(soldUnits($this, $this->roomType->id, '2026-11-01'))->toBe(1)
        ->and(soldUnits($this, $this->roomType->id, '2026-11-02'))->toBe(1)
        ->and(soldUnits($this, $this->otherRoomType->id, '2026-11-01'))->toBe(0);
});
