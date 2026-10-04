<?php

use App\Models\ProductImei;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Builds;

uses(Builds::class);

test('stock summary clearly labels its current-page scope in both languages and views', function (string $locale, string $label) {
    $owner = $this->makeOwner();
    $this->actingAs($owner);

    foreach (['table', 'cards'] as $view) {
        $this->withSession(['locale' => $locale])
            ->get(route('products.index', ['view' => $view]))
            ->assertOk()
            ->assertSee($label);
    }
})->with([
    ['en', 'Stock units on this page'],
    ['ar', 'وحدات المخزون في هذه الصفحة فقط'],
]);

test('both product views use a visible stock dialog with product-specific costs', function () {
    $owner = $this->makeOwner();
    $this->makeProduct($owner, ['cost_price' => 12.5]);
    foreach (['cards', 'table'] as $view) {
        $this->actingAs($owner)->get(route('products.index', ['view' => $view]))->assertOk()
            ->assertSee('x-show="stockFormOpen"', false)
            ->assertDontSee(':class="stockFormOpen', false)
            ->assertSee('costPrice: 12.5', false)
            ->assertSee('@submit.prevent="submitQuickStock()"', false);
    }
});

test('product pages render when stored pictures are null or invalid json', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner, ['pictures' => '{invalid']);

    $this->actingAs($owner)->get(route('products.index'))->assertOk();
    $this->actingAs($owner)->get(route('products.edit', $product))->assertOk();
    $this->actingAs($owner)->get(route('products.out-of-stock'))->assertOk();
});

test('products keep large pictures json payloads after the pictures column is widened', function () {
    $owner = $this->makeOwner();
    $pictures = json_encode([
        'products/' . str_repeat('a', 490) . '.jpg',
        'products/' . str_repeat('b', 490) . '.jpg',
    ], JSON_UNESCAPED_SLASHES);

    expect(strlen($pictures))->toBeGreaterThan(1000);

    $product = Product::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'name' => 'Large Gallery',
        'barcode' => 'LG-01',
        'quantity' => 0,
        'cost_price' => 10,
        'selling_price' => 15,
        'pictures' => $pictures,
    ]);

    expect($product->fresh()->pictures)->toBe($pictures);
});

test('product create and add-variants forms render intake idempotency inputs', function () {
    $owner = $this->makeOwner();

    $this->actingAs($owner)
        ->get(route('products.create'))
        ->assertOk()
        ->assertSee('name="intake_client_uuid"', false);

    $group = \App\Models\ProductVariantGroup::create([
        'name' => 'Set',
        'user_id' => $owner->id,
    ]);
    $product = $this->makeProduct($owner, [
        'variant_group_id' => $group->id,
        'variant_name' => 'Base',
    ]);

    $this->actingAs($owner)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertSee('name="intake_client_uuid"', false);
});

test('employee without product permissions cannot open product pages', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, []);
    $product = $this->makeProduct($owner);

    $this->actingAs($employee)->get(route('products.index'))->assertForbidden();
    $this->actingAs($employee)->get(route('products.create'))->assertForbidden();
    $this->actingAs($employee)->get(route('products.edit', $product))->assertForbidden();
});

test('employee without permissions is blocked from protected product and imei endpoints', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $employee = $this->makeEmployee($owner, []);
    $product = $this->makeProduct($owner, ['has_imeis' => true]);
    $imei = ProductImei::create([
        'user_id' => $owner->id,
        'product_id' => $product->id,
        'imei' => '123456789012345',
    ]);

    $requests = [
        ['getJson', url('/products/searchAll'), []],
        ['getJson', route('products.search', ['barcode' => $product->barcode]), []],
        ['getJson', url('/products/searchWithoutBarcode?search=' . urlencode($product->name)), []],
        ['getJson', route('products.categories'), []],
        ['postJson', route('products.check-barcodes'), ['barcode' => $product->barcode]],
        ['get', route('products.search-barcode', ['barcode' => $product->barcode]), []],
        ['get', route('barcode.search'), []],
        ['getJson', route('products.imei.check', ['imei' => $imei->imei]), []],
        ['getJson', route('products.imei.search', ['imei' => $imei->imei]), []],
        ['getJson', route('products.imeis.index', $product), []],
        ['getJson', route('products.imeis.available', $product), []],
        ['postJson', route('products.imeis.store', $product), ['imeis' => ['998877665544332']]],
        ['deleteJson', route('products.imeis.destroy', [$product, $imei]), []],
        ['get', route('products.out-of-stock'), []],
        ['post', route('products.out-of-stock.bulk'), ['action' => 'deactivate', 'product_ids' => [$product->id]]],
        ['get', route('products.next-id'), []],
        ['get', route('products.export'), []],
    ];

    foreach ($requests as [$method, $url, $payload]) {
        $response = $this->actingAs($employee)->{$method}($url, $payload);
        $response->assertForbidden();
    }

    $this->actingAs($employee)
        ->getJson(route('products.get-suppliers', ['product_id' => $product->id]))
        ->assertForbidden();
});

test('cashier permissions keep pos product search and imei availability working', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['create_bills']);
    $product = $this->makeProduct($owner, ['has_imeis' => true]);
    ProductImei::create([
        'user_id' => $owner->id,
        'product_id' => $product->id,
        'imei' => '555444333222111',
    ]);

    $this->actingAs($employee)
        ->getJson(url('/products/searchAll?search=' . urlencode($product->name)))
        ->assertOk();

    $this->actingAs($employee)
        ->getJson(route('products.search', ['barcode' => $product->barcode]))
        ->assertOk();

    $this->actingAs($employee)
        ->getJson(url('/products/searchWithoutBarcode?search=' . urlencode($product->name)))
        ->assertOk();

    $this->actingAs($employee)
        ->getJson(route('products.imeis.available', $product))
        ->assertOk();
});

test('other tenants cannot edit another shops product', function () {
    $owner = $this->makeOwner();
    $other = $this->makeOwner();
    $product = $this->makeProduct($owner);

    $this->actingAs($other)->get(route('products.edit', $product))->assertNotFound();
    $this->actingAs($other)->put(route('products.update', $product), [
        'name' => 'Blocked',
        'cost_price' => 1,
        'selling_price' => 2,
        'funding_mode' => 'none',
    ])->assertNotFound();
});

test('out of stock get requests stay read only even if action parameters are supplied', function () {
    Storage::fake('public');

    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner, [
        'quantity' => 0,
        'pictures' => json_encode(['products/out-stock.jpg']),
        'last_sale_date' => now()->subMonths(8),
    ]);

    Storage::disk('public')->put('products/out-stock.jpg', 'file');

    $this->actingAs($owner)->get(route('products.out-of-stock', [
        'action' => 'deactivate',
        'product_ids' => [$product->id],
    ]))->assertOk();

    expect($product->fresh()->is_active)->toBeTrue();
    Storage::disk('public')->assertExists('products/out-stock.jpg');
});

test('out of stock bulk actions require edit permission and post deactivation works', function () {
    Storage::fake('public');

    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_products']);
    $product = $this->makeProduct($owner, [
        'quantity' => 0,
        'pictures' => json_encode(['products/deactivate-me.jpg']),
        'last_sale_date' => now()->subMonths(8),
    ]);

    Storage::disk('public')->put('products/deactivate-me.jpg', 'file');

    $this->actingAs($employee)->post(route('products.out-of-stock.bulk'), [
        'action' => 'deactivate',
        'product_ids' => [$product->id],
    ])->assertForbidden();

    $this->actingAs($owner)->post(route('products.out-of-stock.bulk'), [
        'action' => 'deactivate',
        'product_ids' => [$product->id],
    ])->assertRedirect(route('products.out-of-stock'));

    expect($product->fresh()->is_active)->toBeFalse();
    Storage::disk('public')->assertMissing('products/deactivate-me.jpg');
});
