<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     * The storefront is a separate origin. Set FRONTEND_URL (comma-separated
     * for several) before deploying — '*' is a development convenience and
     * must not ship.
     */
    'allowed_origins' => array_filter(explode(',', (string) env('FRONTEND_URL', '*'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    /*
     * The guest cart token is returned on this header. Without exposing it,
     * a cross-origin browser can read the body but not the header.
     */
    'exposed_headers' => ['X-Cart-Token'],

    'max_age' => 0,

    'supports_credentials' => false,

];
