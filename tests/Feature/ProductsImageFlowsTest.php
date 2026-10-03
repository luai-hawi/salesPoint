<?php

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Builds;

uses(Builds::class);

beforeEach(function () {
    Storage::fake('public');
});

test('owner can create a product with a processed image', function () {
    $owner = $this->makeOwner(['image_limit' => 5]);

    $response = $this->actingAs($owner)->post(route('products.store'), [
        'name' => 'Camera',
        'category' => 'Devices',
        'barcode' => 'CAM-100',
        'quantity' => 0,
        'cost_price' => 120,
        'selling_price' => 160,
        'funding_mode' => 'none',
        'pictures' => [
            UploadedFile::fake()->image('camera.jpg', 2500, 1700),
        ],
    ]);

    $response->assertRedirect(route('products.index'));

    $product = Product::withoutGlobalScopes()->where('user_id', $owner->id)->latest('id')->firstOrFail();
    $pictures = json_decode($product->pictures, true);

    expect($pictures)->toHaveCount(1);
    Storage::disk('public')->assertExists($pictures[0]);

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($pictures[0]));
    expect(max($width, $height))->toBeLessThanOrEqual(1600);
});

test('image limit counts unique product files', function () {
    $owner = $this->makeOwner(['image_limit' => 2]);

    $sharedFile = UploadedFile::fake()->image('shared.jpg', 100, 100);
    Storage::disk('public')->put('products/shared.jpg', file_get_contents($sharedFile->getRealPath()));

    Product::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'name' => 'One',
        'barcode' => 'ONE-1',
        'quantity' => 0,
        'cost_price' => 10,
        'selling_price' => 20,
        'pictures' => json_encode(['products/shared.jpg']),
    ]);

    Product::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'name' => 'Two',
        'barcode' => 'TWO-2',
        'quantity' => 0,
        'cost_price' => 10,
        'selling_price' => 20,
        'pictures' => json_encode(['products/shared.jpg']),
    ]);

    $response = $this->actingAs($owner)->post(route('products.store'), [
        'name' => 'Third',
        'barcode' => 'THREE-3',
        'quantity' => 0,
        'cost_price' => 11,
        'selling_price' => 21,
        'funding_mode' => 'none',
        'pictures' => [
            UploadedFile::fake()->image('third.jpg', 1200, 1200),
        ],
    ]);

    $response->assertRedirect(route('products.index'));
});

test('update can reorder kept pictures and replace removed ones', function () {
    $owner = $this->makeOwner(['image_limit' => 5]);
    $product = $this->makeProduct($owner, [
        'pictures' => json_encode(['products/one.jpg', 'products/two.jpg']),
    ]);

    $oneImage = UploadedFile::fake()->image('one.jpg', 100, 100);
    $twoImage = UploadedFile::fake()->image('two.jpg', 100, 100);
    Storage::disk('public')->put('products/one.jpg', file_get_contents($oneImage->getRealPath()));
    Storage::disk('public')->put('products/two.jpg', file_get_contents($twoImage->getRealPath()));

    $response = $this->actingAs($owner)->put(route('products.update', $product), [
        'name' => $product->name,
        'category' => $product->category,
        'barcode' => $product->barcode,
        'cost_price' => $product->cost_price,
        'selling_price' => $product->selling_price,
        'low_stock_threshold' => 3,
        'funding_mode' => 'none',
        'existing_pictures' => ['products/two.jpg'],
        'picture_order' => ['existing:products/two.jpg', 'upload:new-1'],
        'new_picture_tokens' => ['new-1'],
        'pictures' => [
            UploadedFile::fake()->image('replacement.jpg', 1600, 900),
        ],
    ]);

    $response->assertRedirect(route('products.edit', $product));

    $product->refresh();
    $pictures = json_decode($product->pictures, true);

    expect($pictures[0])->toBe('products/two.jpg')
        ->and($pictures)->toHaveCount(2);

    Storage::disk('public')->assertMissing('products/one.jpg');
    Storage::disk('public')->assertExists('products/two.jpg');
    Storage::disk('public')->assertExists($pictures[1]);
});

test('updating a product does not delete a shared picture still used by another product', function () {
    $owner = $this->makeOwner(['image_limit' => 5]);
    $first = $this->makeProduct($owner, ['pictures' => json_encode(['products/shared.jpg'])]);
    $second = $this->makeProduct($owner, ['pictures' => json_encode(['products/shared.jpg'])]);

    $shared = UploadedFile::fake()->image('shared.jpg', 200, 200);
    Storage::disk('public')->put('products/shared.jpg', file_get_contents($shared->getRealPath()));

    $response = $this->actingAs($owner)->put(route('products.update', $first), [
        'name' => $first->name,
        'category' => $first->category,
        'barcode' => $first->barcode,
        'cost_price' => $first->cost_price,
        'selling_price' => $first->selling_price,
        'low_stock_threshold' => 4,
        'funding_mode' => 'none',
        'existing_pictures' => [],
        'picture_order' => [],
        'new_picture_tokens' => [],
    ]);

    $response->assertRedirect(route('products.edit', $first));

    Storage::disk('public')->assertExists('products/shared.jpg');
    expect($second->fresh()->pictures)->toBe(json_encode(['products/shared.jpg']));
});
