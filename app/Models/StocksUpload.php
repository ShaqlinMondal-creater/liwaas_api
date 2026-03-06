<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StocksUpload extends Model
{
    protected $table = 'stocks_uploads';

    protected $fillable = [
        'type',
        'number',
        'file_name',
        'file_url'
    ];
}
