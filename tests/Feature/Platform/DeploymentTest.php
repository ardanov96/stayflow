<?php

declare(strict_types=1);

use App\Modules\Platform\Infrastructure\Deployment\ReleaseManager;
use App\Modules\Platform\Infrastructure\Deployment\ReleasePointer;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * The deployment pipeline of task P0-06.
 *
 * The acceptance of the task is proven here: a release that does not
 * survive the health probe is rolled back automatically, and the server
 * keeps serving the release that worked.
 */
beforeEach(function (): void {
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'stayflow-deploy-'.bin2hex(random_bytes(6));

    mkdir($this->root.DIRECTORY_SEPARATOR.'shared', 0755, true);

    file_put_contents(
        $this->root.DIRECTORY_SEPARATOR.'shared'.DIRECTORY_SEPARATOR.'.env',
        "APP_KEY=base64:drill\nAPP_ENV=staging\n",
    );

    // The drill must not wait half a minute for a failing probe.
    config(['deploy.health_timeout' => 2, 'deploy.health_interval' => 200]);

    $this->server = null;
});

afterEach(function (): void {
    if ($this->server instanceof Process) {
        $this->server->stop();
    }

    deleteDirectory($this->root);
});

it('prepares a release and points the server at it', function (): void {
    $source = makeSource(['health.json' => '{"status":"ok"}']);

    $manager = new ReleaseManager($this->root);
    $pointer = new ReleasePointer($this->root);

    $release = $manager->createRelease($source, 'release-1');

    expect($release)->toBe($this->root.DIRECTORY_SEPARATOR.'releases'.DIRECTORY_SEPARATOR.'release-1')
        ->and(file_get_contents($release.DIRECTORY_SEPARATOR.'health.json'))->toBe('{"status":"ok"}');

    $pointer->swap($release);

    expect($pointer->currentPath())->toBe($release);
});

it('never copies the secrets, the tests or the dependencies into a release', function (): void {
    $source = makeSource([
        'health.json' => '{"status":"ok"}',
        'app/Modules/Platform/Http/Controllers/HealthCheckController.php' => '<?php // application code',
        '.env' => 'APP_KEY=leaked',
        'tests/Feature/Platform/HealthCheckTest.php' => '<?php // test code',
        'node_modules/vue/index.js' => '// dependency',
        '.git/config' => '[core]',
    ]);

    $release = (new ReleaseManager($this->root))->createRelease($source, 'release-1');

    expect(file_exists($release.DIRECTORY_SEPARATOR.'app'))->toBeTrue()
        ->and(file_exists($release.DIRECTORY_SEPARATOR.'.env'))->toBeFalse()
        ->and(file_exists($release.DIRECTORY_SEPARATOR.'tests'))->toBeFalse()
        ->and(file_exists($release.DIRECTORY_SEPARATOR.'node_modules'))->toBeFalse()
        ->and(file_exists($release.DIRECTORY_SEPARATOR.'.git'))->toBeFalse();
});

it('keeps the secrets outside every release and links them in', function (): void {
    $manager = new ReleaseManager($this->root);

    $release = $manager->createRelease(makeSource([]), 'release-1');

    expect(file_exists($release.DIRECTORY_SEPARATOR.'.env'))->toBeFalse();

    $manager->linkEnvironment($release);

    expect(file_get_contents($release.DIRECTORY_SEPARATOR.'.env'))->toContain('APP_KEY');
});

it('rolls the release back automatically when the health probe fails', function (): void {
    $url = startHealthServer($this->root, $this);

    $good = makeSource(['health.json' => '{"status":"ok"}']);

    $this->artisan('deploy:publish', [
        '--root' => $this->root,
        '--source' => $good,
        '--release' => 'good',
        '--health-url' => $url,
        '--skip-dependencies' => true,
        '--skip-migrations' => true,
        '--skip-optimize' => true,
    ])->assertExitCode(0)->run();

    $pointer = new ReleasePointer($this->root);

    expect($pointer->currentPath())->toEndWith('good')
        ->and(healthStatus($url))->toBe('ok');

    // A release that does not survive the probe.
    $broken = makeSource(['health.json' => '{"status":"degraded"}']);

    $this->artisan('deploy:publish', [
        '--root' => $this->root,
        '--source' => $broken,
        '--release' => 'broken',
        '--health-url' => $url,
        '--skip-dependencies' => true,
        '--skip-migrations' => true,
        '--skip-optimize' => true,
    ])->assertExitCode(1)->run();

    // The server is serving the release that worked, again.
    expect($pointer->currentPath())->toEndWith('good')
        ->and(healthStatus($url))->toBe('ok');

    // The failed release is kept for forensics, not silently dropped.
    expect(is_dir($this->root.DIRECTORY_SEPARATOR.'releases'.DIRECTORY_SEPARATOR.'broken'))->toBeTrue();
});

it('reports the state of the server', function (): void {
    $url = startHealthServer($this->root, $this);

    $this->artisan('deploy:publish', [
        '--root' => $this->root,
        '--source' => makeSource(['health.json' => '{"status":"ok"}']),
        '--release' => 'first',
        '--health-url' => $url,
        '--skip-dependencies' => true,
        '--skip-migrations' => true,
        '--skip-optimize' => true,
    ])->assertExitCode(0)->run();

    $this->artisan('deploy:status', [
        '--root' => $this->root,
        '--url' => $url,
    ])->assertExitCode(0)->run();
});

it('rolls the server back to the previous release on demand', function (): void {
    $url = startHealthServer($this->root, $this);

    foreach (['first', 'second'] as $release) {
        $this->artisan('deploy:publish', [
            '--root' => $this->root,
            '--source' => makeSource(['health.json' => '{"status":"ok"}']),
            '--release' => $release,
            '--health-url' => $url,
            '--skip-dependencies' => true,
            '--skip-migrations' => true,
            '--skip-optimize' => true,
        ])->assertExitCode(0)->run();
    }

    $pointer = new ReleasePointer($this->root);

    expect($pointer->currentPath())->toEndWith('second');

    $this->artisan('deploy:rollback', ['--root' => $this->root])->assertExitCode(0)->run();

    expect($pointer->currentPath())->toEndWith('first');
});

/**
 * Build a source tree with the given files.
 *
 * @param  array<string, string>  $files
 */
function makeSource(array $files): string
{
    $source = sys_get_temp_dir().DIRECTORY_SEPARATOR.'stayflow-source-'.bin2hex(random_bytes(6));

    mkdir($source, 0755, true);

    foreach ($files as $path => $contents) {
        $target = $source.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        $directory = dirname($target);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($target, $contents);
    }

    return $source;
}

/**
 * Start the health server of the drill and wait for it to answer.
 */
function startHealthServer(string $root, object $test): string
{
    $port = freePort();
    $address = '127.0.0.1:'.$port;

    $process = new Process(
        [PHP_BINARY, '-S', $address, base_path('tests/Fixtures/deployment/health-router.php')],
        base_path(),
        ['STAGING_ROOT' => $root],
        null,
        30,
    );

    $process->start();

    $test->server = $process;

    $url = 'http://'.$address.'/healthz';
    $deadline = microtime(true) + 15;

    while (microtime(true) < $deadline) {
        if (healthStatus($url) !== null) {
            return $url;
        }

        usleep(100_000);
    }

    throw new RuntimeException('The drill server did not start.');
}

function healthStatus(string $url): ?string
{
    try {
        $response = Http::timeout(2)->acceptJson()->get($url);
    } catch (Throwable) {
        return null;
    }

    $payload = $response->json();

    return is_array($payload) ? ($payload['status'] ?? null) : null;
}

function freePort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

    if ($socket === false) {
        throw new RuntimeException("Could not reserve a port: {$errstr}");
    }

    $name = (string) stream_socket_get_name($socket, false);
    fclose($socket);

    return (int) substr($name, strrpos($name, ':') + 1);
}

function deleteDirectory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path.DIRECTORY_SEPARATOR.$entry;

        if (is_dir($child) && ! is_link($child)) {
            deleteDirectory($child);

            continue;
        }

        @unlink($child);
    }

    @rmdir($path);
}
