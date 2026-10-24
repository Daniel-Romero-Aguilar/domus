<?php

return [
    'enabled' => (bool) env('GOOGLE_PLAY_DEMO_ENABLED', false),
    'dummy_verification' => (bool) env('GOOGLE_PLAY_DEMO_DUMMY_VERIFICATION', false),
    'package_name' => 'com.domus.miapp',
    'product_id' => 'domus_dummy',
    'refresh_interval_minutes' => 5,
    'retry_max_attempts' => max(1, (int) env('GOOGLE_PLAY_RETRY_MAX_ATTEMPTS', 50)),
    'retry_window_days' => max(1, (int) env('GOOGLE_PLAY_RETRY_WINDOW_DAYS', 3)),
    'retry_delays_minutes' => [5, 15, 30, 60, 120, 180],
    'credentials_base64' => env('GOOGLE_PLAY_DEMO_CREDENTIALS_BASE64'),
    'credentials_json' => env('GOOGLE_PLAY_DEMO_CREDENTIALS_JSON'),
    'credentials_path' => env(
        'GOOGLE_PLAY_DEMO_CREDENTIALS_PATH',
        storage_path('app/private/google-play-demo.json'),
    ),
];
