<?php

use App\Models\Bill;
use App\Models\ProductImei;
use App\Models\PurchaseBill;
use Tests\Support\Builds;

uses(Builds::class);

test('employees without edit permission cannot toggle product activation', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_products']);
    $product = $this->makeProduct($owner);

    $this->actingAs($employee)
        ->post(route('products.toggle-active', $product))
        ->assertForbidden();
});

test('imei endpoints return limited data without accounting and customer permissions', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_products']);
    $creator = $this->makeEmployee($owner, ['view_bills']);
    $supplier = $this->makeSupplier($owner, ['name' => 'Secret Supplier']);
    $customer = $this->makeCustomer($owner, ['name' => 'VIP Customer']);
    $product = $this->makeProduct($owner, ['has_imeis' => true]);

    $purchaseBill = new PurchaseBill([
        'supplier_id' => $supplier->id,
        'total_amount' => 100,
        'reference_number' => 'SUP-REF-55',
        'purchase_date' => now()->toDateString(),
        'created_by' => $owner->id,
        'source' => 'stock_intake',
    ]);
    $purchaseBill->user_id = $owner->id;
    $purchaseBill->save();

    $saleBill = new Bill([
        'total_price' => 150,
        'customer_id' => $customer->id,
        'created_by' => $creator->id,
        'payment_method' => 'cash',
    ]);
    $saleBill->user_id = $owner->id;
    $saleBill->save();

    $imei = ProductImei::create([
        'user_id' => $owner->id,
        'product_id' => $product->id,
        'imei' => '111222333444555',
        'supplier_id' => $supplier->id,
        'purchase_bill_id' => $purchaseBill->id,
        'sale_bill_id' => $saleBill->id,
        'unit_cost' => 100,
        'selling_price' => 150,
        'purchased_at' => now()->toDateString(),
        'sold_at' => now(),
    ]);

    $this->actingAs($employee)
        ->getJson(route('products.imeis.index', $product))
        ->assertOk()
        ->assertJsonPath('imeis.0.supplier', null)
        ->assertJsonPath('imeis.0.purchase_bill_id', null)
        ->assertJsonPath('imeis.0.sale_bill_id', null)
        ->assertJsonPath('imeis.0.unit_cost', null);

    $this->actingAs($employee)
        ->getJson(route('products.imei.search', ['imei' => $imei->imei]))
        ->assertOk()
        ->assertJsonPath('supplier', null)
        ->assertJsonPath('purchase_bill', null)
        ->assertJsonPath('unit_cost', null)
        ->assertJsonPath('sale_bill', null);
});

test('supplier lookup endpoint also requires supplier visibility', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_products', 'view_purchase_bills']);
    $product = $this->makeProduct($owner);

    $this->actingAs($employee)
        ->getJson(route('products.get-suppliers', ['product_id' => $product->id]))
        ->assertForbidden();
});

test('imei availability hides supplier and purchase details from cashiers', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['create_bills']);
    $supplier = $this->makeSupplier($owner, ['name' => 'Secret Supplier']);
    $product = $this->makeProduct($owner, ['has_imeis' => true]);

    ProductImei::create([
        'user_id' => $owner->id,
        'product_id' => $product->id,
        'imei' => '555666777888999',
        'supplier_id' => $supplier->id,
        'purchased_at' => now()->toDateString(),
        'unit_cost' => 88,
    ]);

    $this->actingAs($employee)
        ->getJson(route('products.imeis.available', $product))
        ->assertOk()
        ->assertJsonPath('imeis.0.imei', '555666777888999')
        ->assertJsonPath('imeis.0.supplier_name', null)
        ->assertJsonPath('imeis.0.purchased_at', null)
        ->assertJsonPath('imeis.0.unit_cost', null);
});

test('barcode search view redacts supplier and sale identities for limited employees', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_products']);
    $creator = $this->makeEmployee($owner, ['view_bills']);
    $supplier = $this->makeSupplier($owner, ['name' => 'Secret Supplier']);
    $customer = $this->makeCustomer($owner, ['name' => 'VIP Customer']);
    $product = $this->makeProduct($owner, ['has_imeis' => true]);

    $purchaseBill = new PurchaseBill([
        'supplier_id' => $supplier->id,
        'total_amount' => 100,
        'reference_number' => 'SUP-REF-99',
        'purchase_date' => now()->toDateString(),
        'created_by' => $owner->id,
        'source' => 'stock_intake',
    ]);
    $purchaseBill->user_id = $owner->id;
    $purchaseBill->save();

    $saleBill = new Bill([
        'total_price' => 150,
        'customer_id' => $customer->id,
        'created_by' => $creator->id,
        'payment_method' => 'cash',
    ]);
    $saleBill->user_id = $owner->id;
    $saleBill->save();

    ProductImei::create([
        'user_id' => $owner->id,
        'product_id' => $product->id,
        'imei' => '999888777666555',
        'supplier_id' => $supplier->id,
        'purchase_bill_id' => $purchaseBill->id,
        'sale_bill_id' => $saleBill->id,
        'unit_cost' => 100,
        'selling_price' => 150,
        'purchased_at' => now()->toDateString(),
        'sold_at' => now(),
    ]);

    $response = $this->actingAs($employee)->get(route('products.search-barcode', ['barcode' => '999888777666555']));

    $response->assertOk()
        ->assertDontSee('Secret Supplier')
        ->assertDontSee('VIP Customer')
        ->assertDontSee($creator->name)
        ->assertDontSee('SUP-REF-99')
        ->assertDontSee('100.00');
});
