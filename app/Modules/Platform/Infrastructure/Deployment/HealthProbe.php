<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Deployment;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Probes the health endpoint of the running application.
 *
 * The endpoint answers 200 with a JSON body whose `status` is `ok` when
 * the release is healthy. Anything else (a connection error, a timeout, a
 * 500, a 503, a body that says `degraded`) means the release must not
 * serve traffic.
 */
final class HealthProbe
{
    public function __construct(
        private readonly int $timeoutSeconds = 30,
        private readonly int $intervalMs = 500,
    ) {}

    /**
     * Wait until the endpoint is healthy, or until the timeout expires.
     */
    public function waitUntilHealthy(string $url): bool
    {
        $deadline = microtime(true) + $this->timeoutSeconds;

        do {
            if ($this->isHealthy($url)) {
                return true;
            }

            usleep($this->intervalMs * 1000);
        } while (microtime(true) < $deadline);

        return false;
    }

    /**
     * One probe of the endpoint.
     */
    public function isHealthy(string $url): bool
    {
        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->withoutRedirecting()
                ->get($url);
        } catch (Throwable) {
            return false;
        }

        if ($response->status() !== 200) {
            return false;
        }

        $payload = $response->json();

        return is_array($payload) && ($payload['status'] ?? null) === 'ok';
    }
}
