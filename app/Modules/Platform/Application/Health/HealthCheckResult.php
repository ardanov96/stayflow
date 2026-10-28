<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Health;

/**
 * The outcome of one health check.
 *
 * Pure value object: no framework, no persistence.
 *
 * It carries no reason. The reason for a failure goes to the log: a
 * health endpoint is public, and an exception message can hold a DSN or
 * a path. The endpoint names the component that failed, nothing more.
 */
final readonly class HealthCheckResult
{
    public function __construct(
        public string $name,
        public bool $healthy,
        public float $durationMs,
    ) {}

    /**
     * @return array{status: string, duration_ms: float}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->healthy ? 'ok' : 'failed',
            'duration_ms' => round($this->durationMs, 2),
        ];
    }
}
