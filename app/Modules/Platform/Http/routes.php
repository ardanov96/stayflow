<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform routes
|--------------------------------------------------------------------------
|
| The health endpoint is registered without any middleware: the deployment
| pipeline probes it on a release that may be exactly what is broken, so
| it must not depend on the session, the tenant context, or the
| authentication layer.
|
| It is a machine endpoint. It reports which component failed, never why:
| the reason goes to the log.
|
*/

Route::get('healthz', HealthCheckController::class)->name('platform.health');
