<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\CatalogServiceProvider;
use App\Modules\Inventory\Infrastructure\InventoryServiceProvider;
use App\Modules\Platform\Infrastructure\PlatformServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    PlatformServiceProvider::class,
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
];
