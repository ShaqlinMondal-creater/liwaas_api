<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionView extends Model
{
    use HasFactory;

    protected $table = 'section_views';

    protected $fillable = [
        'section_name',
        'uid',
        'status',
        'force_status',
    ];
}
