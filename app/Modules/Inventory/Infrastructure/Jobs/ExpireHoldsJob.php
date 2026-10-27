<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Jobs;

use App\Modules\Inventory\Application\Actions\ExpireHoldsAction;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Jobs\Middleware\PropagatesTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Expires the inventory holds of ONE organization whose time-to-live has
 * passed and gives the units back to availability.
 *
 * The job carries its own tenant context, so every query it runs stays
 * inside the organization (AGENTS.md §3.4). The scheduler fans the work
 * out per organization (see `routes/console.php`).
 */
final class ExpireHoldsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum number of holds expired per run.
     */
    public const BATCH_SIZE = 500;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    public function __construct(
        public readonly int $organizationId,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new PropagatesTenantContext(TenantContext::forOrganization($this->organizationId)),
        ];
    }

    public function handle(ExpireHoldsAction $expireHoldsAction): void
    {
        $expired = $expireHoldsAction->execute($this->organizationId, self::BATCH_SIZE);

        if ($expired > 0) {
            Log::info('inventory.holds.expired', [
                'organization_id' => $this->organizationId,
                'count' => $expired,
            ]);
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('inventory.holds.expiry_failed', [
            'organization_id' => $this->organizationId,
            'error' => $e?->getMessage(),
        ]);
    }
}
