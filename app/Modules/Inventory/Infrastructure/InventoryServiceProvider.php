<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure;

use App\Modules\Inventory\Application\Ports\HoldRepository;
use App\Modules\Inventory\Application\Ports\InventoryBlockRepository;
use App\Modules\Inventory\Application\Ports\InventoryDayRepository;
use App\Modules\Inventory\Application\Ports\RatePlanRepository;
use App\Modules\Inventory\Application\Ports\RatePriceRepository;
use App\Modules\Inventory\Application\Ports\RestrictionDayRepository;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Contracts\PricingService;
use App\Modules\Inventory\Infrastructure\Repositories\EloquentHoldRepository;
use App\Modules\Inventory\Infrastructure\Repositories\EloquentInventoryBlockRepository;
use App\Modules\Inventory\Infrastructure\Repositories\EloquentInventoryDayRepository;
use App\Modules\Inventory\Infrastructure\Repositories\EloquentRatePlanRepository;
use App\Modules\Inventory\Infrastructure\Repositories\EloquentRatePriceRepository;
use App\Modules\Inventory\Infrastructure\Repositories\EloquentRestrictionDayRepository;
use App\Modules\Inventory\Infrastructure\Services\EloquentInventoryService;
use App\Modules\Inventory\Infrastructure\Services\EloquentPricingService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Inventory module bindings and routes.
 */
class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RatePlanRepository::class, EloquentRatePlanRepository::class);
        $this->app->singleton(RatePriceRepository::class, EloquentRatePriceRepository::class);
        $this->app->singleton(PricingService::class, EloquentPricingService::class);
        $this->app->singleton(InventoryDayRepository::class, EloquentInventoryDayRepository::class);
        $this->app->singleton(RestrictionDayRepository::class, EloquentRestrictionDayRepository::class);
        $this->app->singleton(InventoryBlockRepository::class, EloquentInventoryBlockRepository::class);
        $this->app->singleton(HoldRepository::class, EloquentHoldRepository::class);
        $this->app->singleton(InventoryService::class, EloquentInventoryService::class);
    }

    public function boot(): void
    {
        $routesPath = __DIR__.'/../Http/routes.php';
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }
    }
}
