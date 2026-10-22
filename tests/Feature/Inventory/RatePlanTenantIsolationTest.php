<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Infrastructure\Models\RatePlan;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org1 = Organization::factory()->create();
    $this->org2 = Organization::factory()->create();

    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();

    Membership::factory()->create([
        'user_id' => $this->user1->id,
        'organization_id' => $this->org1->id,
        'property_id' => null,
        'role' => Role::Owner,
    ]);

    Membership::factory()->create([
        'user_id' => $this->user2->id,
        'organization_id' => $this->org2->id,
        'property_id' => null,
        'role' => Role::Owner,
    ]);

    $this->context = app(OrganizationContext::class);
});

it('prevents user from Org 1 from viewing rate plans of Org 2', function (): void {
    $prop2 = $this->context->swap(
        TenantContext::forOrganization($this->org2->id),
        fn () => Property::factory()->create(['organization_id' => $this->org2->id])
    );

    $plan2 = $this->context->swap(
        TenantContext::forOrganization($this->org2->id),
        fn () => RatePlan::factory()->create([
            'organization_id' => $this->org2->id,
            'property_id' => $prop2->id,
            'name' => 'Org 2 Secret Rate Plan',
        ])
    );

    $response = $this->actingAs($this->user1)
        ->withHeader('X-Organization-Id', (string) $this->org1->id)
        ->getJson("/api/inventory/rate-plans?property_id={$prop2->id}");

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->not->toContain('Org 2 Secret Rate Plan');
});

it('prevents user from Org 1 from updating prices of Org 2', function (): void {
    $prop2 = $this->context->swap(
        TenantContext::forOrganization($this->org2->id),
        fn () => Property::factory()->create(['organization_id' => $this->org2->id])
    );

    $room2 = $this->context->swap(
        TenantContext::forOrganization($this->org2->id),
        fn () => RoomType::factory()->create([
            'organization_id' => $this->org2->id,
            'property_id' => $prop2->id,
        ])
    );

    $plan2 = $this->context->swap(
        TenantContext::forOrganization($this->org2->id),
        fn () => RatePlan::factory()->create([
            'organization_id' => $this->org2->id,
            'property_id' => $prop2->id,
        ])
    );

    $response = $this->actingAs($this->user1)
        ->withHeader('X-Organization-Id', (string) $this->org1->id)
        ->postJson('/api/inventory/prices', [
            'property_id' => $prop2->id,
            'rate_plan_id' => $plan2->id,
            'room_type_id' => $room2->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'base_price_minor' => 1_000_000_00,
        ]);

    // Validation rejects nonexistent rate_plan_id / room_type_id or 400 invalid argument
    expect($response->status())->toBeIn([400, 422, 403, 404]);
});
