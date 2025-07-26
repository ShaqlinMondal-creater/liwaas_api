<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Invoices extends Model
{
    use HasFactory;

    protected $table = 't_invoice';

    protected $fillable = [
        'invoice_no',
        'invoice_link',
        'invoice_qr',
        'date',
    ];

    public $timestamps = true; // Only if you're using created_at / updated_at
}
