<?php

use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductVariantGroup;
use App\Models\PurchaseBill;
use App\Models\SupplierPayment;
use Tests\Support\Builds;

uses(Builds::class);

test('creating a product with stock on credit records supplier debt and a batch', function () {
    $owner = $this->makeOwner(['image_limit' => 10]);
    $supplier = $this->makeSupplier($owner);

    $response = $this->actingAs($owner)->post(route('products.store'), [
        'name' => 'Console',
        'barcode' => 'PS-01',
        'quantity' => 5,
        'cost_price' => 40,
        'selling_price' => 75,
        'low_stock_threshold' => 1,
        'funding_mode' => 'credit',
        'funding_supplier_id' => $supplier->id,
    ]);

    $response->assertRedirect(route('products.index'));

    $bill = PurchaseBill::withoutGlobalScopes()->latest('id')->firstOrFail();
    $product = Product::withoutGlobalScopes()->where('user_id', $owner->id)->latest('id')->firstOrFail();
    $batch = Batch::withoutGlobalScopes()->where('product_id', $product->id)->firstOrFail();

    expect($bill->source)->toBe('stock_intake')
        ->and((float) $supplier->fresh()->balance)->toBe(200.0)
        ->and(Batch::withoutGlobalScopes()->where('product_id', $product->id)->count())->toBe(1)
        ->and($batch->purchase_bill_id)->toBe($bill->id);
});

test('funded initial product batches are locked after creation', function () {
    $owner = $this->makeOwner(['image_limit' => 10]);
    $supplier = $this->makeSupplier($owner);

    $response = $this->actingAs($owner)->post(route('products.store'), [
        'name' => 'Printer',
        'barcode' => 'PR-01',
        'quantity' => 2,
        'cost_price' => 55,
        'selling_price' => 90,
        'funding_mode' => 'credit',
        'funding_supplier_id' => $supplier->id,
        'intake_client_uuid' => 'initial-funded-lock',
    ]);

    $response->assertRedirect(route('products.index'));

    $product = Product::withoutGlobalScopes()->where('user_id', $owner->id)->latest('id')->firstOrFail();
    $batch = Batch::withoutGlobalScopes()->where('product_id', $product->id)->firstOrFail();

    $this->actingAs($owner)
        ->putJson(route('batches.update', $batch), ['quantity' => 5, 'cost_price' => 60])
        ->assertStatus(422)
        ->assertJsonValidationErrors('batch');

    $this->actingAs($owner)
        ->deleteJson(route('batches.destroy', $batch))
        ->assertStatus(422)
        ->assertJsonValidationErrors('batch');
});

test('creating variant products records funding as one stock intake bill', function () {
    $owner = $this->makeOwner(['image_limit' => 10]);
    $supplier = $this->makeSupplier($owner);

    $response = $this->actingAs($owner)->post(route('products.store'), [
        'name' => 'T-Shirt',
        'has_variants' => 1,
        'cost_price' => 10,
        'selling_price' => 20,
        'funding_mode' => 'partial',
        'funding_supplier_id' => $supplier->id,
        'funding_payment_method' => 'cash',
        'funding_paid_amount' => 15,
        'variants' => [
            ['name' => 'Small', 'quantity' => 1, 'barcode' => 'TS-S'],
            ['name' => 'Large', 'quantity' => 2, 'barcode' => 'TS-L'],
        ],
    ]);

    $response->assertRedirect(route('products.index'));

    $bill = PurchaseBill::withoutGlobalScopes()->latest('id')->firstOrFail();
    $batches = Batch::withoutGlobalScopes()->orderBy('id')->get();

    expect($bill->products)->toHaveCount(2)
        ->and((float) $supplier->fresh()->balance)->toBe(15.0)
        ->and((float) SupplierPayment::withoutGlobalScopes()->sum('amount'))->toBe(15.0)
        ->and($batches)->toHaveCount(2)
        ->and($batches->pluck('purchase_bill_id')->unique()->values()->all())->toBe([$bill->id]);
});

test('creating a funded product is idempotent for repeated intake client uuids', function () {
    $owner = $this->makeOwner(['image_limit' => 10]);
    $supplier = $this->makeSupplier($owner);

    $payload = [
        'name' => 'Router',
        'barcode' => 'RT-01',
        'quantity' => 3,
        'cost_price' => 20,
        'selling_price' => 35,
        'funding_mode' => 'partial',
        'funding_supplier_id' => $supplier->id,
        'funding_payment_method' => 'cash',
        'funding_paid_amount' => 10,
        'intake_client_uuid' => 'product-repeat-1',
    ];

    $this->actingAs($owner)->post(route('products.store'), $payload)->assertRedirect(route('products.index'));

    $product = Product::withoutGlobalScopes()->where('user_id', $owner->id)->latest('id')->firstOrFail();

    $this->actingAs($owner)->post(route('products.store'), $payload)
        ->assertRedirect(route('products.edit', $product));

    expect(Product::withoutGlobalScopes()->where('user_id', $owner->id)->count())->toBe(1)
        ->and(PurchaseBill::withoutGlobalScopes()->count())->toBe(1)
        ->and(SupplierPayment::withoutGlobalScopes()->count())->toBe(1)
        ->and(Batch::withoutGlobalScopes()->where('product_id', $product->id)->where('intake_client_uuid', 'product-repeat-1')->count())->toBe(1);
});

test('adding funded variants is idempotent for repeated intake client uuids', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $group = ProductVariantGroup::create([
        'name' => 'Sneaker',
        'user_id' => $owner->id,
    ]);
    $product = $this->makeProduct($owner, [
        'name' => 'Sneaker - Base',
        'variant_group_id' => $group->id,
        'variant_name' => 'Base',
        'cost_price' => 15,
        'selling_price' => 25,
    ]);

    $payload = [
        'new_variants' => [
            ['name' => 'Blue', 'quantity' => 1, 'barcode' => 'SN-BL'],
            ['name' => 'Red', 'quantity' => 2, 'barcode' => 'SN-RD'],
        ],
        'funding_mode' => 'credit',
        'funding_supplier_id' => $supplier->id,
        'intake_client_uuid' => 'variant-repeat-1',
    ];

    $this->actingAs($owner)->post(route('products.addVariants', $product), $payload)
        ->assertRedirect(route('products.edit', $product));

    $duplicateProduct = Product::withoutGlobalScopes()
        ->where('user_id', $owner->id)
        ->where('variant_name', 'Blue')
        ->firstOrFail();

    $this->actingAs($owner)->post(route('products.addVariants', $product), $payload)
        ->assertRedirect(route('products.edit', $duplicateProduct));

    expect(Product::withoutGlobalScopes()->where('user_id', $owner->id)->count())->toBe(3)
        ->and(PurchaseBill::withoutGlobalScopes()->count())->toBe(1)
        ->and(Batch::withoutGlobalScopes()->whereIn('intake_client_uuid', ['variant-repeat-1', 'variant-repeat-1#2'])->count())->toBe(2);
});

test('product creation rejects unsupported funding payment methods', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);

    $this->actingAs($owner)->post(route('products.store'), [
        'name' => 'Speaker',
        'barcode' => 'SP-01',
        'quantity' => 1,
        'cost_price' => 12,
        'selling_price' => 20,
        'funding_mode' => 'paid',
        'funding_supplier_id' => $supplier->id,
        'funding_payment_method' => 'other',
        'intake_client_uuid' => 'invalid-method-product',
    ])->assertSessionHasErrors('funding_payment_method');
});

test('batch creation rejects unsupported funding payment methods', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner);

    $this->actingAs($owner)->postJson(route('batches.store'), [
        'product_id' => $product->id,
        'quantity' => 1,
        'cost_price' => 12,
        'funding_mode' => 'paid',
        'funding_supplier_id' => $supplier->id,
        'funding_payment_method' => 'other',
        'intake_client_uuid' => 'invalid-method-batch',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('funding_payment_method');
});

test('employees add stock against the owner and can pay immediately', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['edit_products']);
    $product = $this->makeProduct($owner, ['quantity' => 2, 'cost_price' => 10]);

    $response = $this->actingAs($employee)->postJson(route('products.add-quantity', $product), [
        'amount' => 3,
        'cost_price' => 20,
        'funding_mode' => 'paid',
        'funding_payment_method' => 'cash',
    ]);

    $response->assertOk();

    $bill = PurchaseBill::withoutGlobalScopes()->latest('id')->firstOrFail();

    expect((float) $product->fresh()->quantity)->toBe(5.0)
        ->and($bill->user_id)->toBe($owner->id)
        ->and($bill->created_by)->toBe($employee->id);
});

test('batch creation validates supplier requirements and rolls back stock changes on error', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner, ['quantity' => 4, 'cost_price' => 8]);

    $response = $this->actingAs($owner)->postJson(route('batches.store'), [
        'product_id' => $product->id,
        'quantity' => 5,
        'cost_price' => 11,
        'funding_mode' => 'credit',
    ]);

    $response->assertStatus(422);

    expect((float) $product->fresh()->quantity)->toBe(4.0)
        ->and(Batch::withoutGlobalScopes()->where('product_id', $product->id)->count())->toBe(0);
});

test('foreign suppliers are rejected when adding stock batches', function () {
    $owner = $this->makeOwner();
    $other = $this->makeOwner();
    $foreignSupplier = $this->makeSupplier($other);
    $product = $this->makeProduct($owner);

    $response = $this->actingAs($owner)->postJson(route('batches.store'), [
        'product_id' => $product->id,
        'quantity' => 1,
        'cost_price' => 2,
        'funding_mode' => 'credit',
        'funding_supplier_id' => $foreignSupplier->id,
    ]);

    $response->assertStatus(422);
});

test('funded batches cannot be edited or deleted', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner, ['quantity' => 0, 'cost_price' => 0]);

    $createResponse = $this->actingAs($owner)->postJson(route('batches.store'), [
        'product_id' => $product->id,
        'quantity' => 2,
        'cost_price' => 15,
        'funding_mode' => 'credit',
        'funding_supplier_id' => $supplier->id,
        'intake_client_uuid' => 'batch-funded-lock',
    ]);

    $createResponse->assertOk();
    $batch = Batch::withoutGlobalScopes()->where('product_id', $product->id)->firstOrFail();

    $this->actingAs($owner)
        ->putJson(route('batches.update', $batch), ['quantity' => 5, 'cost_price' => 18])
        ->assertStatus(422)
        ->assertJsonValidationErrors('batch');

    $this->actingAs($owner)
        ->deleteJson(route('batches.destroy', $batch))
        ->assertStatus(422)
        ->assertJsonValidationErrors('batch');
});

test('unfunded batches can still be edited and deleted', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner, ['quantity' => 3, 'cost_price' => 10]);
    $batch = Batch::create([
        'product_id' => $product->id,
        'quantity' => 3,
        'cost_price' => 10,
        'user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->putJson(route('batches.update', $batch), ['quantity' => 5, 'cost_price' => 12])
        ->assertOk();

    expect((float) $product->fresh()->quantity)->toBe(5.0);

    $this->actingAs($owner)
        ->deleteJson(route('batches.destroy', $batch->fresh()))
        ->assertOk();

    expect(Batch::withoutGlobalScopes()->find($batch->id))->toBeNull();
});

test('adding stock is idempotent for repeated intake client uuids', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner, ['quantity' => 2, 'cost_price' => 10]);

    $payload = [
        'amount' => 3,
        'cost_price' => 20,
        'funding_mode' => 'none',
        'intake_client_uuid' => 'stock-repeat-1',
    ];

    $this->actingAs($owner)->postJson(route('products.add-quantity', $product), $payload)->assertOk();
    $this->actingAs($owner)->postJson(route('products.add-quantity', $product), $payload)->assertOk();

    expect((float) $product->fresh()->quantity)->toBe(5.0)
        ->and(Batch::withoutGlobalScopes()->where('product_id', $product->id)->where('intake_client_uuid', 'stock-repeat-1')->count())->toBe(1);
});

test('batch creation is idempotent for repeated intake client uuids', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner, ['quantity' => 1, 'cost_price' => 5]);

    $payload = [
        'product_id' => $product->id,
        'quantity' => 4,
        'cost_price' => 11,
        'funding_mode' => 'none',
        'intake_client_uuid' => 'batch-repeat-1',
    ];

    $this->actingAs($owner)->postJson(route('batches.store'), $payload)->assertOk();
    $this->actingAs($owner)->postJson(route('batches.store'), $payload)->assertOk();

    expect((float) $product->fresh()->quantity)->toBe(5.0)
        ->and(Batch::withoutGlobalScopes()->where('product_id', $product->id)->where('intake_client_uuid', 'batch-repeat-1')->count())->toBe(1);
});
