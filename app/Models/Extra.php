<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Extra extends Model
{
    use HasFactory;

    // Explicitly specify the table name
    protected $table = 'extras';

    // Allow mass assignment for these fields
    protected $fillable = [
        'purpose_name',
        'comments',
        'highlights',
        'file_name',
        'file_path',
        'show_status',
    ];
}
