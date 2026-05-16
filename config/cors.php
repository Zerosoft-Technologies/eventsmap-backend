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

    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:3000',
        'http://localhost:3001',
        'http://185.133.88.194:3001',
        'https://admin.eventsmap.zerosoft.in',
        'http://admin.eventsmap.zerosoft.in',
        
        'http://test.eventsmap.projectenconnectc.nl',
        'http://stage.eventsmap.projectenconnectc.nl',
        'http://eventsmap.projectenconnectc.nl',

        'https://test.eventsmap.projectenconnectc.nl',
        'https://stage.eventsmap.projectenconnectc.nl',
        'https://eventsmap.projectenconnectc.nl',

        'http://admin.eventsmap.projectenconnectc.nl',
        'https://admin.eventsmap.projectenconnectc.nl'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
