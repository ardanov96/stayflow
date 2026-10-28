<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Contracts\MediaStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a private file.
 *
 * The route is signed and expires: without a valid signature the file is
 * not reachable at all. The file itself lives on a disk the web server
 * does not serve, so there is no second way to reach it.
 */
final class MediaDownloadController
{
    public function __invoke(Request $request, MediaStorage $storage, string $path): Response
    {
        $contents = $storage->getPrivate($path);

        if ($contents === null) {
            abort(404);
        }

        return response($contents, 200, [
            'Content-Type' => 'application/octet-stream',
            // A private file must not sit in a cache of a proxy.
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
