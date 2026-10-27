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
 * Releases a hold: the held units go back to availability.
 *
 * Idempotent: releasing an already released (or expired) hold returns it
 * unchanged. A committed hold cannot be released.
 */
final readonly class ReleaseHoldAction
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
        ?string $reason = null,
        ?int $actorId = null,
    ): InventoryHoldDTO {
        return $this->withContentionRetry(function () use ($organizationId, $holdId, $reason, $actorId): InventoryHoldDTO {
            return DB::transaction(function () use ($organizationId, $holdId, $reason, $actorId): InventoryHoldDTO {
                $hold = $this->holdRepository->findForUpdate($organizationId, $holdId);

                if ($hold === null) {
                    throw HoldNotFoundException::forId($holdId);
                }

                $status = HoldStatus::from($hold->status);

                if ($status === HoldStatus::Released || $status === HoldStatus::Expired) {
                    return $hold;
                }

                if (! $status->isActive()) {
                    throw HoldStateException::invalidTransition($status, 'release');
                }

                $dates = $this->holdDates($this->holdRepository, $organizationId, $hold);

                // The guarded transition comes FIRST: it is the gate for
                // the counter mutation. Exactly one concurrent caller
                // wins it, and only the winner gives the units back. If
                // the transition fails, the counters are still untouched.
                $transitioned = $this->holdRepository->transitionFromActive(
                    $organizationId,
                    $holdId,
                    HoldStatus::Released,
                    [
                        'released_at' => now(),
                        'release_reason' => $reason,
                    ],
                );

                if (! $transitioned) {
                    throw HoldStateException::invalidTransition($status, 'release');
                }

                $this->inventoryDayRepository->decrementHeldUnits($hold->roomTypeId, $dates, $hold->units);

                $this->auditor->recordCustom(
                    'inventory.hold.released',
                    new SubjectChange(
                        subjectId: $holdId,
                        subjectType: 'InventoryHold',
                        oldValues: ['status' => HoldStatus::Active->value],
                        newValues: [
                            'status' => HoldStatus::Released->value,
                            'reason' => $reason,
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
                    reason: 'hold.released',
                ));

                return $this->holdRepository->find($organizationId, $holdId) ?? $hold;
            });
        });
    }
}
