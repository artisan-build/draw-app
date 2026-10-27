<?php

declare(strict_types=1);

$localSeed = (string) (env('APP_KEY') ?: 'draw-local');
$localKey = substr(hash('sha256', $localSeed.':reverb-key'), 0, 32);
$localSecret = hash('sha256', $localSeed.':reverb-secret');
$localAppId = substr(hash('sha256', $localSeed.':reverb-app'), 0, 16);

return [
    'default' => env('BROADCAST_CONNECTION', 'reverb'),

    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY', $localKey),
            'secret' => env('REVERB_APP_SECRET', $localSecret),
            'app_id' => env('REVERB_APP_ID', $localAppId),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'client_options' => [],
        ],

        'log' => ['driver' => 'log'],
        'null' => ['driver' => 'null'],
    ],
];
