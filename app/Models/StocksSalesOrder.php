<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\StocksClient;
use App\Models\StocksSalesOrderItem;

class StocksSalesOrder extends Model
{
    protected $table = 'stocks_sales_orders';

    protected $fillable = [
        'sales_order_no',
        'client_id',
        'grand_total',
        'total_tax'
    ];

    public function client()
    {
        return $this->belongsTo(StocksClient::class,'client_id');
    }

    public function items()
    {
        return $this->hasMany(StocksSalesOrderItem::class,'sales_order_id');
    }
}
