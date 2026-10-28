<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Infrastructure\Models\InventoryDay;
use App\Modules\Inventory\Infrastructure\Models\RatePlan;
use App\Modules\Inventory\Infrastructure\Models\RatePrice;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use App\Modules\Reservations\Contracts\ReservationService;
use App\Modules\Reservations\Domain\Exceptions\ReservationNotAvailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Creating a reservation for the staff of the hotel (task P2-02).
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create();

    Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'property_id' => null,
        'role' => Role::Owner,
    ]);

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
            'max_adults' => 4,
            'max_children' => 2,
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

it('creates a reservation and takes the room out of the inventory', function (): void {
    $reservation = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->create(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->ratePlan->id,
            arrival: '2026-11-01',
            departure: '2026-11-04',
            adults: 2,
            children: 0,
            guestId: 7,
            idempotencyKey: 'staff-key-1',
            userId: $this->user->id,
        ),
    );

    expect($reservation->code)->toStartWith('RSV-')
        ->and($reservation->status)->toBe('confirmed')
        ->and($reservation->source)->toBe('pms')
        ->and($reservation->arrival)->toBe('2026-11-01')
        ->and($reservation->departure)->toBe('2026-11-04')
        ->and($reservation->totalMinor)->toBe(1_500_000)
        ->and($reservation->currency)->toBe('IDR')
        ->and($reservation->rooms)->toHaveCount(1)
        ->and($reservation->rooms[0]['nights'])->toHaveCount(3);

    // The room is sold, not held: the hold was committed.
    $day = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', '2026-11-01')
            ->first(),
    );

    expect($day?->sold_units)->toBe(1)
        ->and($day?->held_units)->toBe(0);

    // The trail of the reservation and the audit of the change.
    expect(DB::table('reservation_events')->where('reservation_id', $reservation->id)->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('event', 'reservation.created')->count())->toBe(1);
});

it('cannot create a reservation when the room type is not available', function (): void {
    // The room type has no room at all.
    $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => $this->roomType->update(['total_units' => 0]),
    );

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->inventory->materializeDays(
            propertyId: $this->property->id,
            startDate: '2026-11-01',
            endDate: '2026-11-05',
        ),
    );

    expect(fn (): mixed => $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->create(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->ratePlan->id,
            arrival: '2026-11-01',
            departure: '2026-11-04',
            adults: 2,
            children: 0,
            guestId: 7,
            idempotencyKey: 'staff-key-2',
            userId: $this->user->id,
        ),
    ))->toThrow(ReservationNotAvailableException::class);

    expect(DB::table('reservations')->count())->toBe(0);
});

it('cannot create a reservation when the nights are restricted', function (): void {
    // Closed to arrival on the first night.
    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->inventory->setRestrictions(
            propertyId: $this->property->id,
            ratePlanId: $this->ratePlan->id,
            roomTypeId: $this->roomType->id,
            startDate: '2026-11-01',
            endDate: '2026-11-01',
            cta: true,
        ),
    );

    expect(fn (): mixed => $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->create(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->ratePlan->id,
            arrival: '2026-11-01',
            departure: '2026-11-04',
            adults: 2,
            children: 0,
            guestId: 7,
            idempotencyKey: 'staff-key-3',
            userId: $this->user->id,
        ),
    ))->toThrow(ReservationNotAvailableException::class);

    expect(DB::table('reservations')->count())->toBe(0);
});

it('creates one reservation when the same request is sent twice', function (): void {
    $create = fn (): mixed => $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->reservations->create(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->ratePlan->id,
            arrival: '2026-11-01',
            departure: '2026-11-04',
            adults: 2,
            children: 0,
            guestId: 7,
            idempotencyKey: 'staff-key-4',
            userId: $this->user->id,
        ),
    );

    $first = $create();
    $second = $create();

    expect($first->id)->toBe($second->id)
        ->and(DB::table('reservations')->count())->toBe(1)
        // The room was taken once, not twice.
        ->and(DB::table('inventory_holds')->count())->toBe(1);

    $day = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', '2026-11-01')
            ->first(),
    );

    expect($day?->sold_units)->toBe(1);
});
