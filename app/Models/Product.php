<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'pictures',
        'cost_price',
        'selling_price',
    ];

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }
}