<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddressModel extends Model
{
    use HasFactory;

    // Tell Laravel the actual table name
    protected $table = 'addresses'; // <-- this fixes the error

    // Define the fillable attributes to allow mass assignment
    protected $fillable = [
        'registered_user', // Add the registered_user field here
        'name',
        'email',
        'address_type',
        'mobile',
        'state',
        'city',
        'country',
        'pincode',
        'address_line_1',
        'address_line_2',
    ];

    // Optional: If you're using timestamps (created_at, updated_at), leave the following line
    // protected $hidden = ['created_at', 'updated_at'];

    // Each address belongs to one user
    public function user()
    {
        return $this->belongsTo(User::class, 'registered_user');
    }
}
