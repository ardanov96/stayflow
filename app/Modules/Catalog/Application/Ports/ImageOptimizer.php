<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Ports;

use Illuminate\Http\UploadedFile;

/**
 * Port for validating, resizing, and converting images to WebP format.
 */
interface ImageOptimizer
{
    /**
     * Validates and optimizes the uploaded image file to WebP format,
     * storing it in the specified disk and directory.
     */
    public function optimize(
        UploadedFile $file,
        string $disk = 'public',
        string $directory = 'catalog',
    ): OptimizedMediaResult;
}
