<?php

declare(strict_types=1);

return [
    'manifest' => [
        'name' => 'Draw',
        'slug' => 'draw',
        'description' => 'A team whiteboard where people and agents sketch together in real time.',
        'icon' => 'https://scalpels.app/img/products/transparent/draw.png',
        'product_url' => 'https://scalpels.app/products/draw',
    ],

    'credentials' => [
        'guard' => env('BUILT_FOR_CLOUD_CREDENTIAL_GUARD', 'bfc'),
        'declaration' => null,
        'session_guard' => null,
        'app_purposes' => [],
    ],

    'ui' => [
        'landing_page' => false,
        'member_management' => false,
        'personal_credentials' => false,
        'installation_credentials' => false,
        'session_management' => false,
        'managed_transitions' => false,
        'credential_purposes' => [
        ],
    ],
];
