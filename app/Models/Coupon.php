<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Coupon extends Model
{
    use HasFactory;

    protected $table = 't_coupon';

    protected $fillable = [
        'key_name',
        'value',
        'status',
        'start_date',
        'end_date',
    ];
}
