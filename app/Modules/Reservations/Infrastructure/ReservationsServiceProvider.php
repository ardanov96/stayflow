<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Infrastructure;

use App\Modules\Reservations\Contracts\ReservationRepository;
use App\Modules\Reservations\Contracts\ReservationService;
use App\Modules\Reservations\Infrastructure\Repositories\EloquentReservationRepository;
use App\Modules\Reservations\Infrastructure\Services\EloquentReservationService;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Reservations module.
 *
 * Registered in bootstrap/providers.php, so it boots on every request, every
 * console command and every queued job.
 */
final class ReservationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReservationRepository::class, EloquentReservationRepository::class);
        $this->app->singleton(ReservationService::class, EloquentReservationService::class);
    }

    public function boot(): void
    {
        $routesPath = __DIR__.'/../Http/routes.php';

        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }
    }
}
