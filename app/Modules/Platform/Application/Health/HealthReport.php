<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Health;

/**
 * The health of the running application.
 *
 * The deployment pipeline reads this report through the HTTP health
 * endpoint: a report that is not healthy means the release is not serving
 * and must be rolled back.
 */
final readonly class HealthReport
{
    /**
     * @param  list<HealthCheckResult>  $checks
     */
    public function __construct(
        public array $checks,
        public string $version,
        public string $environment,
    ) {}

    /**
     * Whether every check passed.
     */
    public function healthy(): bool
    {
        foreach ($this->checks as $check) {
            if (! $check->healthy) {
                return false;
            }
        }

        return true;
    }

    /**
     * The names of the checks that failed.
     *
     * @return list<string>
     */
    public function failedChecks(): array
    {
        $failed = [];

        foreach ($this->checks as $check) {
            if (! $check->healthy) {
                $failed[] = $check->name;
            }
        }

        return $failed;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $checks = [];

        foreach ($this->checks as $check) {
            $checks[$check->name] = $check->toArray();
        }

        return [
            'status' => $this->healthy() ? 'ok' : 'degraded',
            'version' => $this->version,
            'environment' => $this->environment,
            'checks' => $checks,
        ];
    }
}
