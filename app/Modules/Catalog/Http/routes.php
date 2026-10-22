<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\CatalogMediaController;
use App\Modules\Catalog\Http\Controllers\PropertyController;
use App\Modules\Catalog\Http\Controllers\PropertyPolicyController;
use App\Modules\Catalog\Http\Controllers\RoomTypeController;
use App\Modules\Catalog\Http\Controllers\RoomUnitController;
use App\Modules\Catalog\Http\Controllers\TaxRuleController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/catalog')->middleware(['web', 'auth'])->group(function (): void {
    // Properties
    Route::get('properties', [PropertyController::class, 'index'])
        ->middleware('permission:properties.read')
        ->name('catalog.properties.index');

    Route::get('properties/{id}', [PropertyController::class, 'show'])
        ->middleware('permission:properties.read')
        ->name('catalog.properties.show');

    Route::post('properties', [PropertyController::class, 'store'])
        ->middleware('permission:properties.update')
        ->name('catalog.properties.store');

    Route::match(['put', 'patch'], 'properties/{id}', [PropertyController::class, 'update'])
        ->middleware('permission:properties.update')
        ->name('catalog.properties.update');

    // Room Types
    Route::get('properties/{propertyId}/room-types', [RoomTypeController::class, 'index'])
        ->middleware('permission:properties.read')
        ->name('catalog.room-types.index');

    Route::get('room-types/{id}', [RoomTypeController::class, 'show'])
        ->middleware('permission:properties.read')
        ->name('catalog.room-types.show');

    Route::post('properties/{propertyId}/room-types', [RoomTypeController::class, 'store'])
        ->middleware('permission:room_types.manage')
        ->name('catalog.room-types.store');

    Route::match(['put', 'patch'], 'room-types/{id}', [RoomTypeController::class, 'update'])
        ->middleware('permission:room_types.manage')
        ->name('catalog.room-types.update');

    // Room Units
    Route::get('properties/{propertyId}/room-units', [RoomUnitController::class, 'index'])
        ->middleware('permission:properties.read')
        ->name('catalog.room-units.index');

    Route::post('room-types/{roomTypeId}/room-units', [RoomUnitController::class, 'store'])
        ->middleware('permission:room_types.manage')
        ->name('catalog.room-units.store');

    Route::patch('room-units/{id}/status', [RoomUnitController::class, 'updateStatus'])
        ->middleware('permission:room_types.manage')
        ->name('catalog.room-units.update-status');

    // Tax Rules
    Route::get('properties/{propertyId}/tax-rules', [TaxRuleController::class, 'index'])
        ->middleware('permission:properties.read')
        ->name('catalog.tax-rules.index');

    Route::post('properties/{propertyId}/tax-rules', [TaxRuleController::class, 'store'])
        ->middleware('permission:properties.update')
        ->name('catalog.tax-rules.store');

    // Policies
    Route::get('properties/{propertyId}/policies', [PropertyPolicyController::class, 'index'])
        ->middleware('permission:properties.read')
        ->name('catalog.policies.index');

    Route::post('properties/{propertyId}/policies', [PropertyPolicyController::class, 'store'])
        ->middleware('permission:policies.manage')
        ->name('catalog.policies.store');

    // Media
    Route::post('media/upload', [CatalogMediaController::class, 'upload'])
        ->middleware('permission:properties.update')
        ->name('catalog.media.upload');
});
