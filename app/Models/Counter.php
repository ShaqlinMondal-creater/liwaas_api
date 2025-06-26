<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Orders;

class Counter extends Model
{
    use HasFactory;

    protected $table = 'counters';

    protected $fillable = [
        'name',
        'prefix',
        'postfix',
    ];

    // Relationship: One counter is used by many orders
    public function orders()
    {
        return $this->hasMany(Orders::class);
    }
}
