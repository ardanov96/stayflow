<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Domain\Events\InventoryChanged;
use App\Modules\Inventory\Infrastructure\Jobs\ExpireHoldsJob;
use App\Modules\Inventory\Infrastructure\Models\InventoryDay;
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

    $this->context->swap($this->tenant, function (): void {
        $this->inventoryService->materializeDays(
            propertyId: $this->property->id,
            startDate: $this->checkIn,
            endDate: now()->addDays(5)->format('Y-m-d'),
            roomTypeIds: [$this->roomType->id],
        );
    });
});

it('expires a hold whose time-to-live has passed and gives the units back to availability', function (): void {
    $events = [];
    Event::listen(InventoryChanged::class, function (InventoryChanged $event) use (&$events): void {
        $events[] = $event;
    });

    $holdId = $this->context->swap($this->tenant, function (): int {
        return $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 3,
            idempotencyKey: 'expiry-key',
            ttlSeconds: 60,
        )->id;
    });

    // The time-to-live has not passed yet: the hold keeps its units.
    $this->travel(30)->seconds();

    ExpireHoldsJob::dispatch($this->org->id);

    $this->context->swap($this->tenant, function () use ($holdId): void {
        $hold = $this->inventoryService->getHold($holdId);

        expect($hold)->not->toBeNull()
            ->and($hold->status)->toBe('active');

        $day = InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', $this->checkIn)
            ->first();

        expect($day->held_units)->toBe(3)
            ->and($day->available_units)->toBe(7);
    });

    // Only the creation of the hold was published so far.
    expect($events)->toHaveCount(1)
        ->and($events[0]->reason)->toBe('hold.created');

    // Now the time-to-live has passed.
    $this->travel(2)->minutes();

    ExpireHoldsJob::dispatch($this->org->id);

    $this->context->swap($this->tenant, function () use ($holdId): void {
        $hold = $this->inventoryService->getHold($holdId);

        expect($hold)->not->toBeNull()
            ->and($hold->status)->toBe('expired')
            ->and($hold->expiredAt)->not->toBeNull();

        $day = InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', $this->checkIn)
            ->first();

        expect($day->held_units)->toBe(0)
            ->and($day->sold_units)->toBe(0)
            ->and($day->available_units)->toBe(10);

        expect(AuditLog::query()
            ->where('event', 'inventory.hold.expired')
            ->exists())->toBeTrue();
    });

    expect($events)->toHaveCount(2)
        ->and($events[1]->reason)->toBe('hold.expired');
});

it('never expires a hold twice', function (): void {
    $holdId = $this->context->swap($this->tenant, function (): int {
        return $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 2,
            idempotencyKey: 'expiry-twice-key',
            ttlSeconds: 60,
        )->id;
    });

    $this->travel(2)->minutes();

    ExpireHoldsJob::dispatch($this->org->id);
    ExpireHoldsJob::dispatch($this->org->id);

    $this->context->swap($this->tenant, function () use ($holdId): void {
        $hold = $this->inventoryService->getHold($holdId);

        expect($hold->status)->toBe('expired');

        $day = InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', $this->checkIn)
            ->first();

        expect($day->held_units)->toBe(0)
            ->and($day->available_units)->toBe(10);
    });
});

it('refuses to release an expired hold and keeps the units released', function (): void {
    $holdId = $this->context->swap($this->tenant, function (): int {
        return $this->inventoryService->hold(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            units: 1,
            idempotencyKey: 'expiry-then-release-key',
            ttlSeconds: 60,
        )->id;
    });

    $this->travel(2)->minutes();

    ExpireHoldsJob::dispatch($this->org->id);

    $this->context->swap($this->tenant, function () use ($holdId): void {
        // Releasing an expired hold is a no-op: the units were already
        // given back by the expiry job.
        $released = $this->inventoryService->releaseHold($holdId);

        expect($released->status)->toBe('expired');

        $day = InventoryDay::query()
            ->where('room_type_id', $this->roomType->id)
            ->where('date', $this->checkIn)
            ->first();

        expect($day->held_units)->toBe(0)
            ->and($day->available_units)->toBe(10);
    });
});
