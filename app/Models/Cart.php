<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $table = 'carts';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id', 'products_id', 'aid', 'uid', 'regular_price',
        'sell_price', 'quantity', 'total_price',
    ];
    
    // Cart belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id','id');
    }

    // Cart belongs to a product
    public function product()
    {
        return $this->belongsTo(Product::class, 'products_id', 'id');
    }

    // Cart belongs to a variations
    public function variation()
    {
        return $this->belongsTo(ProductVariations::class, 'uid', 'uid');
    }
    
}
