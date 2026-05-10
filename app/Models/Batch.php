<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'product_id',
        'quantity',
        'cost_price',
        'user_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
