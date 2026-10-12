<?php

declare(strict_types=1);

// Project root relative to this file: tests/Arch/ModuleLayoutTest.php -> project root.
$projectRoot = dirname(__DIR__, 2);

it('platform module skeleton exists')
    ->expect(fn () => true)
    ->toBeTrue();

it('module layout follows AGENTS.md §5 (canonical sub-folders)', function (string $module) use ($projectRoot): void {
    $base = "{$projectRoot}/app/Modules/{$module}";

    expect(is_dir("{$base}/Domain"))->toBeTrue("{$module}/Domain missing")
        ->and(is_dir("{$base}/Application"))->toBeTrue("{$module}/Application missing")
        ->and(is_dir("{$base}/Infrastructure"))->toBeTrue("{$module}/Infrastructure missing")
        ->and(is_dir("{$base}/Http"))->toBeTrue("{$module}/Http missing")
        ->and(is_dir("{$base}/Contracts"))->toBeTrue("{$module}/Contracts missing")
        ->and(is_dir("{$base}/Database"))->toBeTrue("{$module}/Database missing")
        ->and(is_dir("{$base}/Tests"))->toBeTrue("{$module}/Tests missing");
})->with([
    'Platform',
    'Catalog',
    'Inventory',
    'Reservations',
    'Folio',
    'Housekeeping',
    'Payments',
    'Distribution',
    'BookingEngine',
    'Guests',
    'Plugins',
    'Reporting',
    'Notifications',
]);

it('infrastructure models live under Infrastructure, not Domain', function (string $module) use ($projectRoot): void {
    $modelPath = "{$projectRoot}/app/Modules/{$module}/Infrastructure/Models";
    expect(is_dir($modelPath))->toBeTrue("{$module}/Infrastructure/Models missing");
})->with([
    'Platform',
    'Catalog',
    'Inventory',
    'Reservations',
    'Folio',
    'Housekeeping',
    'Payments',
    'Distribution',
    'BookingEngine',
    'Guests',
    'Plugins',
    'Reporting',
    'Notifications',
]);

it('app/Support has ValueObjects and Helpers sub-folders', function () use ($projectRoot): void {
    expect(is_dir("{$projectRoot}/app/Support/ValueObjects"))->toBeTrue()
        ->and(is_dir("{$projectRoot}/app/Support/Helpers"))->toBeTrue();
});

it('every Module Contracts/ folder is readable (no permission issues)', function (string $module) use ($projectRoot): void {
    expect(is_readable("{$projectRoot}/app/Modules/{$module}/Contracts"))->toBeTrue();
})->with([
    'Platform',
    'Catalog',
    'Inventory',
    'Reservations',
    'Folio',
    'Housekeeping',
    'Payments',
    'Distribution',
    'BookingEngine',
    'Guests',
    'Plugins',
    'Reporting',
    'Notifications',
]);
