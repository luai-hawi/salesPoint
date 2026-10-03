<?php

use App\Models\Bill;
use Tests\Support\Builds;

uses(Builds::class);

function indexBillPayload($product, array $overrides = []): array
{
    return array_replace_recursive([
        'product_ids' => [$product->id],
        'quantities' => [1],
        'discounts' => [0],
        'cost_prices' => [(float) $product->cost_price],
        'selling_prices' => [(float) $product->selling_price],
        'discount_types' => ['total'],
        'product_tags' => [null],
    ], $overrides);
}

it('computes the bills page totals over every filtered bill, not only the visible page', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 500, 'cost_price' => 4, 'selling_price' => 10]);
    $product->batches()->create(['quantity' => 500, 'cost_price' => 4, 'user_id' => $owner->id]);

    // 55 customer bills of 10 (1 paid each) + 2 walk-in cash bills: more than one page of 50 bills.
    foreach (range(1, 55) as $i) {
        $this->actingAs($owner)->postJson('/bills', indexBillPayload($product, [
            'customer_id' => $customer->id,
            'paid_amount' => 1,
        ]))->assertOk();
    }
    foreach (range(1, 2) as $i) {
        $this->actingAs($owner)->postJson('/bills', indexBillPayload($product))->assertOk();
    }

    expect(Bill::withoutGlobalScopes()->where('user_id', $owner->id)->count())->toBe(57);

    $response = $this->actingAs($owner)->get(route('bills.index'))->assertOk();

    expect((float) $response->viewData('totalSales'))->toBe(570.0)
        ->and((float) $response->viewData('totalProfit'))->toBe(342.0)
        ->and((float) $response->viewData('filteredPaid'))->toBe(75.0)
        ->and((float) $response->viewData('filteredDue'))->toBe(495.0)
        ->and($response->viewData('bills')->count())->toBe(50);
});
