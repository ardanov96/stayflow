<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Domain\Events\InventoryChanged;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
 * The after-commit publication of `InventoryChanged` is proved here and
 * not in the other hold tests on purpose: `RefreshDatabase` replaces the
 * transaction manager of the container, so the framework would publish
 * the event immediately and the guarantee could not be observed.
 *
 * `DatabaseMigrations` keeps the production wiring (one transaction
 * manager shared by the container and the connection), so the event is
 * really deferred until the outermost transaction commits.
 */
uses(DatabaseMigrations::class);

it('publishes the inventory change only after the transaction committed', function (): void {
    $org = Organization::factory()->create();
    $property = Property::factory()->create(['organization_id' => $org->id]);
    $roomType = RoomType::factory()->create([
        'organization_id' => $org->id,
        'property_id' => $property->id,
        'total_units' => 10,
    ]);

    $context = app(OrganizationContext::class);
    $tenant = TenantContext::forOrganizationAndProperty($org->id, $property->id);
    $inventoryService = app(InventoryService::class);

    $checkIn = now()->addDay()->format('Y-m-d');
    $checkOut = now()->addDays(3)->format('Y-m-d');

    $context->swap($tenant, function () use ($inventoryService, $property, $roomType, $checkIn): void {
        $inventoryService->materializeDays(
            propertyId: $property->id,
            startDate: $checkIn,
            endDate: now()->addDays(5)->format('Y-m-d'),
            roomTypeIds: [$roomType->id],
        );
    });

    $published = false;
    Event::listen(InventoryChanged::class, function () use (&$published): void {
        $published = true;
    });

    DB::transaction(function () use (
        $context,
        $tenant,
        $inventoryService,
        $property,
        $roomType,
        $checkIn,
        $checkOut,
        &$published,
    ): void {
        $context->swap($tenant, function () use ($inventoryService, $property, $roomType, $checkIn, $checkOut): void {
            $inventoryService->hold(
                propertyId: $property->id,
                roomTypeId: $roomType->id,
                checkIn: $checkIn,
                checkOut: $checkOut,
                units: 1,
                idempotencyKey: 'after-commit-key',
            );
        });

        // The action committed its own transaction, but the outer one is
        // still open: the event must not be published yet.
        expect($published)->toBeFalse();
    });

    expect($published)->toBeTrue();
});
