<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Media;

use App\Modules\Platform\Contracts\MediaStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * The storage of the files, on the disks of the application.
 *
 * The private file is never served by the web server: on a disk that
 * supports it, the URL is a temporary one of the provider; on the local
 * disk, it is a signed route to the download controller, which reads the
 * file from the private disk and sends it. Either way, the URL carries a
 * signature and it expires.
 */
final class FilesystemMediaStorage implements MediaStorage
{
    public function __construct(
        private readonly string $privateDisk,
        private readonly string $publicDisk,
        private readonly string $publicUrl,
        private readonly bool $privateDiskTemporaryUrls = false,
    ) {}

    public function putPrivate(string $path, string $contents): string
    {
        Storage::disk($this->privateDisk)->put($path, $contents);

        return $path;
    }

    public function privateUrl(string $path, int $minutes = 15): string
    {
        if ($this->privateDiskTemporaryUrls) {
            // The disk signs the URL itself (S3). The URL of a local file
            // is **not** signed, so it is never used for a private file.
            return Storage::disk($this->privateDisk)->temporaryUrl($path, now()->addMinutes($minutes));
        }

        // The file is served by the application itself, through a signed
        // route that expires.
        return URL::temporarySignedRoute(
            'platform.media.private',
            now()->addMinutes($minutes),
            ['path' => $path],
        );
    }

    public function putPublic(string $path, string $contents): string
    {
        Storage::disk($this->publicDisk)->put($path, $contents);

        return $path;
    }

    public function publicUrl(string $path): string
    {
        return rtrim($this->publicUrl, '/').'/'.ltrim($path, '/');
    }

    public function getPrivate(string $path): ?string
    {
        $disk = Storage::disk($this->privateDisk);

        if (! $disk->exists($path)) {
            return null;
        }

        $contents = $disk->get($path);

        return is_string($contents) ? $contents : null;
    }

    public function deletePrivate(string $path): void
    {
        Storage::disk($this->privateDisk)->delete($path);
    }

    public function deletePublic(string $path): void
    {
        Storage::disk($this->publicDisk)->delete($path);
    }
}
