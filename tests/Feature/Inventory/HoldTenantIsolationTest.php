<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Domain\Exceptions\HoldNotFoundException;
use App\Modules\Inventory\Infrastructure\Jobs\ExpireHoldsJob;
use App\Modules\Inventory\Infrastructure\Models\InventoryDay;
use App\Modules\Inventory\Infrastructure\Models\InventoryHold;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Tenant A
    $this->orgA = Organization::factory()->create();
    $this->propertyA = Property::factory()->create(['organization_id' => $this->orgA->id]);
    $this->roomTypeA = RoomType::factory()->create([
        'organization_id' => $this->orgA->id,
        'property_id' => $this->propertyA->id,
        'total_units' => 10,
    ]);
    $this->tenantA = TenantContext::forOrganizationAndProperty($this->orgA->id, $this->propertyA->id);

    // Tenant B
    $this->orgB = Organization::factory()->create();
    $this->propertyB = Property::factory()->create(['organization_id' => $this->orgB->id]);
    $this->roomTypeB = RoomType::factory()->create([
        'organization_id' => $this->orgB->id,
        'property_id' => $this->propertyB->id,
        'total_units' => 10,
    ]);
    $this->tenantB = TenantContext::forOrganizationAndProperty($this->orgB->id, $this->propertyB->id);

    $this->context = app(OrganizationContext::class);
    $this->inventoryService = app(InventoryService::class);

    $this->checkIn = now()->addDay()->format('Y-m-d');
    $this->checkOut = now()->addDays(3)->format('Y-m-d');

    $tenants = [
        [$this->tenantA, $this->propertyA, $this->roomTypeA],
        [$this->tenantB, $this->propertyB, $this->roomTypeB],
    ];

    foreach ($tenants as [$tenant, $property, $roomType]) {
        $this->context->swap($tenant, function () use ($property, $roomType): void {
            $this->inventoryService->materializeDays(
                propertyId: $property->id,
                startDate: $this->checkIn,
                endDate: now()->addDays(5)->format('Y-m-d'),
                roomTypeIds: [$roomType->id],
            );
        });
    }
});

it('hides the holds of another organization', function (): void {
    $holdId = $this->context->swap($this->tenantA, function (): int {
        return $this->inventoryService->hold(
            propertyId: $this->propertyA->id,
            roomTypeId: $this->roomTypeA->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 1,
            idempotencyKey: 'isolation-key',
        )->id;
    });

    $this->context->swap($this->tenantB, function () use ($holdId): void {
        expect($this->inventoryService->getHold($holdId))->toBeNull()
            ->and(InventoryHold::query()->find($holdId))->toBeNull()
            ->and(InventoryHold::query()->count())->toBe(0);

        $days = InventoryDay::query()
            ->where('room_type_id', $this->roomTypeA->id)
            ->get();

        expect($days)->toBeEmpty();
    });
});

it('refuses to commit a hold of another organization', function (): void {
    $holdId = $this->context->swap($this->tenantA, function (): int {
        return $this->inventoryService->hold(
            propertyId: $this->propertyA->id,
            roomTypeId: $this->roomTypeA->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 1,
            idempotencyKey: 'isolation-commit-key',
        )->id;
    });

    $this->context->swap($this->tenantB, function () use ($holdId): void {
        expect(fn (): mixed => $this->inventoryService->commitHold($holdId))
            ->toThrow(HoldNotFoundException::class);
    });

    // Tenant A keeps its hold and its units.
    $this->context->swap($this->tenantA, function () use ($holdId): void {
        expect($this->inventoryService->getHold($holdId)->status)->toBe('active');

        $day = InventoryDay::query()
            ->where('room_type_id', $this->roomTypeA->id)
            ->where('date', $this->checkIn)
            ->first();

        expect($day->held_units)->toBe(1)
            ->and($day->sold_units)->toBe(0);
    });
});

it('refuses to release a hold of another organization', function (): void {
    $holdId = $this->context->swap($this->tenantA, function (): int {
        return $this->inventoryService->hold(
            propertyId: $this->propertyA->id,
            roomTypeId: $this->roomTypeA->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 1,
            idempotencyKey: 'isolation-release-key',
        )->id;
    });

    $this->context->swap($this->tenantB, function () use ($holdId): void {
        expect(fn (): mixed => $this->inventoryService->releaseHold($holdId))
            ->toThrow(HoldNotFoundException::class);
    });

    $this->context->swap($this->tenantA, function () use ($holdId): void {
        expect($this->inventoryService->getHold($holdId)->status)->toBe('active');

        $day = InventoryDay::query()
            ->where('room_type_id', $this->roomTypeA->id)
            ->where('date', $this->checkIn)
            ->first();

        expect($day->held_units)->toBe(1)
            ->and($day->available_units)->toBe(9);
    });
});

it('the expiry job of another organization does not expire the hold', function (): void {
    $holdId = $this->context->swap($this->tenantA, function (): int {
        return $this->inventoryService->hold(
            propertyId: $this->propertyA->id,
            roomTypeId: $this->roomTypeA->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 2,
            idempotencyKey: 'isolation-expiry-key',
            ttlSeconds: 60,
        )->id;
    });

    $this->travel(2)->minutes();

    ExpireHoldsJob::dispatch($this->orgB->id);

    $this->context->swap($this->tenantA, function () use ($holdId): void {
        expect($this->inventoryService->getHold($holdId)->status)->toBe('active');

        $day = InventoryDay::query()
            ->where('room_type_id', $this->roomTypeA->id)
            ->where('date', $this->checkIn)
            ->first();

        expect($day->held_units)->toBe(2)
            ->and($day->available_units)->toBe(8);
    });

    // The expiry job of the owning organization does expire it.
    ExpireHoldsJob::dispatch($this->orgA->id);

    $this->context->swap($this->tenantA, function () use ($holdId): void {
        expect($this->inventoryService->getHold($holdId)->status)->toBe('expired');

        $day = InventoryDay::query()
            ->where('room_type_id', $this->roomTypeA->id)
            ->where('date', $this->checkIn)
            ->first();

        expect($day->held_units)->toBe(0)
            ->and($day->available_units)->toBe(10);
    });
});
