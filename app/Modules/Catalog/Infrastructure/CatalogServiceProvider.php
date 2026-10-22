<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure;

use App\Modules\Catalog\Application\Ports\ImageOptimizer;
use App\Modules\Catalog\Contracts\CatalogService;
use App\Modules\Catalog\Infrastructure\Media\WebpMediaOptimizer;
use App\Modules\Catalog\Infrastructure\Services\EloquentCatalogService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Catalog module bindings and routes.
 */
class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ImageOptimizer::class, WebpMediaOptimizer::class);
        $this->app->singleton(CatalogService::class, EloquentCatalogService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');
    }
}
