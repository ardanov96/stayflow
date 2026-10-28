<?php

declare(strict_types=1);

/**
 * No ad-hoc date formatting (task P1-12).
 *
 * A date that is formatted by hand is a date whose timezone, locale and
 * format can differ from one call to the next — and that is exactly how
 * a hotel ends up one day ahead of its guests. The helpers in
 * `App\Support\Date` are the only place where a date becomes a string.
 *
 * The rule is a test and not a convention on purpose: it fails the build
 * instead of being remembered.
 */
it('never formats a date by hand outside the date helpers', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $helpers = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Support'.DIRECTORY_SEPARATOR.'Date';

    $offenders = [];

    foreach (phpFilesOf($projectRoot.DIRECTORY_SEPARATOR.'app') as $file) {
        if (str_starts_with($file, $helpers)) {
            continue;
        }

        $contents = (string) file_get_contents($file);

        if (preg_match('/->format\(|date\(\s*[\x27"]|strftime\(/', $contents) === 1) {
            $offenders[] = str_replace($projectRoot.DIRECTORY_SEPARATOR, '', $file);
        }
    }

    expect($offenders)->toBe(
        [],
        'A date must not be formatted by hand. Use App\\Support\\Date (StayDate, PropertyClock, '
        .'DateFormatter, UtcTimestamp) instead. Offenders: '.implode(', ', $offenders)
    );
});

it('keeps the date helpers free of the framework', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $helpers = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Support'.DIRECTORY_SEPARATOR.'Date';

    foreach (phpFilesOf($helpers) as $file) {
        $contents = (string) file_get_contents($file);

        expect($contents)->not->toContain('Illuminate\Database');
    }
});

/**
 * Every PHP file of a directory, recursively.
 *
 * @return list<string>
 */
function phpFilesOf(string $directory): array
{
    if (! is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}
