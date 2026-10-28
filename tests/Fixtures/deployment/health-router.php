<?php

declare(strict_types=1);

/**
 * Router of the built-in PHP server used by the deployment drill.
 *
 * It answers `/healthz` the way the application does, but the answer
 * depends on the release the server is currently pointing at: the drill
 * publishes a release whose `health.json` says `degraded` and proves that
 * the pipeline notices and rolls back.
 *
 * The pointer is resolved here on purpose: the router must not depend on
 * the application, so it reads the pointer the same way the pipeline
 * writes it (a symlink on Linux, a pointer file elsewhere).
 */
$root = (string) (getenv('STAGING_ROOT') ?: '');
$pointer = $root.DIRECTORY_SEPARATOR.'current';

$current = null;

if (is_link($pointer)) {
    $target = readlink($pointer);
    $current = $target === false ? null : $target;
} elseif (is_file($pointer)) {
    $current = trim((string) file_get_contents($pointer));
}

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if ($path !== '/healthz') {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'not found']);

    return true;
}

$healthy = false;

if ($current !== null) {
    $marker = $current.DIRECTORY_SEPARATOR.'health.json';

    if (is_file($marker)) {
        $payload = json_decode((string) file_get_contents($marker), true);
        $healthy = is_array($payload) && ($payload['status'] ?? null) === 'ok';
    }
}

http_response_code($healthy ? 200 : 503);
header('Content-Type: application/json');

echo json_encode([
    'status' => $healthy ? 'ok' : 'degraded',
    'version' => $current === null ? 'none' : basename($current),
    'environment' => 'drill',
    'checks' => [],
]);

return true;
