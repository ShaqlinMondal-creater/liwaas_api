<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    
    protected $fillable = [
        'user_id',
        'products_id',
        'aid',
        'uid',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'products_id');
    }

    public function variation()
    {
        return $this->hasOne(ProductVariations::class, 'uid', 'uid');
    }
    
}
