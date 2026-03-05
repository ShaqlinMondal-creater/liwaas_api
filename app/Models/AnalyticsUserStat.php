<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsUserStat extends Model
{
    protected $table = 'analytics_user_stats';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'total_orders',
        'total_spent',
        'last_order_date'
    ];

    protected $casts = [
        'last_order_date' => 'datetime'
    ];

    // 🔗 Relation
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
