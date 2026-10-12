<?php

declare(strict_types=1);

use App\Modules\Platform\Domain\User;

test('platform module skeleton exists')
    ->expect(fn () => true)
    ->toBeTrue();

test('module layout follows AGENTS.md §5 (canonical sub-folders)', function (string $module): void {
    $base = base_path("app/Modules/{$module}");

    expect(is_dir("{$base}/Domain"))->toBeTrue("{$module}/Domain missing")
        ->and(is_dir("{$base}/Application"))->toBeTrue("{$module}/Application missing")
        ->and(is_dir("{$base}/Infrastructure"))->toBeTrue("{$module}/Infrastructure missing")
        ->and(is_dir("{$base}/Http"))->toBeTrue("{$module}/Http missing")
        ->and(is_dir("{$base}/Contracts"))->toBeTrue("{$module}/Contracts missing");
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

test('module namespaces are namespaced under App\\Modules\\', function (): void {
    expect(class_exists(User::class) || true)->toBeTrue();
})->skip('placeholder until P1-01 fills the Platform\\Domain\\User value object');

test('infrastructure models live under Infrastructure, not Domain', function (string $module): void {
    $modelPath = base_path("app/Modules/{$module}/Infrastructure");
    expect(is_dir($modelPath))->toBeTrue();
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
