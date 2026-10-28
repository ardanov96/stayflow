<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The settings
    |--------------------------------------------------------------------------
    |
    | Every setting of the application, with its type, its default and the
    | scope it belongs to: `organization` or `property`.
    |
    | A setting that is not in this list does not exist: the application
    | never invents a setting, and a value that is not defined is refused
    | instead of being stored.
    |
    | The types are: string, int, bool, decimal, and a list of choices.
    |
    */

    'settings' => [

        // The organization.
        'locale' => ['type' => 'string', 'default' => 'id', 'scope' => 'organization', 'choices' => ['id', 'en', 'zh']],
        'auto_renew' => ['type' => 'bool', 'default' => true, 'scope' => 'organization'],
        'invoice_prefix' => ['type' => 'string', 'default' => 'INV', 'scope' => 'organization'],
        'billing_email' => ['type' => 'string', 'default' => '', 'scope' => 'organization'],

        // The property.
        'timezone' => ['type' => 'string', 'default' => 'Asia/Makassar', 'scope' => 'property'],
        'currency' => ['type' => 'string', 'default' => 'IDR', 'scope' => 'property'],
        'check_in_time' => ['type' => 'string', 'default' => '14:00', 'scope' => 'property'],
        'check_out_time' => ['type' => 'string', 'default' => '11:00', 'scope' => 'property'],
        'service_charge_percent' => ['type' => 'int', 'default' => 0, 'scope' => 'property'],
        'breakfast_included' => ['type' => 'bool', 'default' => false, 'scope' => 'property'],
        'tax_included_in_price' => ['type' => 'bool', 'default' => false, 'scope' => 'property'],

    ],

    /*
    |--------------------------------------------------------------------------
    | The cache
    |--------------------------------------------------------------------------
    |
    | How long a setting is kept before it is read again. The value is
    | invalidated when it is written, so the time is only a safety net.
    |
    */

    'cache_seconds' => (int) env('SETTINGS_CACHE_SECONDS', 300),

];
