<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StocksProduct extends Model
{
    protected $table = 'stocks_products';

    protected $fillable = [
        'uid',
        'name',
        'size',
        'color',
        'list_price',
        'sale_price',
        'stock',
        'status'
    ];

}
