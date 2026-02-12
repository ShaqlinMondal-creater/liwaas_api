<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $table = 't_payments';

    protected $fillable = [
        'genarate_order_id',
        'payment_type',
        'transaction_payment_id',
        'payment_amount',
        'payment_status',
        'order_id',
        'user_id',
        'response_',
    ];

    public function order()
    {
        return $this->belongsTo(\App\Models\Orders::class, 'order_id');
    }
}
