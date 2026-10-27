<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Domain\Exceptions\IdempotencyConflictException;
use App\Modules\Inventory\Infrastructure\Models\InventoryDay;
use App\Modules\Inventory\Infrastructure\Models\InventoryHold;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->property = Property::factory()->create(['organization_id' => $this->org->id]);
    $this->roomType = RoomType::factory()->create([
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'total_units' => 10,
    ]);
    $this->context = app(OrganizationContext::class);
    $this->tenant = TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id);
    $this->inventoryService = app(InventoryService::class);

    $this->checkIn = now()->addDay()->format('Y-m-d');
    $this->checkOut = now()->addDays(3)->format('Y-m-d');

    $this->context->swap($this->tenant, function (): void {
        $this->inventoryService->materializeDays(
            propertyId: $this->property->id,
            startDate: $this->checkIn,
            endDate: now()->addDays(5)->format('Y-m-d'),
            roomTypeIds: [$this->roomType->id],
        );
    });
});

it('replaying the same idempotency key returns the existing hold without holding the units twice', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $first = $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 2,
            idempotencyKey: 'replay-key',
        );

        $second = $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 2,
            idempotencyKey: 'replay-key',
        );

        expect($second->id)->toBe($first->id)
            ->and($second->status)->toBe('active')
            ->and(InventoryHold::query()->count())->toBe(1);

        $day = InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', $this->checkIn)
            ->first();

        expect($day->held_units)->toBe(2)
            ->and($day->sold_units)->toBe(0)
            ->and($day->available_units)->toBe(8);
    });
});

it('rejects the same idempotency key with a different payload and keeps the counters untouched', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 2,
            idempotencyKey: 'conflict-key',
        );

        expect(fn (): mixed => $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 5,
            idempotencyKey: 'conflict-key',
        ))->toThrow(IdempotencyConflictException::class);

        expect(InventoryHold::query()->count())->toBe(1);

        $day = InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', $this->checkIn)
            ->first();

        expect($day->held_units)->toBe(2)
            ->and($day->available_units)->toBe(8);
    });
});

it('scopes the idempotency key to the organization', function (): void {
    $otherOrg = Organization::factory()->create();
    $otherProperty = Property::factory()->create(['organization_id' => $otherOrg->id]);
    $otherRoomType = RoomType::factory()->create([
        'organization_id' => $otherOrg->id,
        'property_id' => $otherProperty->id,
        'total_units' => 10,
    ]);
    $otherTenant = TenantContext::forOrganizationAndProperty($otherOrg->id, $otherProperty->id);

    $this->context->swap($this->tenant, function (): void {
        $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 1,
            idempotencyKey: 'shared-key',
        );
    });

    $this->context->swap($otherTenant, function () use ($otherOrg, $otherProperty, $otherRoomType): void {
        $this->inventoryService->materializeDays(
            propertyId: $otherProperty->id,
            startDate: $this->checkIn,
            endDate: now()->addDays(5)->format('Y-m-d'),
            roomTypeIds: [$otherRoomType->id],
        );

        $hold = $this->inventoryService->hold(
            propertyId: $otherProperty->id,
            roomTypeId: $otherRoomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 1,
            idempotencyKey: 'shared-key',
        );

        expect($hold->organizationId)->toBe($otherOrg->id)
            ->and($hold->idempotencyKey)->toBe('shared-key')
            ->and(InventoryHold::query()->count())->toBe(1);
    });
});

it('rejects an empty idempotency key', function (): void {
    $this->context->swap($this->tenant, function (): void {
        expect(fn (): mixed => $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 1,
            idempotencyKey: '',
        ))->toThrow(InvalidArgumentException::class);

        expect(InventoryHold::query()->count())->toBe(0);
    });
});

it('rejects an idempotency key longer than 64 characters', function (): void {
    $this->context->swap($this->tenant, function (): void {
        expect(fn (): mixed => $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 1,
            idempotencyKey: str_repeat('k', 65),
        ))->toThrow(InvalidArgumentException::class);

        expect(InventoryHold::query()->count())->toBe(0);
    });
});
