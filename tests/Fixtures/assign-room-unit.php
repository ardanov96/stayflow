<?php

declare(strict_types=1);

/**
 * Assigns a physical room to a reservation, in its own process.
 *
 * Used by the concurrency test of task P2-05: two of these try to
 * assign the same room at the same time, and the database decides which
 * one wins.
 *
 * Usage: php assign-room-unit.php <organizationId> <reservationId> <roomTypeId> <roomUnitId>
 */

use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Reservations\Contracts\ReservationService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// The suite runs on the MySQL 8 database of the concurrency tests.
config(['database.default' => 'concurrency']);

[$organizationId, $reservationId, $roomTypeId, $roomUnitId] = array_map('intval', array_slice($argv, 1));

try {
    app(OrganizationContext::class)->swap(
        TenantContext::forOrganization($organizationId),
        function () use ($reservationId, $roomTypeId, $roomUnitId): void {
            app(ReservationService::class)->assignRoomUnit(
                reservationId: $reservationId,
                roomTypeId: $roomTypeId,
                roomUnitId: $roomUnitId,
            );
        },
    );

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage().PHP_EOL);

    exit(1);
}
