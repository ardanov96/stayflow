<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Catalog\Infrastructure\Models\RoomUnit;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Infrastructure\Models\RatePlan;
use App\Modules\Inventory\Infrastructure\Models\RatePrice;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use App\Modules\Reservations\Contracts\ReservationService;
use App\Modules\Reservations\Domain\Exceptions\RoomUnitNotAvailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Assigning a physical room to a reservation (task P2-05).
 *
 * The invariant 7 of docs/DOMAIN_MODEL.md section 3: *a room unit cannot
 * be assigned to two reservations on overlapping nights*.
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

    $this->unit201 = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => RoomUnit::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'number' => '201',
        ]),
    );

    $this->unit202 = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => RoomUnit::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'number' => '202',
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

    foreach (['2026-11-01', '2026-11-02', '2026-11-03'] as $date) {
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

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->inventory->materializeDays(
            propertyId: $this->property->id,
            startDate: '2026-11-01',
            endDate: '2026-11-05',
        ),
    );
});

function aReservation(object $test, string $key): mixed
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
            idempotencyKey: $key,
        ),
    );
}

function bookedNights(object $test, int $roomUnitId): int
{
    return DB::table('reservation_room_nights')
        ->where('room_unit_id', $roomUnitId)
        ->count();
}

it('assigns a physical room to the reservation', function (): void {
    $reservation = aReservation($this, 'assign-key');

    $updated = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->assignRoomUnit(
            reservationId: $reservation->id,
            roomTypeId: $this->roomType->id,
            roomUnitId: $this->unit201->id,
        ),
    );

    expect($updated->rooms[0]['room_unit_id'])->toBe($this->unit201->id)
        ->and(bookedNights($this, $this->unit201->id))->toBe(2);
});

it('refuses a room that is already booked for one of the nights', function (): void {
    $first = aReservation($this, 'first-key');

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->assignRoomUnit(
            reservationId: $first->id,
            roomTypeId: $this->roomType->id,
            roomUnitId: $this->unit201->id,
        ),
    );

    // A second reservation on the same nights.
    $second = aReservation($this, 'second-key');

    expect(fn (): mixed => $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->assignRoomUnit(
            reservationId: $second->id,
            roomTypeId: $this->roomType->id,
            roomUnitId: $this->unit201->id,
        ),
    ))->toThrow(RoomUnitNotAvailableException::class);

    // The invariant holds: the room is booked for one reservation only.
    expect(bookedNights($this, $this->unit201->id))->toBe(2);
});

it('takes the physical room away', function (): void {
    $reservation = aReservation($this, 'unassign-key');

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->assignRoomUnit(
            reservationId: $reservation->id,
            roomTypeId: $this->roomType->id,
            roomUnitId: $this->unit201->id,
        ),
    );

    $updated = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->unassignRoomUnit(
            reservationId: $reservation->id,
            roomTypeId: $this->roomType->id,
        ),
    );

    expect($updated->rooms[0]['room_unit_id'])->toBeNull()
        ->and(bookedNights($this, $this->unit201->id))->toBe(0);
});

it('moves the reservation to another physical room', function (): void {
    $reservation = aReservation($this, 'move-key');

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->assignRoomUnit(
            reservationId: $reservation->id,
            roomTypeId: $this->roomType->id,
            roomUnitId: $this->unit201->id,
        ),
    );

    $updated = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->moveRoomUnit(
            reservationId: $reservation->id,
            roomTypeId: $this->roomType->id,
            newRoomUnitId: $this->unit202->id,
        ),
    );

    expect($updated->rooms[0]['room_unit_id'])->toBe($this->unit202->id)
        ->and(bookedNights($this, $this->unit201->id))->toBe(0)
        ->and(bookedNights($this, $this->unit202->id))->toBe(2);
});

it('suggests the rooms that are free for the nights of the reservation', function (): void {
    $reservation = aReservation($this, 'suggest-key');

    $free = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->suggestRoomUnits(
            reservationId: $reservation->id,
            roomTypeId: $this->roomType->id,
        ),
    );

    expect($free)->toHaveCount(2);

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->assignRoomUnit(
            reservationId: $reservation->id,
            roomTypeId: $this->roomType->id,
            roomUnitId: $this->unit201->id,
        ),
    );

    $free = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->suggestRoomUnits(
            reservationId: $reservation->id,
            roomTypeId: $this->roomType->id,
        ),
    );

    // The room of the reservation itself is not free any more.
    expect($free)->toHaveCount(1)
        ->and($free[0]->id)->toBe($this->unit202->id);
});
