<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use  App\Models\Counter;

class Orders extends Model
{
    use HasFactory;
    protected $table = 'orders'; // ✅ Correct table name

    protected $fillable = [
        'order_code',
        'invoice_no',
        'invoice_link',
        'shipping',
        'ship_delivery_id',
        'shipping_type',
        'shipping_by',
        'shipping_id',
        'user_id',
        'payment_type',
        'payment_status',
        'razorpay_order_id',
        'delivery_status',
        'coupon_id',
        'track_code',
        'tax_price',
        'shipping_charge',
        'grand_total',
    ];


    // 🔗 Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id'); // FK: user_id → users.id
    }

    public function items()
    {
        return $this->hasMany(OrderItems::class, 'order_id', 'id'); // FK: order_id → orders.id
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class); // Only if you have a Coupon model
    }
}
