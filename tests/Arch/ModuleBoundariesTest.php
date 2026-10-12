<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Module boundary enforcement
|--------------------------------------------------------------------------
|
| Each module lives under App\Modules\{Name}. Cross-module dependencies
| are governed by the table in docs/ARCHITECTURE.md §3.
|
*/

foreach ([
    'Platform' => [
        'Catalog', 'Inventory', 'Reservations', 'Folio', 'Housekeeping',
        'Payments', 'Distribution', 'BookingEngine', 'Guests', 'Plugins',
        'Reporting', 'Notifications',
    ],
    'Catalog' => [
        'Inventory', 'Reservations', 'Folio', 'Housekeeping', 'Payments',
        'Distribution', 'BookingEngine', 'Guests', 'Plugins',
        'Reporting', 'Notifications',
    ],
    'Inventory' => [
        'Reservations', 'Folio', 'Housekeeping', 'Payments',
        'Distribution', 'BookingEngine', 'Guests', 'Plugins',
        'Reporting', 'Notifications',
    ],
    'Guests' => [
        'Catalog', 'Inventory', 'Reservations', 'Folio', 'Housekeeping',
        'Payments', 'Distribution', 'BookingEngine', 'Plugins',
        'Reporting', 'Notifications',
    ],
    'Reservations' => [
        'Folio', 'Housekeeping', 'Payments', 'Distribution', 'BookingEngine',
        'Plugins', 'Notifications',
    ],
    'Folio' => [
        'Catalog', 'Inventory', 'Housekeeping', 'Payments', 'Distribution',
        'BookingEngine', 'Guests', 'Plugins', 'Reporting', 'Notifications',
    ],
    'Payments' => [
        'Catalog', 'Inventory', 'Reservations', 'Housekeeping',
        'Distribution', 'BookingEngine', 'Guests', 'Plugins', 'Reporting',
        'Notifications',
    ],
    'Housekeeping' => [
        'Inventory', 'Folio', 'Payments', 'Distribution', 'BookingEngine',
        'Guests', 'Plugins', 'Reporting', 'Notifications',
    ],
    'Distribution' => [
        'Folio', 'Housekeeping', 'Payments', 'BookingEngine', 'Guests',
        'Plugins', 'Reporting', 'Notifications',
    ],
    'BookingEngine' => [
        'Folio', 'Housekeeping', 'Distribution', 'Reporting', 'Notifications',
    ],
    'Plugins' => [
        'Reservations', 'Folio', 'Housekeeping', 'Payments', 'Distribution',
        'Reporting', 'Notifications',
    ],
    'Notifications' => [
        'Catalog', 'Inventory', 'Reservations', 'Folio', 'Housekeeping',
        'Payments', 'Distribution', 'BookingEngine', 'Guests', 'Plugins',
        'Reporting',
    ],
] as $module => $forbidden) {
    $description = "{$module} must not import forbidden sibling modules";
    $deny = array_map(
        static fn (string $other): string => "App\\Modules\\{$other}",
        $forbidden,
    );

    arch($description)
        ->expect("App\\Modules\\{$module}")
        ->not->toUse($deny);
}

arch('Reporting must not depend on itself')
    ->expect('App\\Modules\\Reporting')
    ->not->toUse(['App\\Modules\\Reporting']);
