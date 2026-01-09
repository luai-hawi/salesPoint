<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'barcode',
        'quantity',
        'pictures',
        'cost_price',
        'selling_price',
        'has_tags',
        'is_active',
        'user_id', // the user who owns this product
        'last_sale_date',
        'deactivation_warning_months',
        'deactivation_months',
        'extended_until',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'last_sale_date' => 'datetime',
        'extended_until' => 'datetime',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_active' => 'boolean',
        'has_tags' => 'boolean',
    ];

    public function bills()
{
    return $this->belongsToMany(Bill::class, 'bill_product')
        ->withPivot('quantity','discount', 'cost_price', 'selling_price', 'tags');
}

public function batches()
{
    return $this->hasMany(Batch::class);
}

public function barcodes()
{
    return $this->hasMany(ProductBarcode::class);
}

}