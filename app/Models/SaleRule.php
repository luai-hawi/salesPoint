<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleRule extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'discount_type',
        'discount_value',
        'applies_every_n',
    ];

    protected $casts = [
        'discount_value'  => 'float',
        'applies_every_n' => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate the total discount for a given quantity of items.
     *
     * Logic:
     * - applies_every_n = 1 → automatic per-item discount (applied to ALL units)
     * - applies_every_n = N → discount applied per complete group of N units
     *   Example: price=4, qty=7, every_n=3, discount=2 (amount on 3 items)
     *     groups = floor(7/3) = 2 → discount = 2 * 2 = 4  → total = 28 - 4 = 24
     */
    public function calculateDiscount(float $unitPrice, float $quantity): float
    {
        $n = max(1, $this->applies_every_n);

        if ($n === 1) {
            // Automatic per-item discount
            $rawDiscount = $this->discount_type === 'percentage'
                ? $unitPrice * ($this->discount_value / 100)
                : $this->discount_value;

            return round($rawDiscount * $quantity, 2);
        }

        // Quantity-based: discount per complete group of N items
        $groups = floor($quantity / $n);

        if ($groups <= 0) {
            return 0.0;
        }

        // Discount per group
        if ($this->discount_type === 'percentage') {
            $discountPerGroup = ($unitPrice * $n) * ($this->discount_value / 100);
        } else {
            $discountPerGroup = $this->discount_value;
        }

        return round($groups * $discountPerGroup, 2);
    }
}
