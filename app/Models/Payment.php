<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Orders;

class Payment extends Model
{
    use HasFactory;

    // Define table name (optional if name is not plural of model)
    protected $table = 'payments';

    // Fillable columns
    protected $fillable = [
        'razorpay_payment_id',
        'razorpay_order_id',
        'method',
        'amount',
        'status',
        'order_id',
        'user_id',
    ];

    // Relationship: Payment belongs to a User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: Payment belongs to an Order
    public function order()
    {
        return $this->belongsTo(Orders::class);
    }
}
