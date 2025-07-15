<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'barcode',
        'quantity',
        'pictures',
        'cost_price',
        'selling_price',
    ];

    public function bills()
{
    return $this->belongsToMany(Bill::class, 'bill_product')
        ->withPivot('quantity','discount', 'cost_price', 'selling_price');
}
}