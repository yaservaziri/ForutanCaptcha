<?php

return [
    // Where to load CAPTCHA images from (see docs above).
    'image_seed_path' => null,
    // CAPTCHA image resize dimensions (pixels) and quality.
    'default_width' => 200,
    'default_height' => 200,
    'default_quality' => 80,
    // Font used in images.
    'font_path' => base_path('vendor/forutan/captcha/resources/fonts/lightweight.ttf'),
    // Where to redirect after solving CAPTCHA per context.
    'redirect_on_pass' => [
      'default' => '/',
    ],
    // Number of images shown in grid.
    'image_count' => [
      'default' => 12,
    ],
    // How many correct images required to pass.
    'min_correct' => ['default' => 3],
    'max_correct' => ['default' => 6],
    // Token/session expiration.
    'expire_minutes' => ['default' => 2],
    // Anti-brute-force: retry limits and block times.
    'max_attempts' => ['default' => 3],    
    'block_duration_minutes' => ['default' => 60],
    // Route prefix and middleware.
    'route_prefix' => 'fcaptcha',
    'middleware' => ['web'],
    // per-route rate limiting.
    'throttle' => [
        'show' => '100,1',
        'verify' => '100,1',
        'image' => '100,1',
    ],
];
