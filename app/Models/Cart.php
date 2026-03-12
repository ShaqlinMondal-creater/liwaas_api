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

    // ⭐ Add this
    protected $casts = [
        'regular_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity' => 'integer'
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
