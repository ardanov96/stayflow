<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Inventory\Application\Ports\HoldRepository;
use App\Modules\Inventory\Application\Ports\InventoryDayRepository;
use App\Modules\Inventory\Application\Support\ResolvesHoldDates;
use App\Modules\Inventory\Application\Support\RetriesOnContention;
use App\Modules\Inventory\Contracts\DTOs\InventoryHoldDTO;
use App\Modules\Inventory\Domain\Events\InventoryChanged;
use App\Modules\Inventory\Domain\Exceptions\HoldNotFoundException;
use App\Modules\Inventory\Domain\Exceptions\HoldStateException;
use App\Modules\Inventory\Domain\Hold\HoldStatus;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use Illuminate\Support\Facades\DB;

/**
 * Commits a hold: the held units become sold units.
 *
 * Idempotent: committing an already committed hold returns it unchanged.
 */
final readonly class CommitHoldAction
{
    use ResolvesHoldDates;
    use RetriesOnContention;

    public function __construct(
        private HoldRepository $holdRepository,
        private InventoryDayRepository $inventoryDayRepository,
        private Auditor $auditor,
    ) {}

    public function execute(
        int $organizationId,
        int $holdId,
        ?int $reservationId = null,
        ?int $actorId = null,
    ): InventoryHoldDTO {
        return $this->withContentionRetry(function () use ($organizationId, $holdId, $reservationId, $actorId): InventoryHoldDTO {
            return DB::transaction(function () use ($organizationId, $holdId, $reservationId, $actorId): InventoryHoldDTO {
                $hold = $this->holdRepository->findForUpdate($organizationId, $holdId);

                if ($hold === null) {
                    throw HoldNotFoundException::forId($holdId);
                }

                $status = HoldStatus::from($hold->status);

                if ($status === HoldStatus::Committed) {
                    return $hold;
                }

                if (! $status->isActive()) {
                    throw HoldStateException::invalidTransition($status, 'commit');
                }

                $dates = $this->holdDates($this->holdRepository, $organizationId, $hold);

                // The guarded transition comes FIRST: it is the gate for
                // the counter mutation. Exactly one concurrent caller
                // wins it, and only the winner converts the held units
                // into sold units.
                $transitioned = $this->holdRepository->transitionFromActive(
                    $organizationId,
                    $holdId,
                    HoldStatus::Committed,
                    [
                        'reservation_id' => $reservationId,
                        'committed_at' => now(),
                    ],
                );

                if (! $transitioned) {
                    throw HoldStateException::invalidTransition($status, 'commit');
                }

                $this->inventoryDayRepository->convertHeldUnitsToSold($hold->roomTypeId, $dates, $hold->units);

                $this->auditor->recordCustom(
                    'inventory.hold.committed',
                    new SubjectChange(
                        subjectId: $holdId,
                        subjectType: 'InventoryHold',
                        oldValues: ['status' => HoldStatus::Active->value],
                        newValues: [
                            'status' => HoldStatus::Committed->value,
                            'reservation_id' => $reservationId,
                        ],
                        metadata: $actorId !== null ? ['actor_id' => $actorId] : [],
                    ),
                    new TenantHint(
                        organizationId: $organizationId,
                        propertyId: $hold->propertyId,
                    ),
                );

                event(new InventoryChanged(
                    organizationId: $organizationId,
                    propertyId: $hold->propertyId,
                    roomTypeIds: [$hold->roomTypeId],
                    startDate: $hold->checkIn,
                    endDate: $hold->checkOut,
                    reason: 'hold.committed',
                ));

                return $this->holdRepository->find($organizationId, $holdId) ?? $hold;
            });
        });
    }
}
