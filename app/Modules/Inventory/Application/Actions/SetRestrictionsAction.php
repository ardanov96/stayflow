<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Inventory\Application\Ports\RestrictionDayRepository;
use App\Modules\Inventory\Contracts\DTOs\RestrictionDayDTO;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Support\Date\StayDate;
use InvalidArgumentException;

final readonly class SetRestrictionsAction
{
    public function __construct(
        private RestrictionDayRepository $restrictionDayRepository,
        private Auditor $auditor,
    ) {}

    /**
     * @return array<int, RestrictionDayDTO>
     */
    public function execute(
        int $organizationId,
        int $propertyId,
        int $ratePlanId,
        int $roomTypeId,
        string $startDate,
        string $endDate,
        ?int $minStay = null,
        ?int $maxStay = null,
        ?bool $cta = null,
        ?bool $ctd = null,
        ?bool $stopSell = null,
        ?int $actorId = null,
    ): array {
        $start = StayDate::fromString($startDate);
        $end = StayDate::fromString($endDate);

        if ($start->isAfter($end)) {
            throw new InvalidArgumentException("Start date {$startDate} cannot be after end date {$endDate}.");
        }

        if ($minStay !== null && $maxStay !== null && $minStay > $maxStay) {
            throw new InvalidArgumentException("minStay ({$minStay}) cannot be greater than maxStay ({$maxStay}).");
        }

        $records = [];

        foreach ($start->range($end->plusDays(1)) as $date) {
            $records[] = [
                'organization_id' => $organizationId,
                'property_id' => $propertyId,
                'rate_plan_id' => $ratePlanId,
                'room_type_id' => $roomTypeId,
                'date' => $date->toString(),
                'min_stay' => $minStay,
                'max_stay' => $maxStay,
                'cta' => $cta ?? false,
                'ctd' => $ctd ?? false,
                'stop_sell' => $stopSell ?? false,
            ];
        }

        $saved = $this->restrictionDayRepository->setRestrictionsBatch($records);

        if ($actorId !== null) {
            $this->auditor->recordCustom(
                'restrictions.updated',
                new SubjectChange(
                    subjectId: $ratePlanId,
                    subjectType: 'RatePlan',
                    oldValues: null,
                    newValues: [
                        'room_type_id' => $roomTypeId,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'min_stay' => $minStay,
                        'max_stay' => $maxStay,
                        'cta' => $cta,
                        'ctd' => $ctd,
                        'stop_sell' => $stopSell,
                    ],
                    metadata: [
                        'actor_id' => $actorId,
                    ],
                ),
                new TenantHint(
                    organizationId: $organizationId,
                    propertyId: $propertyId,
                ),
            );
        }

        return $saved;
    }
}
