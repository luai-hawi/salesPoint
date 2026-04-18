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
        'customer_id',
        'user_id',
        'created_by', // New field to store the creator's ID
        'is_damaged',
        'is_returned',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'bill_product')
            ->withPivot('quantity', 'discount', 'cost_price', 'selling_price', 'tags');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
