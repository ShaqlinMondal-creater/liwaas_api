<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSpecModel extends Model
{
    protected $table = 'product_specs';

    protected $fillable = [
        'uid',
        'spec_name',
        'spec_value'
    ];

    public function variation()
    {
        return $this->belongsTo(ProductVariations::class, 'uid', 'uid');
    }
}
