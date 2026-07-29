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

];
