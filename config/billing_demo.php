<?php

return [
    'enabled' => (bool) env('GOOGLE_PLAY_DEMO_ENABLED', false),
    'dummy_verification' => (bool) env('GOOGLE_PLAY_DEMO_DUMMY_VERIFICATION', false),
    'package_name' => 'com.domus.miapp',
    'product_id' => 'domus_dummy',
    'credentials_base64' => env('GOOGLE_PLAY_DEMO_CREDENTIALS_BASE64'),
    'credentials_json' => env('GOOGLE_PLAY_DEMO_CREDENTIALS_JSON'),
    'credentials_path' => env(
        'GOOGLE_PLAY_DEMO_CREDENTIALS_PATH',
        storage_path('app/private/google-play-demo.json'),
    ),
];
