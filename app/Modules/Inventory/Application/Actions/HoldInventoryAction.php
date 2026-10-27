<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Catalog\Contracts\CatalogService;
use App\Modules\Inventory\Application\Ports\HoldRepository;
use App\Modules\Inventory\Application\Ports\InventoryDayRepository;
use App\Modules\Inventory\Application\Support\ContentionDetector;
use App\Modules\Inventory\Application\Support\RetriesOnContention;
use App\Modules\Inventory\Contracts\DTOs\InventoryHoldDTO;
use App\Modules\Inventory\Domain\Availability\InventoryWindow;
use App\Modules\Inventory\Domain\Events\InventoryChanged;
use App\Modules\Inventory\Domain\Exceptions\IdempotencyConflictException;
use App\Modules\Inventory\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Inventory\Domain\Exceptions\OutsideMaterializedWindowException;
use App\Modules\Inventory\Domain\Hold\HoldRequest;
use App\Modules\Inventory\Domain\Hold\HoldStatus;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Places a temporary hold on inventory units.
 *
 * Transactional and idempotent:
 *  - the idempotency key is claimed FIRST (with an INSERT), the
 *    inventory rows SECOND, so concurrent writers always take the locks
 *    in the same order;
 *  - replaying the same idempotency key with the same payload returns
 *    the existing hold without touching the counters;
 *  - replaying it with a different payload is rejected.
 *
 * @see docs/CONCURRENCY.md for the proofs this behaviour relies on.
 */
final readonly class HoldInventoryAction
{
    use RetriesOnContention;

    public function __construct(
        private HoldRepository $holdRepository,
        private InventoryDayRepository $inventoryDayRepository,
        private CatalogService $catalogService,
        private Auditor $auditor,
    ) {}

    public function execute(
        int $organizationId,
        HoldRequest $request,
        string $idempotencyKey,
        ?int $actorId = null,
    ): InventoryHoldDTO {
        $this->assertValidIdempotencyKey($idempotencyKey);
        $this->assertRoomTypeBelongsToProperty($organizationId, $request);
        $this->assertWithinMaterializedWindow($request);

        try {
            return $this->withContentionRetry(
                fn (): InventoryHoldDTO => $this->placeHold($organizationId, $request, $idempotencyKey, $actorId)
            );
        } catch (Throwable $e) {
            if (! ContentionDetector::isDuplicateIdempotencyKey($e)) {
                throw $e;
            }

            // Another transaction claimed the same key first. The unique
            // index is the arbiter, and the loser only sees the violation
            // AFTER the winner committed, so the winning hold is readable
            // right now.
            $winner = $this->findWinner($organizationId, $idempotencyKey);

            if ($winner === null) {
                throw $e;
            }

            return $this->resolveExisting($winner, $request, $idempotencyKey);
        }
    }

    private function placeHold(
        int $organizationId,
        HoldRequest $request,
        string $idempotencyKey,
        ?int $actorId,
    ): InventoryHoldDTO {
        return DB::transaction(function () use ($organizationId, $request, $idempotencyKey, $actorId): InventoryHoldDTO {
            $dates = $request->stayDates();
            $firstDate = $dates[0];
            $lastDate = $dates[count($dates) - 1];

            // 1. Claim the idempotency key FIRST, with an INSERT.
            //
            // The row is inserted, never selected `FOR UPDATE`, on purpose:
            // a locking read of a *missing* unique key takes an InnoDB gap
            // lock. Two writers that both hold that gap lock deadlock each
            // other when they insert their own row into the same gap, which
            // is exactly what a burst of concurrent holds does. The unique
            // index arbitrates the race instead: the loser blocks inside the
            // INSERT until the winner commits, then gets the
            // unique-constraint violation and returns the winning hold.
            //
            // The Laravel clock is used on purpose (`CarbonImmutable::now()`
            // instead of `new DateTimeImmutable`): the time-to-live must
            // follow the same clock as the expiry query, and the clock has
            // to be freezable in tests.
            $now = CarbonImmutable::now();
            $hold = $this->holdRepository->create([
                'organization_id' => $organizationId,
                'property_id' => $request->propertyId,
                'room_type_id' => $request->roomTypeId,
                'check_in' => $request->checkIn,
                'check_out' => $request->checkOut,
                'units' => $request->units,
                'status' => HoldStatus::Active->value,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => $request->expiresAt($now),
            ]);

            // 2. Inventory rows second, always in (room_type_id, date) order.
            $locked = $this->inventoryDayRepository->lockRangeForRoomTypes(
                [$request->roomTypeId],
                $firstDate,
                $lastDate,
            );
            $days = $locked[$request->roomTypeId] ?? [];

            // 3. Every stay date must be materialized.
            foreach ($dates as $date) {
                if (! isset($days[$date])) {
                    throw OutsideMaterializedWindowException::unmaterialized($request->roomTypeId, $date);
                }
            }

            // 4. Availability check on the locked rows.
            foreach ($dates as $date) {
                $day = $days[$date];

                if ($day->availableUnits < $request->units) {
                    throw InsufficientInventoryException::forDate(
                        roomTypeId: $request->roomTypeId,
                        date: $date,
                        requested: $request->units,
                        available: $day->availableUnits,
                    );
                }
            }

            // 5. Hold the units.
            $this->inventoryDayRepository->incrementHeldUnits($request->roomTypeId, $dates, $request->units);

            // 6. Persist the stay-date lines of the hold.
            $lines = [];
            foreach ($dates as $date) {
                $lines[] = ['date' => $date, 'units' => $request->units];
            }

            $this->holdRepository->createLines($organizationId, $hold->id, $request->propertyId, $lines);

            // 7. Audit trail, inside the same transaction.
            $this->auditor->recordCustom(
                'inventory.hold.created',
                new SubjectChange(
                    subjectId: $hold->id,
                    subjectType: 'InventoryHold',
                    oldValues: null,
                    newValues: [
                        'room_type_id' => $request->roomTypeId,
                        'check_in' => $request->checkIn,
                        'check_out' => $request->checkOut,
                        'units' => $request->units,
                    ],
                    metadata: $actorId !== null ? ['actor_id' => $actorId] : [],
                ),
                new TenantHint(
                    organizationId: $organizationId,
                    propertyId: $request->propertyId,
                ),
            );

            // 8. Published after the transaction committed.
            event(new InventoryChanged(
                organizationId: $organizationId,
                propertyId: $request->propertyId,
                roomTypeIds: [$request->roomTypeId],
                startDate: $firstDate,
                endDate: $lastDate,
                reason: 'hold.created',
            ));

            return $hold;
        });
    }

    /**
     * Replay of a known idempotency key: the same payload returns the
     * existing hold, a different payload is a conflict.
     */
    private function resolveExisting(
        InventoryHoldDTO $existing,
        HoldRequest $request,
        string $idempotencyKey,
    ): InventoryHoldDTO {
        $samePayload = $existing->propertyId === $request->propertyId
            && $existing->roomTypeId === $request->roomTypeId
            && $existing->checkIn === $request->checkIn
            && $existing->checkOut === $request->checkOut
            && $existing->units === $request->units;

        if (! $samePayload) {
            throw IdempotencyConflictException::forKey($idempotencyKey);
        }

        return $existing;
    }

    /**
     * The hold that owns the idempotency key.
     *
     * The winner commits before the loser receives the unique-constraint
     * violation, so the row is already there. The tiny retry loop only
     * covers the (practically impossible) case where the read lands
     * before the winner's transaction became visible.
     */
    private function findWinner(int $organizationId, string $idempotencyKey): ?InventoryHoldDTO
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $winner = $this->holdRepository->findByIdempotencyKey($organizationId, $idempotencyKey);

            if ($winner !== null) {
                return $winner;
            }

            usleep(10_000);
        }

        return null;
    }

    private function assertValidIdempotencyKey(string $idempotencyKey): void
    {
        $length = strlen($idempotencyKey);

        if ($length === 0) {
            throw new InvalidArgumentException('The idempotency key must not be empty.');
        }

        if ($length > 64) {
            throw new InvalidArgumentException("The idempotency key must be at most 64 characters, got {$length}.");
        }
    }

    private function assertRoomTypeBelongsToProperty(int $organizationId, HoldRequest $request): void
    {
        $roomType = $this->catalogService->getRoomType($request->roomTypeId);

        if ($roomType === null) {
            throw new InvalidArgumentException("Room type {$request->roomTypeId} not found.");
        }

        if ($roomType->organizationId !== $organizationId) {
            throw new InvalidArgumentException("Room type {$request->roomTypeId} belongs to another organization.");
        }

        if ($roomType->propertyId !== $request->propertyId) {
            throw new InvalidArgumentException(
                "Room type {$request->roomTypeId} does not belong to property {$request->propertyId}."
            );
        }
    }

    private function assertWithinMaterializedWindow(HoldRequest $request): void
    {
        if (! InventoryWindow::isWithinWindow($request->checkIn)) {
            throw OutsideMaterializedWindowException::forDate($request->checkIn);
        }

        if (! InventoryWindow::isWithinWindow($request->checkOut)) {
            throw OutsideMaterializedWindowException::forDate($request->checkOut);
        }
    }
}
