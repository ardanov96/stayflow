<?php

declare(strict_types=1);

use App\Modules\Platform\Infrastructure\PlatformServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    PlatformServiceProvider::class,
];
