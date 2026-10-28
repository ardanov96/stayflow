<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Deployment;

/**
 * What happened to a release that was published.
 */
final readonly class PublishOutcome
{
    public function __construct(
        public string $releasePath,
        public bool $healthy,
        public bool $rolledBack,
        public ?string $restoredPath = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'release' => $this->releasePath,
            'healthy' => $this->healthy,
            'rolled_back' => $this->rolledBack,
            'restored' => $this->restoredPath,
        ];
    }
}
