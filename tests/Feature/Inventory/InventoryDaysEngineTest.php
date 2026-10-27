<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Inventory\Infrastructure\Jobs\MaterializeInventoryDaysJob;
use App\Modules\Inventory\Infrastructure\Models\InventoryBlock;
use App\Modules\Inventory\Infrastructure\Models\InventoryDay;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->property = Property::factory()->create([
        'organization_id' => $this->org->id,
    ]);

    $this->user = User::factory()->create();
    $this->context = app(OrganizationContext::class);
    $this->tenant = TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id);
    $this->inventoryService = app(InventoryService::class);
});

it('pre-materializes inventory days and availability matches hand-computed fixtures', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $roomType = RoomType::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'total_units' => 10,
        ]);

        $count = $this->inventoryService->materializeDays(
            propertyId: $this->property->id,
            startDate: '2026-11-01',
            endDate: '2026-11-05',
            roomTypeIds: [$roomType->id],
        );

        expect($count)->toBe(5);

        // Initial availability: total (10) - held (0) - sold (0) - blocked (0) = 10
        $day1 = InventoryDay::query()->where('room_type_id', $roomType->id)->where('date', '2026-11-01')->first();
        expect($day1)->not->toBeNull()
            ->and($day1->total_units)->toBe(10)
            ->and($day1->held_units)->toBe(0)
            ->and($day1->sold_units)->toBe(0)
            ->and($day1->blocked_units)->toBe(0)
            ->and($day1->available_units)->toBe(10);

        // Simulate hold (2), sold (3), blocked (1) on 2026-11-02
        // Hand-computed expected availability: 10 - (2 + 3 + 1) = 4
        $day2 = InventoryDay::query()->where('room_type_id', $roomType->id)->where('date', '2026-11-02')->first();
        $day2->update([
            'held_units' => 2,
            'sold_units' => 3,
            'blocked_units' => 1,
        ]);

        $day2->refresh();
        expect($day2->available_units)->toBe(4);

        // Re-running materialization does not overwrite existing held/sold/blocked counts
        $this->inventoryService->materializeDays(
            propertyId: $this->property->id,
            startDate: '2026-11-01',
            endDate: '2026-11-05',
            roomTypeIds: [$roomType->id],
        );

        $day2->refresh();
        expect($day2->total_units)->toBe(10)
            ->and($day2->held_units)->toBe(2)
            ->and($day2->sold_units)->toBe(3)
            ->and($day2->blocked_units)->toBe(1)
            ->and($day2->available_units)->toBe(4);
    });
});

it('enforces database CHECK constraints when held + sold + blocked exceeds total_units', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $roomType = RoomType::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'total_units' => 5,
        ]);

        // Valid: 5 total = 2 held + 2 sold + 1 blocked
        $day = InventoryDay::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'date' => '2026-11-01',
            'total_units' => 5,
            'held_units' => 2,
            'sold_units' => 2,
            'blocked_units' => 1,
        ]);
        expect($day->available_units)->toBe(0);

        // Invalid: updating held to 3 makes sum 6 > 5 total units -> triggers DB CHECK constraint
        expect(function () use ($day): void {
            $day->update(['held_units' => 3]);
        })->toThrow(QueryException::class);
    });
});

it('blocks room units across dates and decrements available inventory', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $roomType = RoomType::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'total_units' => 8,
        ]);

        $this->inventoryService->materializeDays(
            propertyId: $this->property->id,
            startDate: '2026-11-01',
            endDate: '2026-11-03',
            roomTypeIds: [$roomType->id],
        );

        // Block 3 units for maintenance from 2026-11-01 to 2026-11-02
        $block = $this->inventoryService->block(
            propertyId: $this->property->id,
            roomTypeId: $roomType->id,
            startDate: '2026-11-01',
            endDate: '2026-11-02',
            units: 3,
            reason: 'Air conditioning maintenance',
            userId: $this->user->id,
        );

        expect($block->id)->toBeGreaterThan(0)
            ->and($block->units)->toBe(3)
            ->and($block->reason)->toBe('Air conditioning maintenance');

        $day1 = InventoryDay::query()->where('room_type_id', $roomType->id)->where('date', '2026-11-01')->first();
        $day2 = InventoryDay::query()->where('room_type_id', $roomType->id)->where('date', '2026-11-02')->first();
        $day3 = InventoryDay::query()->where('room_type_id', $roomType->id)->where('date', '2026-11-03')->first();

        expect($day1->blocked_units)->toBe(3)
            ->and($day1->available_units)->toBe(5)
            ->and($day2->blocked_units)->toBe(3)
            ->and($day2->available_units)->toBe(5)
            ->and($day3->blocked_units)->toBe(0)
            ->and($day3->available_units)->toBe(8);

        // Unblock restores availability
        $this->inventoryService->unblock($block->id, $this->user->id);

        $day1->refresh();
        $day2->refresh();
        expect($day1->blocked_units)->toBe(0)
            ->and($day1->available_units)->toBe(8)
            ->and($day2->blocked_units)->toBe(0)
            ->and($day2->available_units)->toBe(8);

        expect(InventoryBlock::query()->find($block->id))->toBeNull();
    });
});

it('rejects blocking more units than currently available', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $roomType = RoomType::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'total_units' => 4,
        ]);

        $this->inventoryService->materializeDays(
            propertyId: $this->property->id,
            startDate: '2026-11-01',
            endDate: '2026-11-02',
            roomTypeIds: [$roomType->id],
        );

        // Sold 2 units on 2026-11-02, leaving 2 available
        InventoryDay::query()
            ->where('room_type_id', $roomType->id)
            ->where('date', '2026-11-02')
            ->update(['sold_units' => 2]);

        // Requesting to block 3 units should fail
        expect(function () use ($roomType): void {
            $this->inventoryService->block(
                propertyId: $this->property->id,
                roomTypeId: $roomType->id,
                startDate: '2026-11-01',
                endDate: '2026-11-02',
                units: 3,
                reason: 'Excess block',
            );
        })->toThrow(InsufficientInventoryException::class);
    });
});

it('MaterializeInventoryDaysJob runs in the background and respects tenant context', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $roomType = RoomType::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'total_units' => 6,
        ]);

        MaterializeInventoryDaysJob::dispatch(
            propertyId: $this->property->id,
            startDate: '2026-11-10',
            endDate: '2026-11-12',
            roomTypeIds: [$roomType->id],
        );

        $days = InventoryDay::query()
            ->where('room_type_id', $roomType->id)
            ->whereBetween('date', ['2026-11-10', '2026-11-12'])
            ->get();

        expect($days)->toHaveCount(3);
    });
});
