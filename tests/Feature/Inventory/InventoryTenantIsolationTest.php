<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Domain\Exceptions\InventoryBlockNotFoundException;
use App\Modules\Inventory\Infrastructure\Models\InventoryBlock;
use App\Modules\Inventory\Infrastructure\Models\InventoryDay;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Tenant A
    $this->orgA = Organization::factory()->create();
    $this->propertyA = Property::factory()->create(['organization_id' => $this->orgA->id]);
    $this->userA = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->userA->id,
        'organization_id' => $this->orgA->id,
        'role' => Role::Owner,
    ]);

    // Tenant B
    $this->orgB = Organization::factory()->create();
    $this->propertyB = Property::factory()->create(['organization_id' => $this->orgB->id]);
    $this->userB = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->userB->id,
        'organization_id' => $this->orgB->id,
        'role' => Role::Owner,
    ]);

    $this->inventoryService = app(InventoryService::class);
});

it('prevents cross-tenant read or modification of inventory days and blocks', function (): void {
    $context = app(OrganizationContext::class);
    $contextA = TenantContext::forOrganizationAndProperty($this->orgA->id, $this->propertyA->id);
    $contextB = TenantContext::forOrganizationAndProperty($this->orgB->id, $this->propertyB->id);

    [$roomTypeA, $blockA] = $context->swap($contextA, function () {
        $roomTypeA = RoomType::factory()->create([
            'organization_id' => $this->orgA->id,
            'property_id' => $this->propertyA->id,
            'total_units' => 5,
        ]);

        $this->inventoryService->materializeDays(
            propertyId: $this->propertyA->id,
            startDate: '2026-11-01',
            endDate: '2026-11-03',
            roomTypeIds: [$roomTypeA->id],
        );

        $blockA = $this->inventoryService->block(
            propertyId: $this->propertyA->id,
            roomTypeId: $roomTypeA->id,
            startDate: '2026-11-01',
            endDate: '2026-11-02',
            units: 2,
            reason: 'Maintenance A',
        );

        return [$roomTypeA, $blockA];
    });

    // Run within Tenant B context
    $context->swap($contextB, function () use ($roomTypeA, $blockA): void {
        // Tenant B queries inventory_days for roomTypeA -> BelongsToTenant returns empty
        $daysForTenantB = InventoryDay::query()->where('room_type_id', $roomTypeA->id)->get();
        expect($daysForTenantB)->toBeEmpty();

        // Tenant B queries inventory_blocks for blockA -> BelongsToTenant returns empty
        $blockForTenantB = InventoryBlock::query()->find($blockA->id);
        expect($blockForTenantB)->toBeNull();

        // Tenant B cannot unblock Tenant A's block
        expect(function () use ($blockA): void {
            $this->inventoryService->unblock($blockA->id);
        })->toThrow(InventoryBlockNotFoundException::class);
    });
});
