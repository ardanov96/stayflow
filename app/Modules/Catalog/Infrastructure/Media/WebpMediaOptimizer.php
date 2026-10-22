<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Media;

use App\Modules\Catalog\Application\Ports\ImageOptimizer;
use App\Modules\Catalog\Application\Ports\OptimizedMediaResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Adapter that optimizes uploaded images to WebP format.
 *
 * If GD/WebP is available in the PHP environment, converts and compresses to .webp.
 * Otherwise, securely stores the original image while validating type and dimensions.
 */
class WebpMediaOptimizer implements ImageOptimizer
{
    public function optimize(
        UploadedFile $file,
        string $disk = 'public',
        string $directory = 'catalog',
    ): OptimizedMediaResult {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Uploaded file is not valid or corrupted.');
        }

        $realPath = $file->getRealPath();
        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false) {
            throw new InvalidArgumentException('File is not a valid image.');
        }

        [$origWidth, $origHeight] = $imageInfo;
        $mime = (string) $imageInfo['mime'];

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($mime, $allowedMimes, true)) {
            throw new InvalidArgumentException("Unsupported image format: {$mime}. Only JPEG, PNG, and WebP are allowed.");
        }

        $hashName = Str::random(40);
        $storage = Storage::disk($disk);

        if (extension_loaded('gd') && function_exists('imagewebp')) {
            $source = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($realPath),
                'image/png' => @imagecreatefrompng($realPath),
                default => @imagecreatefromwebp($realPath),
            };

            if ($source !== false) {
                // Ensure truecolor
                $width = imagesx($source);
                $height = imagesy($source);

                $targetWidth = min($width, 2048);
                $targetHeight = (int) round(($height / $width) * $targetWidth);

                $target = imagecreatetruecolor($targetWidth, $targetHeight);
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

                ob_start();
                imagewebp($target, null, 85);
                $webpData = (string) ob_get_clean();

                imagedestroy($source);
                imagedestroy($target);

                $filePath = trim($directory, '/').'/'.$hashName.'.webp';
                $storage->put($filePath, $webpData);

                return new OptimizedMediaResult(
                    disk: $disk,
                    filePath: $filePath,
                    fileName: $hashName.'.webp',
                    mimeType: 'image/webp',
                    fileSize: strlen($webpData),
                    width: $targetWidth,
                    height: $targetHeight,
                );
            }
        }

        // Fallback when GD imagewebp is not available
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $fileName = $hashName.'.'.$extension;
        $filePath = $file->storeAs($directory, $fileName, $disk);

        return new OptimizedMediaResult(
            disk: $disk,
            filePath: (string) $filePath,
            fileName: $fileName,
            mimeType: $mime,
            fileSize: $file->getSize(),
            width: (int) $origWidth,
            height: (int) $origHeight,
        );
    }
}
