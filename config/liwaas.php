<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shipping Rules
    |--------------------------------------------------------------------------
    |
    | Free shipping when cart sell-total (before coupon) is >= min_order_price.
    | Otherwise shipping_charge is applied.
    |
    */

    'min_order_price' => 500,
    'shipping_charge' => 80,

    /*
    |--------------------------------------------------------------------------
    | Payment cron
    |--------------------------------------------------------------------------
    |
    | Shared secret for POST /api/payments/checkPaymentCorn.
    | Send as header X-Cron-Secret (preferred) or Bearer token.
    |
    */

    'payment_cron_secret' => env('PAYMENT_CRON_SECRET'),

];
