<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StocksSalesOrderItem extends Model
{
    protected $table = 'stocks_sales_order_items';

    protected $fillable = [
        'sales_order_id',
        'uid',
        'qty',
        'price',
        'tax',
        'sub_total',
        'sub_total_tax'
    ];

    public function product()
    {
        return $this->belongsTo(StocksProduct::class,'uid','uid');
    }
}
