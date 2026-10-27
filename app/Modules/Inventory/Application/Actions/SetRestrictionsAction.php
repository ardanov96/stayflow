<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Inventory\Application\Ports\RestrictionDayRepository;
use App\Modules\Inventory\Contracts\DTOs\RestrictionDayDTO;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
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
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);

        if ($start > $end) {
            throw new InvalidArgumentException("Start date {$startDate} cannot be after end date {$endDate}.");
        }

        if ($minStay !== null && $maxStay !== null && $minStay > $maxStay) {
            throw new InvalidArgumentException("minStay ({$minStay}) cannot be greater than maxStay ({$maxStay}).");
        }

        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
        $records = [];

        foreach ($period as $date) {
            $records[] = [
                'organization_id' => $organizationId,
                'property_id' => $propertyId,
                'rate_plan_id' => $ratePlanId,
                'room_type_id' => $roomTypeId,
                'date' => $date->format('Y-m-d'),
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
