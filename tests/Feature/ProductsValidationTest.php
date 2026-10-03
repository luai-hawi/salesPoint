<?php

use Tests\Support\Builds;

uses(Builds::class);

test('creating a product rejects barcodes already used by another product in the same shop', function () {
    $owner = $this->makeOwner(['image_limit' => 10]);
    $existing = $this->makeProduct($owner, ['barcode' => 'DUP-100']);

    $response = $this->actingAs($owner)->post(route('products.store'), [
        'name' => 'Duplicate Barcode Product',
        'barcode' => 'DUP-100',
        'quantity' => 1,
        'cost_price' => 10,
        'selling_price' => 20,
        'low_stock_threshold' => 10,
    ]);

    $response->assertSessionHasErrors('barcode');
});

test('product low stock threshold defaults to ten when left blank and rejects fractions', function () {
    $owner = $this->makeOwner(['image_limit' => 10]);

    $createResponse = $this->actingAs($owner)->post(route('products.store'), [
        'name' => 'Threshold Product',
        'barcode' => 'TH-100',
        'quantity' => 1,
        'cost_price' => 10,
        'selling_price' => 20,
        'low_stock_threshold' => '',
    ]);

    $createResponse->assertRedirect(route('products.index'));

    $product = \App\Models\Product::withoutGlobalScopes()->where('user_id', $owner->id)->latest('id')->firstOrFail();
    expect((int) $product->low_stock_threshold)->toBe(10);

    $this->actingAs($owner)
        ->put(route('products.update', $product), [
            'name' => $product->name,
            'barcode' => $product->barcode,
            'cost_price' => $product->cost_price,
            'selling_price' => $product->selling_price,
            'low_stock_threshold' => '2.5',
        ])
        ->assertSessionHasErrors('low_stock_threshold');
});

test('bulk status redirect keeps the low stock filter', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner);

    $response = $this->actingAs($owner)->post(route('products.bulk-status'), [
        'product_ids' => [$product->id],
        'action' => 'deactivate',
        'low_stock' => '1',
    ]);

    $response->assertRedirect(route('products.index', ['low_stock' => '1']));
});

test('out of stock bulk redirect keeps the active filter settings', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner, [
        'quantity' => 0,
        'last_sale_date' => now()->subMonths(8),
    ]);

    $response = $this->actingAs($owner)->post(route('products.out-of-stock.bulk'), [
        'product_ids' => [$product->id],
        'action' => 'deactivate',
        'filter' => 'warning',
        'warning_months' => 4,
        'deactivation_months' => 6,
    ]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('warning_months=4')
        ->toContain('deactivation_months=6')
        ->toContain('filter=warning');
});
