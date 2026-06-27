<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImei extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'imei',
        'supplier_id',
        'purchase_bill_id',
        'sale_bill_id',
        'unit_cost',
        'selling_price',
        'purchased_at',
        'sold_at',
    ];

    protected $casts = [
        'purchased_at' => 'date',
        'sold_at' => 'datetime',
        'unit_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseBill()
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }

    public function saleBill()
    {
        return $this->belongsTo(Bill::class, 'sale_bill_id');
    }

    public function isSold(): bool
    {
        return !is_null($this->sale_bill_id);
    }
}
