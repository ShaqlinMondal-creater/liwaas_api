<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StocksReturnItem extends Model
{
    protected $table = 'stocks_return_items';

    protected $fillable = [
        'sales_order_id',
        'sales_order_item_id',
        'status',
        'uid',
        'qty',
        'price',
        'tax',
        'sub_total',
        'sub_total_tax',
        'return_date'
    ];

    protected $casts = [
        'return_date' => 'date',
        'price' => 'float',
        'tax' => 'float',
        'sub_total' => 'float',
        'sub_total_tax' => 'float',
    ];

    // Relationships (optional but recommended)

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function salesOrderItem()
    {
        return $this->belongsTo(SalesOrderItem::class);
    }
}