<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Infrastructure\Health\HealthChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * The health endpoint of the running application.
 *
 * This is the contract the deployment pipeline uses to decide whether a
 * release is good: the pipeline publishes the release, probes this
 * endpoint, and rolls back automatically when the answer is not 200.
 *
 * It answers 200 when every component is healthy and 503 otherwise. The
 * body names the failing components but never carries the reason: the
 * reason is in the log.
 *
 * The route carries no middleware on purpose: the probe has to work when
 * the session store, the tenant context, or the authentication layer are
 * exactly what is broken.
 */
final class HealthCheckController extends Controller
{
    public function __construct(private readonly HealthChecker $checker) {}

    public function __invoke(): JsonResponse
    {
        $report = $this->checker->report();

        return response()->json(
            $report->toArray(),
            $report->healthy() ? 200 : 503,
        );
    }
}
