<?php

declare(strict_types=1);

use App\Modules\Inventory\Http\Controllers\InventoryBlockController;
use App\Modules\Inventory\Http\Controllers\InventoryQuoteController;
use App\Modules\Inventory\Http\Controllers\PriceQuoteController;
use App\Modules\Inventory\Http\Controllers\RatePlanController;
use App\Modules\Inventory\Http\Controllers\RatePriceController;
use App\Modules\Inventory\Http\Controllers\RestrictionController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/inventory')->middleware(['web', 'auth'])->group(function (): void {
    // Rate Plans
    Route::get('rate-plans', [RatePlanController::class, 'index'])
        ->middleware('permission:rate_plans.read')
        ->name('inventory.rate-plans.index');

    Route::get('rate-plans/{id}', [RatePlanController::class, 'show'])
        ->middleware('permission:rate_plans.read')
        ->name('inventory.rate-plans.show');

    Route::post('rate-plans', [RatePlanController::class, 'store'])
        ->middleware('permission:rate_plans.manage')
        ->name('inventory.rate-plans.store');

    Route::match(['put', 'patch'], 'rate-plans/{id}', [RatePlanController::class, 'update'])
        ->middleware('permission:rate_plans.manage')
        ->name('inventory.rate-plans.update');

    // Rate Prices
    Route::post('prices', [RatePriceController::class, 'setPrices'])
        ->middleware('permission:rate_plans.manage')
        ->name('inventory.prices.set');

    // Price Quote (Itemized stay price calculation)
    Route::post('quote', [PriceQuoteController::class, 'quote'])
        ->middleware('permission:rate_plans.read')
        ->name('inventory.quote');

    // Availability & Inventory Days Engine (P1-08)
    Route::post('availability/quote', [InventoryQuoteController::class, 'quote'])
        ->middleware('permission:rate_plans.read')
        ->name('inventory.availability.quote');

    Route::get('availability', [InventoryQuoteController::class, 'getAvailability'])
        ->middleware('permission:rate_plans.read')
        ->name('inventory.availability.index');

    Route::post('materialize', [InventoryQuoteController::class, 'materialize'])
        ->middleware('permission:inventory.manage')
        ->name('inventory.materialize');

    // Inventory Blocks
    Route::post('blocks', [InventoryBlockController::class, 'store'])
        ->middleware('permission:inventory.manage')
        ->name('inventory.blocks.store');

    Route::delete('blocks/{id}', [InventoryBlockController::class, 'destroy'])
        ->middleware('permission:inventory.manage')
        ->name('inventory.blocks.destroy');

    // Restrictions
    Route::post('restrictions', [RestrictionController::class, 'setRestrictions'])
        ->middleware('permission:restrictions.manage')
        ->name('inventory.restrictions.set');
});
