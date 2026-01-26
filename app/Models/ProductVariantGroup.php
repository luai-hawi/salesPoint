<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariantGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_id',
    ];

    /**
     * Get the user that owns the variant group.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all products (variants) in this group.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'variant_group_id');
    }
}
