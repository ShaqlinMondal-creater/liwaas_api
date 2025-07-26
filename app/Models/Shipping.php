<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Shipping extends Model
{
    use HasFactory;

    protected $table = 't_shipping';

    protected $fillable = [
        'shipping_status',
        'shipping_by',
        'address_id',
        'shipping_charge',
        'shipping_delivery_id',
        'response_',
    ];
}
