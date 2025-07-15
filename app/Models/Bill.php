<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'total_price',
        'note',
    ];

    public function products()
{
    return $this->belongsToMany(Product::class, 'bill_product')
        ->withPivot('quantity', 'discount', 'cost_price', 'selling_price');
}
}