<?php

declare(strict_types=1);

use App\Modules\Inventory\Http\Controllers\PriceQuoteController;
use App\Modules\Inventory\Http\Controllers\RatePlanController;
use App\Modules\Inventory\Http\Controllers\RatePriceController;
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
});
