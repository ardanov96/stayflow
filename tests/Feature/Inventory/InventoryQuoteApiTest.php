<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
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
    $this->property = Property::factory()->create(['organization_id' => $this->org->id]);

    $this->owner = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->owner->id,
        'organization_id' => $this->org->id,
        'property_id' => null,
        'role' => Role::Owner,
    ]);

    $this->housekeeping = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->housekeeping->id,
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'role' => Role::Housekeeping,
    ]);

    $this->roomType = RoomType::factory()->create([
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'total_units' => 5,
    ]);

    $this->ratePlan = RatePlan::factory()->create([
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
    ]);

    // Materialize days and setup prices
    $this->context = app(OrganizationContext::class);
    $this->tenant = TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id);

    $this->context->swap($this->tenant, function (): void {
        app(InventoryService::class)->materializeDays(
            propertyId: $this->property->id,
            startDate: '2026-11-01',
            endDate: '2026-11-05',
            roomTypeIds: [$this->roomType->id],
        );

        for ($i = 1; $i <= 5; $i++) {
            RatePrice::factory()->create([
                'organization_id' => $this->org->id,
                'property_id' => $this->property->id,
                'rate_plan_id' => $this->ratePlan->id,
                'room_type_id' => $this->roomType->id,
                'date' => sprintf('2026-11-%02d', $i),
                'base_price_minor' => 1_000_000_00,
            ]);
        }
    });
});

it('quotes availability via API endpoint', function (): void {
    $response = $this->actingAs($this->owner)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->withHeader('X-Property-Id', (string) $this->property->id)
        ->postJson('/api/inventory/availability/quote', [
            'property_id' => $this->property->id,
            'room_type_ids' => [$this->roomType->id],
            'check_in_date' => '2026-11-01',
            'check_out_date' => '2026-11-03',
            'adults' => 2,
            'rate_plan_id' => $this->ratePlan->id,
        ]);

    $response->assertOk();
    $data = $response->json();

    expect($data['nights'])->toBe(2)
        ->and($data['room_types'])->toHaveCount(1)
        ->and($data['room_types'][0]['is_available'])->toBeTrue()
        ->and($data['room_types'][0]['available_units'])->toBe(5)
        ->and($data['room_types'][0]['price_quote']['subtotal_minor'])->toBe(2_000_000_00);
});

it('creates and destroys inventory blocks via API with permission check', function (): void {
    // Housekeeping role does not have inventory.manage permission -> 403
    $forbidden = $this->actingAs($this->housekeeping)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->withHeader('X-Property-Id', (string) $this->property->id)
        ->postJson('/api/inventory/blocks', [
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'units' => 2,
            'reason' => 'Renovation',
        ]);
    $forbidden->assertForbidden();

    // Owner has inventory.manage -> 201 Created
    $created = $this->actingAs($this->owner)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->withHeader('X-Property-Id', (string) $this->property->id)
        ->postJson('/api/inventory/blocks', [
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'units' => 2,
            'reason' => 'Renovation',
        ]);
    $created->assertCreated();
    $blockId = $created->json('id');

    // Remove block
    $deleted = $this->actingAs($this->owner)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->withHeader('X-Property-Id', (string) $this->property->id)
        ->deleteJson("/api/inventory/blocks/{$blockId}");

    $deleted->assertOk();
});

it('sets restrictions via API with permission check', function (): void {
    $response = $this->actingAs($this->owner)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->withHeader('X-Property-Id', (string) $this->property->id)
        ->postJson('/api/inventory/restrictions', [
            'property_id' => $this->property->id,
            'rate_plan_id' => $this->ratePlan->id,
            'room_type_id' => $this->roomType->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'min_stay' => 2,
            'cta' => true,
        ]);

    $response->assertOk();
    $data = $response->json();
    expect($data)->toHaveCount(2)
        ->and($data[0]['min_stay'])->toBe(2)
        ->and($data[0]['cta'])->toBeTrue();
});
