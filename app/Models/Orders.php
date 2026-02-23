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
        'user_id',
        'order_code',
        'invoice_id',
        'shipping_id',
        'tax_price',
        'grand_total',
        'payment_type',
        'payment_id',
        'order_status',
        'coupon_id',
        'coupon_discount',
        'other_text',
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

    public function invoice()
    {
        return $this->belongsTo(\App\Models\Invoices::class, 'invoice_id');
    }

    public function shipping()
    {
        return $this->belongsTo(\App\Models\Shipping::class, 'shipping_id');
    }

    public function payment()
    {
        return $this->belongsTo(\App\Models\Payment::class, 'payment_id');
    }

}
