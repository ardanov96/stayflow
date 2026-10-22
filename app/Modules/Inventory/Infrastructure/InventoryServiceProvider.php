<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure;

use App\Modules\Inventory\Application\Ports\RatePlanRepository;
use App\Modules\Inventory\Application\Ports\RatePriceRepository;
use App\Modules\Inventory\Contracts\PricingService;
use App\Modules\Inventory\Infrastructure\Repositories\EloquentRatePlanRepository;
use App\Modules\Inventory\Infrastructure\Repositories\EloquentRatePriceRepository;
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
    }

    public function boot(): void
    {
        $routesPath = __DIR__.'/../Http/routes.php';
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }
    }
}
