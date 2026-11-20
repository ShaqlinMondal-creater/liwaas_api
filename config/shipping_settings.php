<?php

return [
    'file' => 'shipping_settings.json',

    /*
    | Default free-shipping threshold and flat fee.
    | Admin can override these via Store → Shipping rules (saved to shipping_settings.json).
    */
    'defaults' => [
        'min_order_price' => (float) env('SHIPPING_MIN_ORDER_PRICE', 500),
        'shipping_charge' => (float) env('SHIPPING_CHARGE', 80),
        'enabled' => true,
    ],
];
