<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://pawacode.com',
        'https://www.pawacode.com',
        'http://localhost:8443',
        'http://localhost:3000',
<<<<<<< HEAD
        'http://localhost:5174',
=======
>>>>>>> 1d8df1d (new update version)
        'http://localhost:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
