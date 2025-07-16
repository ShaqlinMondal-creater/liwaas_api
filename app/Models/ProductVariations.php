<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariations extends Model
{
    protected $table = 'product_variations';

    protected $fillable = [
        'aid', 'uid', 'regular_price', 'sell_price', 'currency',
        'gst', 'stock', 'images_id', 'color', 'size'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'aid', 'aid');
    }

    public function carts()
    {
        return $this->hasMany(Cart::class, 'uid', 'uid');
    }
}
