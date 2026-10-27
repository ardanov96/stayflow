<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Domain\Events\InventoryChanged;
use App\Modules\Inventory\Domain\Exceptions\HoldStateException;
use App\Modules\Inventory\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Inventory\Domain\Exceptions\OutsideMaterializedWindowException;
use App\Modules\Inventory\Infrastructure\Models\InventoryDay;
use App\Modules\Inventory\Infrastructure\Models\InventoryHold;
use App\Modules\Inventory\Infrastructure\Models\InventoryHoldLine;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\AuditLog;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

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
    // The stay covers the night of the check-in up to (excluding) the
    // check-out date.
    $this->lastNight = now()->addDays(2)->format('Y-m-d');

    $this->context->swap($this->tenant, function (): void {
        $this->inventoryService->materializeDays(
            propertyId: $this->property->id,
            startDate: $this->checkIn,
            endDate: now()->addDays(5)->format('Y-m-d'),
            roomTypeIds: [$this->roomType->id],
        );
    });
});

it('holds the units and records the hold, its lines and the audit trail', function (): void {
    $events = [];
    Event::listen(InventoryChanged::class, function (InventoryChanged $event) use (&$events): void {
        $events[] = $event;
    });

    $this->context->swap($this->tenant, function (): void {
        $hold = $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 2,
            idempotencyKey: 'hold-key-1',
        );

        expect($hold->id)->toBeGreaterThan(0)
            ->and($hold->organizationId)->toBe($this->org->id)
            ->and($hold->propertyId)->toBe($this->property->id)
            ->and($hold->roomTypeId)->toBe($this->roomType->id)
            ->and($hold->checkIn)->toBe($this->checkIn)
            ->and($hold->checkOut)->toBe($this->checkOut)
            ->and($hold->units)->toBe(2)
            ->and($hold->status)->toBe('active')
            ->and($hold->idempotencyKey)->toBe('hold-key-1');

        $days = InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->whereBetween('date', [$this->checkIn, $this->lastNight])
            ->get();

        expect($days)->toHaveCount(2);
        foreach ($days as $day) {
            expect($day->held_units)->toBe(2)
                ->and($day->sold_units)->toBe(0)
                ->and($day->available_units)->toBe(8);
        }

        $lines = InventoryHoldLine::query()->where('hold_id', $hold->id)->get();
        expect($lines)->toHaveCount(2)
            ->and($lines->map(fn ($line): string => $line->date->format('Y-m-d'))->all())
            ->toBe([$this->checkIn, $this->lastNight]);

        $audit = AuditLog::query()->where('event', 'inventory.hold.created')->first();
        expect($audit)->not->toBeNull()
            ->and($audit->organization_id)->toBe($this->org->id);
    });

    expect($events)->toHaveCount(1)
        ->and($events[0]->organizationId)->toBe($this->org->id)
        ->and($events[0]->roomTypeIds)->toBe([$this->roomType->id])
        ->and($events[0]->reason)->toBe('hold.created');
});

it('commits a hold and turns the held units into sold units', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $hold = $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 2,
            idempotencyKey: 'hold-key-commit',
        );

        $committed = $this->inventoryService->commitHold($hold->id, reservationId: 99);

        expect($committed->status)->toBe('committed')
            ->and($committed->reservationId)->toBe(99)
            ->and($committed->committedAt)->not->toBeNull();

        $days = InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->whereBetween('date', [$this->checkIn, $this->lastNight])
            ->get();

        foreach ($days as $day) {
            expect($day->held_units)->toBe(0)
                ->and($day->sold_units)->toBe(2)
                ->and($day->available_units)->toBe(8);
        }

        expect(AuditLog::query()->where('event', 'inventory.hold.committed')->exists())->toBeTrue();
    });
});

it('releases a hold and gives the units back to availability', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $hold = $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 3,
            idempotencyKey: 'hold-key-release',
        );

        $released = $this->inventoryService->releaseHold($hold->id, 'guest cancelled');

        expect($released->status)->toBe('released')
            ->and($released->releaseReason)->toBe('guest cancelled')
            ->and($released->releasedAt)->not->toBeNull();

        $days = InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->whereBetween('date', [$this->checkIn, $this->lastNight])
            ->get();

        foreach ($days as $day) {
            expect($day->held_units)->toBe(0)
                ->and($day->sold_units)->toBe(0)
                ->and($day->available_units)->toBe(10);
        }

        expect(AuditLog::query()->where('event', 'inventory.hold.released')->exists())->toBeTrue();
    });
});

it('rejects a hold that exceeds the available units and changes nothing', function (): void {
    $this->context->swap($this->tenant, function (): void {
        expect(fn (): mixed => $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 11,
            idempotencyKey: 'hold-key-too-many',
        ))->toThrow(InsufficientInventoryException::class);

        expect(InventoryHold::query()->count())->toBe(0);

        $day = InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', $this->checkIn)
            ->first();

        expect($day->held_units)->toBe(0)
            ->and($day->available_units)->toBe(10);
    });
});

it('rejects a hold on a date that was never materialized', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $farAway = now()->addDays(400)->format('Y-m-d');

        expect(fn (): mixed => $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $farAway,
            checkOut: now()->addDays(401)->format('Y-m-d'),
            units: 1,
            idempotencyKey: 'hold-key-unmaterialized',
        ))->toThrow(OutsideMaterializedWindowException::class);
    });
});

it('rejects a hold for a room type of another property', function (): void {
    $otherProperty = Property::factory()->create(['organization_id' => $this->org->id]);
    $otherRoomType = RoomType::factory()->create([
        'organization_id' => $this->org->id,
        'property_id' => $otherProperty->id,
    ]);

    $this->context->swap($this->tenant, function () use ($otherRoomType): void {
        expect(fn (): mixed => $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $otherRoomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 1,
            idempotencyKey: 'hold-key-wrong-property',
        ))->toThrow(InvalidArgumentException::class);
    });
});

it('committing twice does not sell the units twice', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $hold = $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 2,
            idempotencyKey: 'hold-key-commit-twice',
        );

        $first = $this->inventoryService->commitHold($hold->id, reservationId: 7);
        $second = $this->inventoryService->commitHold($hold->id, reservationId: 7);

        expect($second->id)->toBe($first->id)
            ->and($second->status)->toBe('committed');

        $day = InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', $this->checkIn)
            ->first();

        expect($day->sold_units)->toBe(2)
            ->and($day->held_units)->toBe(0);
    });
});

it('refuses to release a committed hold', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $hold = $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 1,
            idempotencyKey: 'hold-key-release-after-commit',
        );

        $this->inventoryService->commitHold($hold->id);

        expect(fn (): mixed => $this->inventoryService->releaseHold($hold->id))
            ->toThrow(HoldStateException::class);
    });
});

it('releasing twice does not give the units back twice', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $hold = $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 4,
            idempotencyKey: 'hold-key-release-twice',
        );

        $this->inventoryService->releaseHold($hold->id);
        $this->inventoryService->releaseHold($hold->id);

        $day = InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', $this->checkIn)
            ->first();

        expect($day->held_units)->toBe(0)
            ->and($day->available_units)->toBe(10);
    });
});
