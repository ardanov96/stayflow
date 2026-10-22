<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Domain\Policy\PolicyType;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\PropertyPolicy;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\PricingService;
use App\Modules\Inventory\Domain\Exceptions\RatePriceNotFoundException;
use App\Modules\Inventory\Domain\RatePlan\DerivationType;
use App\Modules\Inventory\Infrastructure\Models\RatePlan;
use App\Modules\Inventory\Infrastructure\Models\RatePrice;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    $this->property = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => Property::factory()->create(['organization_id' => $this->org->id])
    );

    $this->policy = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => PropertyPolicy::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'type' => PolicyType::Cancellation,
            'title' => 'Flexible Cancellation',
            'structured_terms' => ['free_cancellation_hours' => 48],
        ])
    );

    $this->roomType = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => RoomType::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'base_occupancy' => 2,
            'max_adults' => 4,
            'max_children' => 2,
        ])
    );

    $this->parentPlan = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => RatePlan::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'code' => 'BAR',
            'name' => 'Best Available Rate',
            'cancellation_policy_id' => $this->policy->id,
        ])
    );

    // Setup prices for Nov 1, 2, 3 (3 nights)
    foreach (['2026-11-01', '2026-11-02', '2026-11-03'] as $date) {
        $this->context->swap(
            TenantContext::forOrganization($this->org->id),
            fn () => RatePrice::factory()->create([
                'organization_id' => $this->org->id,
                'property_id' => $this->property->id,
                'rate_plan_id' => $this->parentPlan->id,
                'room_type_id' => $this->roomType->id,
                'date' => $date,
                'base_price_minor' => 1_000_000_00, // 1,000,000 IDR
                'currency' => 'IDR',
                'extra_adult_price_minor' => 200_000_00, // 200,000 IDR
                'extra_child_price_minor' => 100_000_00, // 100,000 IDR
            ])
        );
    }

    $this->pricingService = app(PricingService::class);
});

it('calculates itemized nightly prices for multi-night stay without extra guests', function (): void {
    $quote = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => $this->pricingService->quoteStay(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->parentPlan->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-04', // 3 nights
            adults: 2, // Matches base occupancy
            children: 0,
        )
    );

    expect($quote->nights)->toBe(3)
        ->and(count($quote->nightlyBreakdowns))->toBe(3)
        ->and($quote->subtotal->amountMinor)->toBe(3_000_000_00) // 3 * 1,000,000 IDR
        ->and($quote->cancellationPolicy)->not->toBeNull()
        ->and($quote->cancellationPolicy['title'])->toBe('Flexible Cancellation');

    foreach ($quote->nightlyBreakdowns as $breakdown) {
        expect($breakdown->basePrice->amountMinor)->toBe(1_000_000_00)
            ->and($breakdown->extraAdults)->toBe(0)
            ->and($breakdown->extraChildren)->toBe(0)
            ->and($breakdown->totalPrice->amountMinor)->toBe(1_000_000_00);
    }
});

it('calculates extra adult and child occupancy supplements correctly', function (): void {
    // 3 adults (base occupancy is 2 -> 1 extra adult) and 1 child
    $quote = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => $this->pricingService->quoteStay(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->parentPlan->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03', // 2 nights
            adults: 3,
            children: 1,
        )
    );

    expect($quote->nights)->toBe(2);

    // Each night:
    // Base: 1,000,000
    // 1 extra adult: +200,000
    // 1 extra child: +100,000
    // Night total: 1,300,000
    // Stay total (2 nights): 2,600,000 IDR
    expect($quote->subtotal->amountMinor)->toBe(2_600_000_00);

    $night1 = $quote->nightlyBreakdowns[0];
    expect($night1->extraAdults)->toBe(1)
        ->and($night1->extraAdultPrice->amountMinor)->toBe(200_000_00)
        ->and($night1->extraChildren)->toBe(1)
        ->and($night1->extraChildPrice->amountMinor)->toBe(100_000_00)
        ->and($night1->totalPrice->amountMinor)->toBe(1_300_000_00);
});

it('calculates derived rate plan prices using percentage derivation from parent plan', function (): void {
    // Create derived plan with -15.00% discount (1500 bps)
    $derivedPlan = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => RatePlan::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'code' => 'PROMO15',
            'name' => 'Promo 15% Off',
            'parent_rate_plan_id' => $this->parentPlan->id,
            'derivation_type' => DerivationType::Percentage,
            'derivation_value' => -1500,
        ])
    );

    $quote = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => $this->pricingService->quoteStay(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $derivedPlan->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03', // 2 nights
            adults: 2,
            children: 0,
        )
    );

    // Parent base is 1,000,000 IDR. With -15%, derived base is 850,000 IDR.
    // 2 nights * 850,000 = 1,700,000 IDR
    expect($quote->nights)->toBe(2)
        ->and($quote->subtotal->amountMinor)->toBe(1_700_000_00);

    expect($quote->nightlyBreakdowns[0]->basePrice->amountMinor)->toBe(850_000_00)
        ->and($quote->nightlyBreakdowns[1]->basePrice->amountMinor)->toBe(850_000_00);
});

it('throws exception when a date in the requested range has no price configured', function (): void {
    // Nov 4 has no price
    expect(fn () => $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => $this->pricingService->quoteStay(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->parentPlan->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-05', // Includes Nov 4 which has no price
            adults: 2,
        )
    ))->toThrow(RatePriceNotFoundException::class);
});

it('calculates price quote via HTTP endpoint', function (): void {
    $response = $this->actingAs($this->user)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->postJson('/api/inventory/quote', [
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'rate_plan_id' => $this->parentPlan->id,
            'check_in_date' => '2026-11-01',
            'check_out_date' => '2026-11-03',
            'adults' => 2,
            'children' => 0,
        ]);

    $response->assertOk();
    $data = $response->json('data');

    expect($data['nights'])->toBe(2)
        ->and($data['subtotal_minor'])->toBe(2_000_000_00)
        ->and(count($data['nightly_breakdowns']))->toBe(2);
});
