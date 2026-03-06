<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StocksSalesOrder extends Model
{
    protected $table = 'stocks_sales_orders';

    protected $fillable = [
        'sales_order_no',
        'client_id',
        'grand_total',
        'total_tax'
    ];

    public function items()
    {
        return $this->hasMany(StocksSalesOrderItem::class,'sales_order_id');
    }
}
