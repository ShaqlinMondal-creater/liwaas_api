<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StocksClient extends Model
{
    use HasFactory;

    protected $table = 'stocks_clients';

    protected $fillable = [
        'name',
        'owner_name',
        'mobile',
        'address',
        'email',
        'status'
    ];
}
