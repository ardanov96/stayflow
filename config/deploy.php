<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Deployment
    |--------------------------------------------------------------------------
    |
    | The layout of a deployed server and the endpoint the deployment
    | pipeline probes after every release.
    |
    | The server layout is:
    |
    |   <root>/current        -> the release that is serving (symlink)
    |   <root>/releases/<id>  -> one directory per release
    |   <root>/shared/.env    -> the secrets, outside every release
    |
    | The `current` pointer is a symlink on Linux (the deployment target)
    | and a plain pointer file where symlinks are not available, so the
    | same code runs on a developer machine.
    |
    */

    'root' => env('DEPLOY_ROOT', '/srv/stayflow'),

    'health_url' => env('DEPLOY_HEALTH_URL'),

    'health_timeout' => (int) env('DEPLOY_HEALTH_TIMEOUT', 30),

    'health_interval' => (int) env('DEPLOY_HEALTH_INTERVAL', 500),

    'shared_env' => env('DEPLOY_SHARED_ENV'),

    'composer' => env('DEPLOY_COMPOSER', 'composer'),

];
