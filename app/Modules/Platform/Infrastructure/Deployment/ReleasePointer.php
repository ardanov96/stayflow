<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Deployment;

use RuntimeException;

/**
 * The pointer to the release that is currently serving.
 *
 * On the deployment target (Linux) the pointer is a symlink, so the swap
 * is an atomic rename and the web server picks the new release up on its
 * next request without a reload.
 *
 * Where symlinks are not available (a developer machine without the
 * privilege to create them) the pointer is a plain file holding the path
 * of the release. Both forms are read the same way, so the pipeline and
 * its tests behave identically everywhere.
 */
final class ReleasePointer
{
    /**
     * @var array<string, bool>
     */
    private static array $symlinkSupport = [];

    public function __construct(private readonly string $root) {}

    public function pointerPath(): string
    {
        return $this->root.DIRECTORY_SEPARATOR.'current';
    }

    /**
     * The release that is serving, or null when nothing was published yet.
     */
    public function currentPath(): ?string
    {
        $pointer = $this->pointerPath();

        if (is_link($pointer)) {
            $target = readlink($pointer);

            return $target === false ? null : $this->absolute($target);
        }

        if (is_file($pointer)) {
            $contents = trim((string) file_get_contents($pointer));

            return $contents === '' ? null : $contents;
        }

        return null;
    }

    /**
     * Point the server at another release.
     *
     * The swap is a rename over the old pointer, so a request that is
     * already being served is never interrupted.
     */
    public function swap(string $releasePath): void
    {
        $pointer = $this->pointerPath();
        $directory = dirname($pointer);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $temporary = $pointer.'.tmp'.bin2hex(random_bytes(4));

        if ($this->symlinksSupported()) {
            if (! symlink($releasePath, $temporary)) {
                throw new RuntimeException("Could not create the release pointer at {$temporary}.");
            }
        } else {
            file_put_contents($temporary, $releasePath);
        }

        if (! rename($temporary, $pointer)) {
            @unlink($temporary);

            throw new RuntimeException("Could not point the server at {$releasePath}.");
        }
    }

    /**
     * Whether this platform can create symlinks.
     */
    public function symlinksSupported(): bool
    {
        if (isset(self::$symlinkSupport[$this->root])) {
            return self::$symlinkSupport[$this->root];
        }

        if (! is_dir($this->root)) {
            return self::$symlinkSupport[$this->root] = false;
        }

        $probe = $this->root.DIRECTORY_SEPARATOR.'.symlink-probe'.bin2hex(random_bytes(4));

        $supported = @symlink($this->root, $probe);
        @unlink($probe);

        return self::$symlinkSupport[$this->root] = $supported;
    }

    /**
     * The releases of the server, newest first.
     *
     * @return list<string>
     */
    public function releases(): array
    {
        $directory = $this->root.DIRECTORY_SEPARATOR.'releases';

        if (! is_dir($directory)) {
            return [];
        }

        $entries = scandir($directory) ?: [];
        $releases = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($path)) {
                $releases[] = $path;
            }
        }

        rsort($releases);

        return $releases;
    }

    private function absolute(string $path): string
    {
        if ($path !== '' && ($path[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1)) {
            return $path;
        }

        return $this->root.DIRECTORY_SEPARATOR.$path;
    }
}
