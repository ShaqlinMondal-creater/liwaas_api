<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    // protected $fillable = [
    //     'aid', 'name', 'brand_id', 'category_id', 'slug', 'description', 'specification',
    //     'gender', 'cod', 'shipping', 'ratings', 'keyword', 'image_url', 'upload_id',
    //     'product_status', 'added_by', 'custom_design'
    // ];

    protected $table = 'products';
    protected $primaryKey = 'id';

    protected $fillable = [
        'aid', 'name', 'brand_id', 'category_id', 'slug', 'description', 'specification', 
        'gender', 'cod', 'shipping', 'ratings', 'keyword', 'image_url', 'upload_id', 
        'product_status', 'added_by', 'custom_design'
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function upload()
    {
        return $this->belongsTo(Upload::class, 'upload_id', 'id');
    }

    public function variations()
    {
        return $this->hasMany(ProductVariations::class, 'aid', 'aid'); // foreign key in variations = aid, local key in products = aid
    }

    public function carts()
    {
        return $this->hasMany(Cart::class, 'products_id', 'id');
    }
    
}
