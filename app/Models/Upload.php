<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    protected $table = 'uploads';
    protected $primaryKey = 'id';

    protected $fillable = ['path', 'url', 'file_name', 'extension'];

    public function products()
    {
        return $this->hasMany(Product::class, 'upload_id', 'id');
    }
}
