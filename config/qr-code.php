<?php

return [
    // The size of the generated QR code (in pixels)
    'size' => 200,

    // The error correction level (L, M, Q, H)
    'error_correction_level' => 'H',

    // The foreground color of the QR code (RGB format)
    'foreground_color' => [0, 0, 0],

    // The background color of the QR code (RGB format)
    'background_color' => [255, 255, 255],

    // The label (if any) for the QR code
    'label' => '',

    // Font size of the label (if any)
    'label_font_size' => 16,

    // The image type of the QR code (png, jpeg, etc.)
    'image_type' => 'png',

    // Path for caching generated QR codes (if needed)
    'cache_path' => storage_path('app/qrcodes'),

    // Use Bacon QR Code library (default: true)
    'use_bacon' => true,

    'bacon' => [
        'image_backend' => 'gd', // Use GD as the image backend (to avoid Imagick)
    ],
];
