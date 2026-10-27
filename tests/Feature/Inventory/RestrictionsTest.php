<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Infrastructure\Models\RatePlan;
use App\Modules\Inventory\Infrastructure\Models\RatePrice;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->property = Property::factory()->create(['organization_id' => $this->org->id]);
    $this->user = User::factory()->create();

    $this->context = app(OrganizationContext::class);
    $this->tenant = TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id);

    $this->inventoryService = app(InventoryService::class);

    $this->context->swap($this->tenant, function (): void {
        $this->roomType = RoomType::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'total_units' => 10,
        ]);

        $this->ratePlan = RatePlan::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
        ]);

        // Materialize 10 days
        $this->inventoryService->materializeDays(
            propertyId: $this->property->id,
            startDate: '2026-11-01',
            endDate: '2026-11-10',
            roomTypeIds: [$this->roomType->id],
        );

        // Setup base prices for all days
        for ($i = 1; $i <= 10; $i++) {
            $dateStr = sprintf('2026-11-%02d', $i);
            RatePrice::factory()->create([
                'organization_id' => $this->org->id,
                'property_id' => $this->property->id,
                'rate_plan_id' => $this->ratePlan->id,
                'room_type_id' => $this->roomType->id,
                'date' => $dateStr,
                'base_price_minor' => 1_000_000_00,
            ]);
        }
    });
});

it('enforces Closed to Arrival (CTA) restriction on check-in date', function (): void {
    $this->context->swap($this->tenant, function (): void {
        // Set CTA on 2026-11-02
        $this->inventoryService->setRestrictions(
            propertyId: $this->property->id,
            ratePlanId: $this->ratePlan->id,
            roomTypeId: $this->roomType->id,
            startDate: '2026-11-02',
            endDate: '2026-11-02',
            cta: true,
        );

        // Check-in on 2026-11-02 is blocked
        $quoteBlocked = $this->inventoryService->quote(
            propertyId: $this->property->id,
            roomTypeIds: [$this->roomType->id],
            checkInDate: '2026-11-02',
            checkOutDate: '2026-11-04',
            adults: 2,
            ratePlanId: $this->ratePlan->id,
        );

        $rtQuoteBlocked = $quoteBlocked->roomTypes[0];
        expect($rtQuoteBlocked->isAvailable)->toBeFalse()
            ->and($rtQuoteBlocked->restrictionViolations)->toContain('Closed to Arrival (CTA) on 2026-11-02.');

        // Check-in on 2026-11-01 departing on 2026-11-03 traverses 2026-11-02 and is allowed
        $quoteAllowed = $this->inventoryService->quote(
            propertyId: $this->property->id,
            roomTypeIds: [$this->roomType->id],
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            adults: 2,
            ratePlanId: $this->ratePlan->id,
        );

        $rtQuoteAllowed = $quoteAllowed->roomTypes[0];
        expect($rtQuoteAllowed->isAvailable)->toBeTrue()
            ->and($rtQuoteAllowed->restrictionViolations)->toBeEmpty();
    });
});

it('enforces Closed to Departure (CTD) restriction on check-out date', function (): void {
    $this->context->swap($this->tenant, function (): void {
        // Set CTD on 2026-11-04
        $this->inventoryService->setRestrictions(
            propertyId: $this->property->id,
            ratePlanId: $this->ratePlan->id,
            roomTypeId: $this->roomType->id,
            startDate: '2026-11-04',
            endDate: '2026-11-04',
            ctd: true,
        );

        // Check-out on 2026-11-04 is blocked
        $quoteBlocked = $this->inventoryService->quote(
            propertyId: $this->property->id,
            roomTypeIds: [$this->roomType->id],
            checkInDate: '2026-11-02',
            checkOutDate: '2026-11-04',
            adults: 2,
            ratePlanId: $this->ratePlan->id,
        );

        $rtQuoteBlocked = $quoteBlocked->roomTypes[0];
        expect($rtQuoteBlocked->isAvailable)->toBeFalse()
            ->and($rtQuoteBlocked->restrictionViolations)->toContain('Closed to Departure (CTD) on 2026-11-04.');

        // Check-out on 2026-11-05 is allowed
        $quoteAllowed = $this->inventoryService->quote(
            propertyId: $this->property->id,
            roomTypeIds: [$this->roomType->id],
            checkInDate: '2026-11-02',
            checkOutDate: '2026-11-05',
            adults: 2,
            ratePlanId: $this->ratePlan->id,
        );

        $rtQuoteAllowed = $quoteAllowed->roomTypes[0];
        expect($rtQuoteAllowed->isAvailable)->toBeTrue();
    });
});

it('enforces Stop Sell restriction across stay dates', function (): void {
    $this->context->swap($this->tenant, function (): void {
        // Set Stop Sell on 2026-11-03
        $this->inventoryService->setRestrictions(
            propertyId: $this->property->id,
            ratePlanId: $this->ratePlan->id,
            roomTypeId: $this->roomType->id,
            startDate: '2026-11-03',
            endDate: '2026-11-03',
            stopSell: true,
        );

        $quote = $this->inventoryService->quote(
            propertyId: $this->property->id,
            roomTypeIds: [$this->roomType->id],
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-05',
            adults: 2,
            ratePlanId: $this->ratePlan->id,
        );

        $rtQuote = $quote->roomTypes[0];
        expect($rtQuote->isAvailable)->toBeFalse()
            ->and($rtQuote->restrictionViolations)->toContain('Stop sell active on 2026-11-03.');
    });
});

it('enforces min_stay and max_stay restrictions', function (): void {
    $this->context->swap($this->tenant, function (): void {
        // Set min_stay = 3 and max_stay = 5 from 2026-11-01 to 2026-11-05
        $this->inventoryService->setRestrictions(
            propertyId: $this->property->id,
            ratePlanId: $this->ratePlan->id,
            roomTypeId: $this->roomType->id,
            startDate: '2026-11-01',
            endDate: '2026-11-05',
            minStay: 3,
            maxStay: 5,
        );

        // 2 nights: rejected by min_stay
        $quoteTooShort = $this->inventoryService->quote(
            propertyId: $this->property->id,
            roomTypeIds: [$this->roomType->id],
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            adults: 2,
            ratePlanId: $this->ratePlan->id,
        );
        expect($quoteTooShort->roomTypes[0]->isAvailable)->toBeFalse();

        // 4 nights: valid within [3, 5]
        $quoteValid = $this->inventoryService->quote(
            propertyId: $this->property->id,
            roomTypeIds: [$this->roomType->id],
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-05',
            adults: 2,
            ratePlanId: $this->ratePlan->id,
        );
        expect($quoteValid->roomTypes[0]->isAvailable)->toBeTrue();

        // 6 nights: rejected by max_stay
        $quoteTooLong = $this->inventoryService->quote(
            propertyId: $this->property->id,
            roomTypeIds: [$this->roomType->id],
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-07',
            adults: 2,
            ratePlanId: $this->ratePlan->id,
        );
        expect($quoteTooLong->roomTypes[0]->isAvailable)->toBeFalse();
    });
});
