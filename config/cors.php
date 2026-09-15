<?php

return [
    'paths'                    => ['api/*'],
    'allowed_origins'          => array_filter(array_map('trim', explode(',', env('cors.allowed_origins', '')))),
    'allowed_origins_patterns' => [],
    'allowed_methods'          => ['*'],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [],
    'max_age'                  => 0,
    'supports_credentials'     => false,
];
