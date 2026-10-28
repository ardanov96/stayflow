<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The plans
    |--------------------------------------------------------------------------
    |
    | A plan says which **modules** of the product an organization has and
    | which **features** are switched on. The modules are the ones of the
    | URL of the API (`api/<module>/...`), so a module that is not in the
    | plan is not reachable, by route and by API.
    |
    | A plan is a set of modules and a set of features; the plans are
    | cumulative, so the biggest one has everything.
    |
    */

    'plans' => [

        'pms' => [
            'label' => 'PMS',
            'modules' => ['catalog', 'inventory', 'reservations', 'folio', 'housekeeping'],
            'features' => ['reports', 'night_audit', 'import'],
        ],

        'booking_engine' => [
            'label' => 'Booking Engine',
            'modules' => ['booking_engine'],
            'features' => ['promo_codes', 'add_ons', 'widget', 'analytics'],
        ],

        'channel_manager' => [
            'label' => 'Channel Manager',
            'modules' => ['distribution'],
            'features' => ['ari_push', 'reconciliation', 'overbooking_quarantine'],
        ],

        'crm' => [
            'label' => 'CRM',
            'modules' => ['guests'],
            'features' => ['segments', 'automations', 'campaigns'],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | The trial
    |--------------------------------------------------------------------------
    |
    | How long an organization may use the product before it has to pay.
    |
    */

    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    // The plan of the trial of an organization that has no subscription
    // yet. Every organization starts somewhere.
    'default_plan' => env('BILLING_DEFAULT_PLAN', 'pms'),

    /*
    |--------------------------------------------------------------------------
    | The gateways
    |--------------------------------------------------------------------------
    |
    | The organization chooses its gateway; the default is the one of
    | ADR-0013. Xendit is the primary (it supports the payment methods of
    | OQ-05); Midtrans is the secondary, for the cards.
    |
    */

    'default_gateway' => env('BILLING_GATEWAY', 'xendit'),

    // The shared secret of the webhook of the gateways.
    'webhook_token' => env('BILLING_WEBHOOK_TOKEN'),

    'gateways' => ['xendit', 'midtrans'],

];
