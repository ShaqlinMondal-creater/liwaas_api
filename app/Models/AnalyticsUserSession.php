<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsUserSession extends Model
{
    protected $table = 'analytics_user_sessions';

    protected $fillable = [
        'user_id',
        'ip_address',
        'country',
        'device',
        'browser',
        'platform',
        'last_activity'
    ];

    protected $casts = [
        'last_activity' => 'datetime'
    ];

    // 🔗 Relation
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
