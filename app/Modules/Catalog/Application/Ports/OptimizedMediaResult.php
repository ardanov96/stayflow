<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Ports;

/**
 * Result of image validation and optimization.
 */
final readonly class OptimizedMediaResult
{
    public function __construct(
        public string $disk,
        public string $filePath,
        public string $fileName,
        public string $mimeType,
        public int $fileSize,
        public ?int $width = null,
        public ?int $height = null,
    ) {}
}
