<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsCartActivity extends Model
{
    protected $table = 'analytics_cart_activities';

    protected $fillable = [
        'user_id',
        'product_id',
        'qty',
        'status'
    ];

    // 🔗 Relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
