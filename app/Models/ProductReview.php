<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    use HasFactory;

    protected $table = 'product_reviews';

    protected $fillable = [
        'user_id',
        'products_id',
        'aid',
        'uid',
        'total_star',
        'comments',
        'upload_images',
    ];

    protected $casts = [
        'upload_images' => 'array', // Automatically cast JSON to array
    ];


     // 🔗 ProductReview belongs to User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // 🔗 ProductReview belongs to Product
    public function product()
    {
        return $this->belongsTo(Product::class, 'products_id', 'id');
    }

    // 🔗 ProductReview belongs to ProductVariation
    public function variation()
    {
        return $this->belongsTo(ProductVariations::class, 'uid', 'uid');
    }
}
