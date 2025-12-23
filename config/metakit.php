<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api_prefix' => 'api/metakit',
    'api_middleware' => ['api', 'auth:sanctum'],

    /*
    |--------------------------------------------------------------------------
    | Query Whitelist
    |--------------------------------------------------------------------------
    | Query parameters that will be included in the query_hash calculation.
    | Parameters not in this list will be ignored.
    */
    'query_whitelist' => [
        'city',
        'district',
        'uni',
        'gender',
        'price_min',
        'price_max',
        'type',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache_ttl_minutes' => 360,

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    */
    'default' => [
        'site_name' => env('APP_NAME', 'Laravel'),
        'title_suffix' => ' - ' . env('APP_NAME', 'Laravel'),
        'default_image' => env('METAKIT_DEFAULT_IMAGE', '/images/og-default.jpg'),
        'default_robots' => 'index, follow',
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Comments
    |--------------------------------------------------------------------------
    | When enabled, adds HTML comments with debug information.
    */
    'debug_comments' => env('METAKIT_DEBUG', false),
];

