<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Domain\Exceptions\OutsideMaterializedWindowException;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

/**
 * The midnight boundary of a property.
 *
 * A stay date is the night of a hotel, so the day of a hotel starts when
 * its own clock says so. The same instant is a different day for two
 * properties in two timezones, and the booking window has to follow the
 * property and not the server.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // 17:00 UTC: it is already the next day in Bali (UTC+8), and still
    // the same day in Los Angeles (UTC-7 in October).
    Date::setTestNow('2026-10-27 17:00:00');

    $this->organization = Organization::factory()->create();
    $this->context = app(OrganizationContext::class);
    $this->inventoryService = app(InventoryService::class);

    $this->bali = Property::factory()->create([
        'organization_id' => $this->organization->id,
        'timezone' => 'Asia/Makassar',
    ]);

    $this->losAngeles = Property::factory()->create([
        'organization_id' => $this->organization->id,
        'timezone' => 'America/Los_Angeles',
    ]);

    $roomTypeOf = function (Property $property): RoomType {
        $roomType = RoomType::factory()->create([
            'organization_id' => $property->organization_id,
            'property_id' => $property->id,
            'total_units' => 5,
        ]);

        app(OrganizationContext::class)->swap(
            TenantContext::forOrganizationAndProperty($property->organization_id, $property->id),
            fn (): mixed => app(InventoryService::class)->materializeDays(
                propertyId: $property->id,
                startDate: '2026-10-27',
                endDate: '2026-11-05',
                roomTypeIds: [$roomType->id],
            ),
        );

        return $roomType;
    };

    $this->baliRoomType = $roomTypeOf($this->bali);
    $this->losAngelesRoomType = $roomTypeOf($this->losAngeles);
});

afterEach(function (): void {
    Date::setTestNow();
});

it('refuses a night that has already started in the timezone of the property', function (): void {
    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->organization->id, $this->bali->id),
        fn (): mixed => $this->inventoryService->hold(
            propertyId: $this->bali->id,
            roomTypeId: $this->baliRoomType->id,
            checkIn: '2026-10-27',
            checkOut: '2026-10-28',
            units: 1,
            idempotencyKey: 'bali-past-night',
        ),
    );
})->throws(OutsideMaterializedWindowException::class);

it('accepts the same night for a property whose day has not started yet', function (): void {
    $hold = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->organization->id, $this->losAngeles->id),
        fn (): mixed => $this->inventoryService->hold(
            propertyId: $this->losAngeles->id,
            roomTypeId: $this->losAngelesRoomType->id,
            checkIn: '2026-10-27',
            checkOut: '2026-10-28',
            units: 1,
            idempotencyKey: 'la-current-night',
        ),
    );

    expect($hold)->not->toBeNull();
});

it('materializes the window of the property, not the window of the server', function (): void {
    // A room type that was never materialized: only the default window
    // can fill it.
    $roomType = RoomType::factory()->create([
        'organization_id' => $this->bali->organization_id,
        'property_id' => $this->bali->id,
        'total_units' => 5,
    ]);

    $materialized = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->organization->id, $this->bali->id),
        fn (): mixed => $this->inventoryService->materializeDays(
            propertyId: $this->bali->id,
            startDate: null,
            endDate: null,
            roomTypeIds: [$roomType->id],
        ),
    );

    $baliDates = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->organization->id, $this->bali->id),
        fn (): array => $this->inventoryService->getAvailability(
            propertyId: $this->bali->id,
            roomTypeId: $roomType->id,
            startDate: '2026-10-27',
            endDate: '2026-10-29',
        ),
    );

    $dates = array_map(static fn ($day): string => $day->date, $baliDates);

    // The Bali window starts on the 28th, in Bali: the 27th is already
    // the past there, so it was never materialized.
    expect($materialized)->toBeGreaterThan(0)
        ->and($dates)->toContain('2026-10-28')
        ->and($dates)->not->toContain('2026-10-27');
});
