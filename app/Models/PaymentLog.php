<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    protected $table = 't_payment_logs';

    protected $fillable = [
        'order_id',
        'payment_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'status',
        'request_payload',
        'response_payload'
    ];
}
