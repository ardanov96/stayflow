<?php

declare(strict_types=1);
use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| Eloquent model placement
|--------------------------------------------------------------------------
|
| ARCHITECTURE.md §3 rule: "no cross-module table writes; Eloquent models are
| not used across modules". Eloquent models live under
| `App\Modules\{Name}\Infrastructure\Models\*` and may only be referenced
| inside the same module — the owning module's own Contracts/ sub-namespace
| is the cross-module entry point.
|
| These tests are vacuously green today; they will fail CI the moment a
| developer writes `use App\Modules\Inventory\Infrastructure\Models\InventoryDay;`
| from inside, say, `App\Modules\Reservations\Application\Actions\*`.
|
*/

// The original "toBeUsedInNothing" assertion was too strict: it forbade
// any reference to a model anywhere, including the model's own factory
// (Database\Factories\{Name}Factory), which legitimately references the
// model class. The cross-module half of the rule is enforced by
// tests/Arch/ModuleBoundariesTest and by the "Domain and Application
// sub-trees never import Eloquent" assertion below; together they
// guarantee that models are only consumed inside their own module and
// inside database/factories/, database/seeders/, and tests/.

it('Infrastructure/Models directory exists for every module', function (string $module): void {
    $projectRoot = dirname(__DIR__, 2);
    $path = "{$projectRoot}/app/Modules/{$module}/Infrastructure/Models";
    expect(is_dir($path))->toBeTrue("{$module}/Infrastructure/Models should exist");
})->with([
    'Platform', 'Catalog', 'Inventory', 'Reservations', 'Folio',
    'Housekeeping', 'Payments', 'Distribution', 'BookingEngine',
    'Guests', 'Plugins', 'Reporting', 'Notifications',
]);

arch('Domain and Application sub-trees never import Eloquent')
    ->expect([
        'App\\Modules\\Catalog\\Domain',
        'App\\Modules\\Catalog\\Application',
        'App\\Modules\\Inventory\\Domain',
        'App\\Modules\\Inventory\\Application',
        'App\\Modules\\Reservations\\Domain',
        'App\\Modules\\Reservations\\Application',
        'App\\Modules\\Folio\\Domain',
        'App\\Modules\\Folio\\Application',
        'App\\Modules\\Housekeeping\\Domain',
        'App\\Modules\\Housekeeping\\Application',
        'App\\Modules\\Payments\\Domain',
        'App\\Modules\\Payments\\Application',
        'App\\Modules\\Distribution\\Domain',
        'App\\Modules\\Distribution\\Application',
        'App\\Modules\\BookingEngine\\Domain',
        'App\\Modules\\BookingEngine\\Application',
        'App\\Modules\\Guests\\Domain',
        'App\\Modules\\Guests\\Application',
        'App\\Modules\\Plugins\\Domain',
        'App\\Modules\\Plugins\\Application',
        'App\\Modules\\Reporting\\Domain',
        'App\\Modules\\Reporting\\Application',
        'App\\Modules\\Notifications\\Domain',
        'App\\Modules\\Notifications\\Application',
        'App\\Modules\\Platform\\Domain',
        'App\\Modules\\Platform\\Application',
    ])
    ->not->toUse(Model::class);
