<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsProductView extends Model
{
    protected $table = 'analytics_product_views';

    protected $fillable = [
        'user_id',
        'product_id'
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
