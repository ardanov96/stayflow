<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\HealthCheckController;
use App\Modules\Platform\Infrastructure\Notifications\PostmarkWebhookController;
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

/*
|--------------------------------------------------------------------------
| The webhook of the email provider
|--------------------------------------------------------------------------
|
| The provider posts the bounces and the spam complaints of the messages
| it could not deliver. The endpoint carries a shared secret in the path
| (compared in constant time), because the endpoint is public.
|
| It carries no middleware on purpose: the provider knows nothing about
| a session, and a CSRF token would reject every callback.
|
*/

Route::post('webhooks/postmark/{token}', PostmarkWebhookController::class)
    ->name('platform.webhooks.postmark');
