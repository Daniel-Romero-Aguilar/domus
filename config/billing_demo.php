<?php

return [
    'enabled' => (bool) env('GOOGLE_PLAY_DEMO_ENABLED', false),
    'package_name' => 'com.domus.miapp',
    'product_id' => 'domus_dummy',
    'credentials_json' => env('GOOGLE_PLAY_DEMO_CREDENTIALS_JSON'),
    'credentials_path' => env(
        'GOOGLE_PLAY_DEMO_CREDENTIALS_PATH',
        storage_path('app/private/google-play-demo.json'),
    ),
];
