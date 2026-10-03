<?php

namespace App\Services\Restaurant;

class RestaurantCartCalculator
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function orderTotal(array $rows, float $billDiscountPercent = 0): float
    {
        $subtotal = 0.0;

        foreach ($rows as $row) {
            $subtotal += $this->lineTotal($row);
        }

        if ($billDiscountPercent > 0) {
            $subtotal -= ($subtotal * min($billDiscountPercent, 100) / 100);
        }

        return round(max(0, $subtotal), 2);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function lineTotal(array $row): float
    {
        $quantity = (float) ($row['quantity'] ?? 0);
        $line = $quantity * (float) ($row['selling_price'] ?? 0);
        $discount = (float) ($row['discount'] ?? 0);

        if (($row['discount_type'] ?? 'total') === 'per-unit') {
            $discount *= $quantity;
        }

        return max(0, $line - $discount + ($this->tagsTotal($row['tags'] ?? null) * $quantity));
    }

    public function tagsTotal(mixed $tags): float
    {
        if ($tags === null || $tags === '') {
            return 0.0;
        }

        if (is_string($tags)) {
            return $this->stringTagsTotal($tags);
        }

        if (! is_array($tags)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($tags as $tag) {
            if (is_string($tag)) {
                $total += $this->stringTagsTotal($tag);
                continue;
            }

            if (is_array($tag)) {
                if (isset($tag['price'])) {
                    $total += (float) $tag['price'];
                    continue;
                }

                $total += $this->tagsTotal(implode('&', array_map('strval', $tag)));
            }
        }

        return round($total, 2);
    }

    private function stringTagsTotal(string $tagsString): float
    {
        $total = 0.0;

        foreach (explode('&', $tagsString) as $pair) {
            if (! str_contains($pair, '@')) {
                continue;
            }

            [$name, $price] = array_pad(explode('@', $pair, 2), 2, null);
            $total += (float) $price;
        }

        return round($total, 2);
    }
}
