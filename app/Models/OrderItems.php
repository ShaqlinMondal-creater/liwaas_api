<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItems extends Model
{
    use HasFactory;
    protected $table = 'order_details'; // ✅ Correct table name
    protected $fillable = [
        'order_id',
        'user_id',
        'product_id',
        'aid',
        'uid',
        'quantity',
        'total',
        'tax',
    ];

    // 🔗 Relationships
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id'); // FK: order_id → orders.id
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id'); // FK: user_id → users.id
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id'); // FK: product_id → products.id
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariations::class, 'uid', 'uid'); // FK: uid → product_variations.uid
    }
}
