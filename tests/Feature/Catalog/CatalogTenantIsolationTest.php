<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Contracts\CatalogService;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Catalog\Infrastructure\Models\RoomUnit;
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

it('prevents user from Org 1 from viewing properties of Org 2', function (): void {
    $prop2 = $this->context->swap(
        TenantContext::forOrganization($this->org2->id),
        fn () => Property::factory()->create(['organization_id' => $this->org2->id, 'name' => 'Org 2 Secret Resort'])
    );

    $prop1 = $this->context->swap(
        TenantContext::forOrganization($this->org1->id),
        fn () => Property::factory()->create(['organization_id' => $this->org1->id, 'name' => 'Org 1 Public Resort'])
    );

    // HTTP GET properties for User 1
    $response = $this->actingAs($this->user1)
        ->withHeader('X-Organization-Id', (string) $this->org1->id)
        ->getJson('/api/catalog/properties');

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain('Org 1 Public Resort')
        ->and($names)->not->toContain('Org 2 Secret Resort');

    // Attempting to access Org 2's property directly returns 404
    $this->actingAs($this->user1)
        ->withHeader('X-Organization-Id', (string) $this->org1->id)
        ->getJson("/api/catalog/properties/{$prop2->id}")
        ->assertNotFound();
});

it('prevents user from Org 1 from updating a property of Org 2', function (): void {
    $prop2 = $this->context->swap(
        TenantContext::forOrganization($this->org2->id),
        fn () => Property::factory()->create(['organization_id' => $this->org2->id, 'name' => 'Original Name'])
    );

    $this->actingAs($this->user1)
        ->withHeader('X-Organization-Id', (string) $this->org1->id)
        ->patchJson("/api/catalog/properties/{$prop2->id}", [
            'name' => 'Compromised Name',
        ])
        ->assertNotFound();

    $freshName = $this->context->swap(
        TenantContext::forOrganization($this->org2->id),
        fn () => $prop2->fresh()->name
    );
    expect($freshName)->toBe('Original Name');
});

it('verifies EloquentCatalogService contract respects tenant isolation', function (): void {
    [$prop1, $rt1] = $this->context->swap(TenantContext::forOrganization($this->org1->id), function () {
        $p = Property::factory()->create(['organization_id' => $this->org1->id]);
        $rt = RoomType::factory()->create([
            'organization_id' => $this->org1->id,
            'property_id' => $p->id,
        ]);
        RoomUnit::factory()->create([
            'organization_id' => $this->org1->id,
            'property_id' => $p->id,
            'room_type_id' => $rt->id,
            'is_active' => true,
        ]);

        return [$p, $rt];
    });

    $service = app(CatalogService::class);

    // From Org 2's context, looking up Org 1's property or room type returns null or 0
    $this->context->swap(TenantContext::forOrganization($this->org2->id), function () use ($service, $prop1, $rt1): void {
        expect($service->getProperty($prop1->id))->toBeNull()
            ->and($service->getRoomType($rt1->id))->toBeNull()
            ->and($service->getRoomTypesForProperty($prop1->id))->toHaveCount(0)
            ->and($service->getActiveRoomUnitsCount($rt1->id))->toBe(0);
    });
});
