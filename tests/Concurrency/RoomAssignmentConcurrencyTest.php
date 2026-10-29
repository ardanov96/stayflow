<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\RoomUnit;
use App\Modules\Inventory\Infrastructure\Models\RatePlan;
use App\Modules\Inventory\Infrastructure\Models\RatePrice;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Reservations\Contracts\ReservationService;
use Symfony\Component\Process\Process;

/**
 * The invariant 7 of docs/DOMAIN_MODEL.md section 3: *a room unit cannot
 * be assigned to two reservations on overlapping nights* (task P2-05).
 *
 * The database is the arbiter: two processes try to assign the same room
 * to two reservations at the same time, and exactly one of them may win.
 */
it('assigns a room unit to only one reservation when two try at the same time', function (): void {
    $roomType = $this->createRoomType(2);

    $ratePlan = $this->context->swap(
        TenantContext::forOrganization($this->organization->id),
        fn () => RatePlan::factory()->create([
            'organization_id' => $this->organization->id,
            'property_id' => $this->property->id,
            'code' => 'BAR',
            'name' => 'Best Available Rate',
        ]),
    );

    foreach (['2026-11-01', '2026-11-02'] as $date) {
        $this->context->swap(
            TenantContext::forOrganization($this->organization->id),
            fn () => RatePrice::factory()->create([
                'organization_id' => $this->organization->id,
                'property_id' => $this->property->id,
                'rate_plan_id' => $ratePlan->id,
                'room_type_id' => $roomType->id,
                'date' => $date,
                'base_price_minor' => 500_000,
                'currency' => 'IDR',
                'extra_adult_price_minor' => 0,
                'extra_child_price_minor' => 0,
            ]),
        );
    }

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->organization->id, $this->property->id),
        fn () => $this->inventoryService->materializeDays(
            propertyId: $this->property->id,
            startDate: '2026-11-01',
            endDate: '2026-11-04',
        ),
    );

    $unit = $this->context->swap(
        TenantContext::forOrganization($this->organization->id),
        fn () => RoomUnit::factory()->create([
            'organization_id' => $this->organization->id,
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'number' => '201',
        ]),
    );

    $reservations = $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->organization->id, $this->property->id),
        function () use ($roomType, $ratePlan): array {
            $reservations = [];

            foreach (['concurrent-a', 'concurrent-b'] as $key) {
                $reservations[] = app(ReservationService::class)->create(
                    propertyId: $this->property->id,
                    roomTypeId: $roomType->id,
                    ratePlanId: $ratePlan->id,
                    arrival: '2026-11-01',
                    departure: '2026-11-03',
                    adults: 2,
                    children: 0,
                    guestId: 7,
                    idempotencyKey: $key,
                );
            }

            return $reservations;
        },
    );

    // Two processes, at the same time, the same room.
    $processes = [];

    foreach ($reservations as $reservation) {
        $process = new Process([
            PHP_BINARY,
            base_path('tests/Fixtures/assign-room-unit.php'),
            (string) $this->organization->id,
            (string) $reservation->id,
            (string) $roomType->id,
            (string) $unit->id,
        ]);

        $process->start();

        $processes[] = $process;
    }

    foreach ($processes as $process) {
        $process->wait();
    }

    $succeeded = 0;

    foreach ($processes as $process) {
        if ($process->isSuccessful()) {
            $succeeded++;
        }
    }

    expect($succeeded)->toBe(1)
        ->and(DB::table('reservation_room_nights')->where('room_unit_id', $unit->id)->count())->toBe(2);
});
