<?php

declare(strict_types=1);

namespace Tests\Fixtures\Platform;

use App\Modules\Platform\Jobs\Middleware\PropagatesTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * A job that fails, used to prove that the tenant reaches the error
 * report of a queued job (task P1-16).
 */
final class FailingTenantJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        throw new RuntimeException('the job failed');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new PropagatesTenantContext];
    }
}
