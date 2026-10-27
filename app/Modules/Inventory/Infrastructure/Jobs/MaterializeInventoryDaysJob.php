<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Jobs;

use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Platform\Jobs\Middleware\PropagatesTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class MaterializeInventoryDaysJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int>|null  $roomTypeIds
     */
    public function __construct(
        public readonly int $propertyId,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly ?array $roomTypeIds = null,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new PropagatesTenantContext];
    }

    public function handle(InventoryService $inventoryService): void
    {
        $inventoryService->materializeDays(
            propertyId: $this->propertyId,
            startDate: $this->startDate,
            endDate: $this->endDate,
            roomTypeIds: $this->roomTypeIds,
        );
    }
}
