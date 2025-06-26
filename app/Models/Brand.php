<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $table = 'brands';
    protected $primaryKey = 'id'; // default, shown for clarity

    // protected $fillable = ['name','logo'];
    protected $fillable = ['name'];
    
    public function products()
    {
        return $this->hasMany(Product::class, 'brand_id', 'id');
        // foreign_key on Product = brand_id, local_key on Brand = id
    }
}
