<?php

declare(strict_types=1);

namespace App\Modules\Folio\Infrastructure;

use App\Modules\Folio\Contracts\FolioRepository;
use App\Modules\Folio\Contracts\FolioService;
use App\Modules\Folio\Infrastructure\Repositories\EloquentFolioRepository;
use App\Modules\Folio\Infrastructure\Services\EloquentFolioService;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Folio module.
 */
final class FolioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FolioRepository::class, EloquentFolioRepository::class);
        $this->app->singleton(FolioService::class, EloquentFolioService::class);
    }
}
