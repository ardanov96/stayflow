<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Deployment;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * The filesystem side of a deployment.
 *
 * A server holds one directory per release under <root>/releases. A
 * release is prepared (code, dependencies, migrations, caches, secrets)
 * and only then does the pointer move to it, so a release that fails to
 * prepare never becomes the one that serves traffic.
 *
 * The secrets never live in the repository: they are kept in
 * <root>/shared/.env and copied into the release that serves.
 */
final class ReleaseManager
{
    /**
     * Paths that are never copied from the source tree into a release.
     *
     * @var list<string>
     */
    private const EXCLUDED_PATHS = [
        '.git',
        '.github',
        '.idea',
        '.vscode',
        'node_modules',
        'tests',
        '.env',
        '.env.backup',
    ];

    /**
     * Directories whose contents are runtime state, not code.
     *
     * @var list<string>
     */
    private const EXCLUDED_CONTENTS = [
        'storage/logs/',
        'storage/framework/cache/',
        'storage/framework/sessions/',
        'storage/framework/views/',
    ];

    public function __construct(
        private readonly string $root,
        private readonly string $composer = 'composer',
    ) {}

    public function root(): string
    {
        return $this->root;
    }

    public function releasesPath(): string
    {
        return $this->root.DIRECTORY_SEPARATOR.'releases';
    }

    public function sharedPath(): string
    {
        return $this->root.DIRECTORY_SEPARATOR.'shared';
    }

    /**
     * Copy the source tree into a new release and return its path.
     */
    public function createRelease(string $source, ?string $id = null): string
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);

        if (! is_dir($source)) {
            throw new RuntimeException("The deployment source {$source} does not exist.");
        }

        $id ??= date('Ymd-His');
        $path = $this->uniqueReleasePath($id);

        mkdir($path, 0755, true);

        $this->copyDirectory($source, $path);
        $this->ensureRuntimeDirectories($path);

        return $path;
    }

    /**
     * Install the production dependencies of a release.
     */
    public function installDependencies(string $releasePath): void
    {
        $this->run($this->composer, [
            'install',
            '--no-dev',
            '--optimize-autoloader',
            '--no-interaction',
            '--no-progress',
        ], $releasePath);
    }

    /**
     * Bring the schema in line with the release.
     */
    public function runMigrations(string $releasePath): void
    {
        $this->run(PHP_BINARY, ['artisan', 'migrate', '--force'], $releasePath);
    }

    /**
     * Warm the caches so the first request is not the one that pays for it.
     */
    public function optimize(string $releasePath): void
    {
        $this->run(PHP_BINARY, ['artisan', 'config:cache'], $releasePath);
        $this->run(PHP_BINARY, ['artisan', 'route:cache'], $releasePath);
        $this->run(PHP_BINARY, ['artisan', 'view:cache'], $releasePath);
    }

    /**
     * Ask the queue workers to pick up the new code.
     */
    public function restartQueue(string $releasePath): void
    {
        try {
            $this->run(PHP_BINARY, ['artisan', 'queue:restart'], $releasePath);
        } catch (RuntimeException) {
            // A queue that is not running must not fail a deployment: the
            // workers pick the new code up on their next restart.
        }
    }

    /**
     * Put the shared secrets into the release.
     *
     * The secrets live outside the repository and outside every release
     * but the one that serves: they are kept in <root>/shared and copied
     * into the release, never committed.
     *
     * The copy also carries the version of the release, so the health
     * endpoint can report which build is live.
     */
    public function linkEnvironment(string $releasePath): void
    {
        $shared = $this->sharedPath().DIRECTORY_SEPARATOR.'.env';

        if (! is_file($shared)) {
            throw new RuntimeException(
                "The shared environment file {$shared} does not exist. The secrets must be provisioned outside the repository."
            );
        }

        $contents = (string) file_get_contents($shared);
        $version = basename($releasePath);

        $contents = preg_match('/^APP_VERSION=.*$/m', $contents) === 1
            ? (string) preg_replace('/^APP_VERSION=.*$/m', 'APP_VERSION='.$version, $contents)
            : rtrim($contents, "\n")."\nAPP_VERSION=".$version."\n";

        $target = $releasePath.DIRECTORY_SEPARATOR.'.env';

        file_put_contents($target, $contents);
        chmod($target, 0600);
    }

    /**
     * Remove the releases that are older than the given number, keeping
     * the current one and the previous one (the rollback target).
     */
    public function prune(string $currentPath, int $keep = 5): int
    {
        $releases = glob($this->releasesPath().DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];
        rsort($releases);

        $removed = 0;

        foreach (array_slice($releases, $keep) as $release) {
            if ($release === $currentPath) {
                continue;
            }

            $this->deleteDirectory($release);
            $removed++;
        }

        return $removed;
    }

    private function uniqueReleasePath(string $id): string
    {
        $path = $this->releasesPath().DIRECTORY_SEPARATOR.$id;
        $suffix = 1;

        while (is_dir($path)) {
            $path = $this->releasesPath().DIRECTORY_SEPARATOR.$id.'-'.$suffix;
            $suffix++;
        }

        if (! is_dir($this->releasesPath())) {
            mkdir($this->releasesPath(), 0755, true);
        }

        return $path;
    }

    private function ensureRuntimeDirectories(string $releasePath): void
    {
        foreach ([
            'storage/logs',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
        ] as $directory) {
            $path = $releasePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);

            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    private function copyDirectory(string $source, string $destination, string $relative = ''): void
    {
        $entries = scandir($source);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $relativePath = $relative === '' ? $entry : $relative.'/'.$entry;

            if (in_array($relativePath, self::EXCLUDED_PATHS, true)) {
                continue;
            }

            if ($this->isExcludedContent($relativePath)) {
                continue;
            }

            $from = $source.DIRECTORY_SEPARATOR.$entry;
            $to = $destination.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($from)) {
                if (! is_dir($to)) {
                    mkdir($to, 0755, true);
                }

                $this->copyDirectory($from, $to, $relativePath);

                continue;
            }

            copy($from, $to);
        }
    }

    private function isExcludedContent(string $relativePath): bool
    {
        foreach (self::EXCLUDED_CONTENTS as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function deleteDirectory(string $path): void
    {
        $entries = scandir($path) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($child) && ! is_link($child)) {
                $this->deleteDirectory($child);

                continue;
            }

            @unlink($child);
        }

        @rmdir($path);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function run(string $command, array $arguments, string $workingDirectory): void
    {
        $process = new Process(
            array_merge([$command], $arguments),
            $workingDirectory,
            null,
            null,
            900,
        );

        $process->run();

        if ($process->isSuccessful()) {
            return;
        }

        $output = trim($process->getErrorOutput() ?: $process->getOutput());

        throw new RuntimeException(sprintf(
            'The command "%s %s" failed with exit code %s: %s',
            $command,
            implode(' ', $arguments),
            (string) $process->getExitCode(),
            $output,
        ));
    }
}
