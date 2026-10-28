<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Infrastructure\Models\InventoryDay;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Giving the sold units of a night back to availability (task P2-03).
 *
 * A reservation that changed its nights no longer covers the nights it
 * dropped: they are sold, and they go back to the pool.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->context = app(OrganizationContext::class);

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
        ]),
    );

    $this->inventory = app(InventoryService::class);

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->inventory->materializeDays(
            propertyId: $this->property->id,
            startDate: '2026-11-01',
            endDate: '2026-11-05',
        ),
    );
});

it('gives the sold units of the nights back to availability', function (): void {
    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->inventory->releaseSoldUnits(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            dates: ['2026-11-01', '2026-11-02'],
            units: 1,
        ),
    );

    $day = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', '2026-11-01')
            ->first(),
    );

    // The night was not sold: the counter stays at zero, never below.
    expect($day?->sold_units)->toBe(0);
});

it('takes the sold units of the nights back', function (): void {
    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        function (): void {
            $hold = $this->inventory->hold(
                propertyId: $this->property->id,
                roomTypeId: $this->roomType->id,
                checkIn: '2026-11-01',
                checkOut: '2026-11-03',
                units: 1,
                idempotencyKey: 'release-sold-key',
            );

            $this->inventory->commitHold($hold->id);
        },
    );

    $before = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', '2026-11-01')
            ->first(),
    );

    expect($before?->sold_units)->toBe(1);

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => $this->inventory->releaseSoldUnits(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            dates: ['2026-11-01', '2026-11-02'],
            units: 1,
        ),
    );

    $after = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', '2026-11-01')
            ->first(),
    );

    expect($after?->sold_units)->toBe(0);
});