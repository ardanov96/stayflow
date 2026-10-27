<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Inventory\Application\Ports\HoldRepository;
use App\Modules\Inventory\Application\Ports\InventoryDayRepository;
use App\Modules\Inventory\Application\Support\ResolvesHoldDates;
use App\Modules\Inventory\Application\Support\RetriesOnContention;
use App\Modules\Inventory\Contracts\DTOs\InventoryHoldDTO;
use App\Modules\Inventory\Domain\Events\InventoryChanged;
use App\Modules\Inventory\Domain\Hold\HoldStatus;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Expires the holds that passed their time-to-live and gives the units
 * back to availability.
 *
 * Runs per organization: the job carries the tenant context, so every
 * query stays inside the organization (AGENTS.md §3.4). Each hold is
 * expired in its own transaction, so one failure never rolls back the
 * others.
 */
final readonly class ExpireHoldsAction
{
    use ResolvesHoldDates;
    use RetriesOnContention;

    public function __construct(
        private HoldRepository $holdRepository,
        private InventoryDayRepository $inventoryDayRepository,
        private Auditor $auditor,
    ) {}

    /**
     * @return int Number of holds expired.
     */
    public function execute(int $organizationId, int $limit = 500): int
    {
        $expired = 0;

        foreach ($this->holdRepository->findExpired($organizationId, $limit) as $candidate) {
            if ($this->expireOne($organizationId, $candidate)) {
                $expired++;
            }
        }

        return $expired;
    }

    private function expireOne(int $organizationId, InventoryHoldDTO $candidate): bool
    {
        return $this->withContentionRetry(function () use ($organizationId, $candidate): bool {
            return DB::transaction(function () use ($organizationId, $candidate): bool {
                $hold = $this->holdRepository->findForUpdate($organizationId, $candidate->id);

                if ($hold === null) {
                    return false;
                }

                $status = HoldStatus::from($hold->status);

                if (! $status->isActive()) {
                    return false;
                }

                // The same clock as the expiry query (`now()`): a hold is
                // expired when its deadline is in the past.
                if (CarbonImmutable::now() < new DateTimeImmutable($hold->expiresAt)) {
                    return false;
                }

                $dates = $this->holdDates($this->holdRepository, $organizationId, $hold);

                // The guarded transition comes FIRST: it is the gate for
                // the counter mutation. Exactly one concurrent caller
                // wins it, and only the winner gives the units back. A
                // caller that loses the race returns `false` with the
                // counters untouched — the units are returned once, by
                // the winner, even when the expiry job runs twice.
                $transitioned = $this->holdRepository->transitionFromActive(
                    $organizationId,
                    $hold->id,
                    HoldStatus::Expired,
                    ['expired_at' => now()],
                );

                if (! $transitioned) {
                    return false;
                }

                $this->inventoryDayRepository->decrementHeldUnits($hold->roomTypeId, $dates, $hold->units);

                $this->auditor->recordCustom(
                    'inventory.hold.expired',
                    new SubjectChange(
                        subjectId: $hold->id,
                        subjectType: 'InventoryHold',
                        oldValues: ['status' => HoldStatus::Active->value],
                        newValues: ['status' => HoldStatus::Expired->value],
                        metadata: [],
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
                    reason: 'hold.expired',
                ));

                return true;
            });
        });
    }
}
