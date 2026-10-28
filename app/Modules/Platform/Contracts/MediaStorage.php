<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

/**
 * The storage of the files (task P1-14).
 *
 * Two kinds of files, and they are never mixed:
 *
 *  - a **private** file (an invoice, a document of a guest) is never
 *    reachable through a public URL. It is served through a signed URL
 *    that expires.
 *  - a **public** file (the photo of a room type) is served by the CDN.
 */
interface MediaStorage
{
    /**
     * Store a private file and return its path.
     */
    public function putPrivate(string $path, string $contents): string;

    /**
     * A URL that works for a while, and only for the holder of the link.
     */
    public function privateUrl(string $path, int $minutes = 15): string;

    /**
     * Store a public file and return its path.
     */
    public function putPublic(string $path, string $contents): string;

    /**
     * The URL of a public file: the one of the CDN.
     */
    public function publicUrl(string $path): string;

    /**
     * Read a private file.
     */
    public function getPrivate(string $path): ?string;

    public function deletePrivate(string $path): void;

    public function deletePublic(string $path): void;
}
