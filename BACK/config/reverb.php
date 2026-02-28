<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Application
    |--------------------------------------------------------------------------
    |
    | Reverb supports broadcasting for one or more "applications". Each
    | application is identified by an ID (configured via REVERB_APP_ID and
    | friends). When Reverb receives a request it will attempt to resolve the
    | incoming app id against the list below.
    |
    */

    'apps' => [
        [
            'app_id' => env('REVERB_APP_ID', 'rrhh'),
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'allowed_origins' => [
                env('APP_URL', 'http://localhost'),
                // add additional origins (e.g. front host) if needed
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Server options
    |--------------------------------------------------------------------------
    |
    | These values configure the host/port used by the Reverb server when
    | you run `php artisan reverb:start`.  They can also be overridden via
    | REVERB_SERVER_HOST / REVERB_SERVER_PORT environment variables.
    |
    */

    'server' => [
        'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
        'port' => env('REVERB_SERVER_PORT', 6001),
        'debug' => env('REVERB_DEBUG', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scaling (Redis pub/sub)
    |--------------------------------------------------------------------------
    |
    | If you enable scaling Reverb will publish messages to the configured
    | Redis connection so that multiple Reverb servers can stay in sync.
    |
    */

    'scaling' => [
        'enabled' => env('REVERB_SCALING_ENABLED', false),
        'redis_connection' => env('REVERB_SCALING_REDIS', 'default'),
    ],
];
