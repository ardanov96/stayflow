<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
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

    $this->roomType = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => RoomType::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
        ])
    );

    $this->ratePlan = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => RatePlan::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
        ])
    );
});

it('sets rate prices across a date range with audit log', function (): void {
    $response = $this->actingAs($this->user)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->postJson('/api/inventory/prices', [
            'property_id' => $this->property->id,
            'rate_plan_id' => $this->ratePlan->id,
            'room_type_id' => $this->roomType->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'base_price_minor' => 1_500_000_00, // 1,500,000 IDR
            'currency' => 'IDR',
            'extra_adult_price_minor' => 300_000_00,
            'extra_child_price_minor' => 150_000_00,
        ]);

    $response->assertOk();

    // 5 dates: Nov 1, 2, 3, 4, 5
    expect(RatePrice::withoutGlobalScopes()->count())->toBe(5);

    $this->assertDatabaseHas('rate_prices', [
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'rate_plan_id' => $this->ratePlan->id,
        'room_type_id' => $this->roomType->id,
        'date' => '2026-11-01',
        'base_price_minor' => 1_500_000_00,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'organization_id' => $this->org->id,
        'subject_type' => 'RatePrice',
        'event' => 'rate.changed',
    ]);
});

it('overwrites existing rate prices for the same room and date', function (): void {
    $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => RatePrice::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'rate_plan_id' => $this->ratePlan->id,
            'room_type_id' => $this->roomType->id,
            'date' => '2026-11-01',
            'base_price_minor' => 1_000_000_00,
        ])
    );

    $this->actingAs($this->user)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->postJson('/api/inventory/prices', [
            'property_id' => $this->property->id,
            'rate_plan_id' => $this->ratePlan->id,
            'room_type_id' => $this->roomType->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-01',
            'base_price_minor' => 2_000_000_00,
        ])
        ->assertOk();

    expect(RatePrice::withoutGlobalScopes()->count())->toBe(1);

    $this->assertDatabaseHas('rate_prices', [
        'date' => '2026-11-01',
        'base_price_minor' => 2_000_000_00,
    ]);
});
