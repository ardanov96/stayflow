<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Services;

use App\Modules\Catalog\Contracts\CatalogService;
use App\Modules\Inventory\Application\Actions\BlockInventoryAction;
use App\Modules\Inventory\Application\Actions\MaterializeInventoryDaysAction;
use App\Modules\Inventory\Application\Actions\QuoteInventoryAction;
use App\Modules\Inventory\Application\Actions\SetRestrictionsAction;
use App\Modules\Inventory\Application\Actions\UnblockInventoryAction;
use App\Modules\Inventory\Application\Ports\InventoryDayRepository;
use App\Modules\Inventory\Contracts\DTOs\InventoryBlockDTO;
use App\Modules\Inventory\Contracts\DTOs\InventoryQuoteDTO;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Platform\Contracts\OrganizationContext;
use InvalidArgumentException;

final readonly class EloquentInventoryService implements InventoryService
{
    public function __construct(
        private QuoteInventoryAction $quoteInventoryAction,
        private BlockInventoryAction $blockInventoryAction,
        private UnblockInventoryAction $unblockInventoryAction,
        private SetRestrictionsAction $setRestrictionsAction,
        private MaterializeInventoryDaysAction $materializeInventoryDaysAction,
        private InventoryDayRepository $inventoryDayRepository,
        private CatalogService $catalogService,
        private OrganizationContext $organizationContext,
    ) {}

    public function quote(
        int $propertyId,
        array $roomTypeIds,
        string $checkInDate,
        string $checkOutDate,
        int $adults,
        int $children = 0,
        ?int $ratePlanId = null,
    ): InventoryQuoteDTO {
        return $this->quoteInventoryAction->execute(
            propertyId: $propertyId,
            roomTypeIds: $roomTypeIds,
            checkInDate: $checkInDate,
            checkOutDate: $checkOutDate,
            adults: $adults,
            children: $children,
            ratePlanId: $ratePlanId,
        );
    }

    public function block(
        int $propertyId,
        int $roomTypeId,
        string $startDate,
        string $endDate,
        int $units,
        string $reason,
        ?int $userId = null,
    ): InventoryBlockDTO {
        $orgId = $this->resolveOrganizationId($propertyId);

        return $this->blockInventoryAction->execute(
            organizationId: $orgId,
            propertyId: $propertyId,
            roomTypeId: $roomTypeId,
            startDate: $startDate,
            endDate: $endDate,
            units: $units,
            reason: $reason,
            actorId: $userId,
        );
    }

    public function unblock(int $blockId, ?int $userId = null): void
    {
        $this->unblockInventoryAction->execute(
            blockId: $blockId,
            actorId: $userId,
        );
    }

    public function getAvailability(
        int $propertyId,
        int $roomTypeId,
        string $startDate,
        string $endDate,
    ): array {
        return array_values($this->inventoryDayRepository->getRange(
            roomTypeId: $roomTypeId,
            startDate: $startDate,
            endDate: $endDate,
        ));
    }

    public function setRestrictions(
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
        ?int $userId = null,
    ): array {
        $orgId = $this->resolveOrganizationId($propertyId);

        return $this->setRestrictionsAction->execute(
            organizationId: $orgId,
            propertyId: $propertyId,
            ratePlanId: $ratePlanId,
            roomTypeId: $roomTypeId,
            startDate: $startDate,
            endDate: $endDate,
            minStay: $minStay,
            maxStay: $maxStay,
            cta: $cta,
            ctd: $ctd,
            stopSell: $stopSell,
            actorId: $userId,
        );
    }

    public function materializeDays(
        int $propertyId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $roomTypeIds = null,
    ): int {
        $orgId = $this->resolveOrganizationId($propertyId);

        return $this->materializeInventoryDaysAction->execute(
            organizationId: $orgId,
            propertyId: $propertyId,
            startDate: $startDate,
            endDate: $endDate,
            roomTypeIds: $roomTypeIds,
        );
    }

    private function resolveOrganizationId(int $propertyId): int
    {
        $ctx = $this->organizationContext->current();
        if ($ctx !== null) {
            return $ctx->organizationId;
        }

        $property = $this->catalogService->getProperty($propertyId);
        if ($property === null) {
            throw new InvalidArgumentException("Property {$propertyId} not found.");
        }

        return $property->organizationId;
    }
}
