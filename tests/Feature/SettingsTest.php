<?php

use App\Models\Sale;
use App\Models\Tag;
use App\Models\User;
use App\Models\ActivityLog;
use Tests\Support\Builds;

uses(Builds::class);

test('settings page renders for owner employee with permission and restaurant', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['manage_settings']);
    $restaurant = $this->makeRestaurant();

    $this->actingAs($owner)->get('/settings')->assertOk();
    $this->actingAs($employee)->get('/settings')->assertOk();
    $this->actingAs($restaurant)->get('/settings')->assertOk();
});

test('product settings validation still requires deactivation after warning', function () {
    $owner = $this->makeOwner();

    $response = $this->actingAs($owner)->from('/settings')->post(route('settings.update-product'), [
        'product_warning_period' => 6,
        'product_deactivation_period' => 4,
    ]);

    $response->assertRedirect('/settings');
    $response->assertSessionHasErrors('product_deactivation_period');
});

test('visibility settings are saved and preserve pos slim mode', function () {
    $owner = $this->makeOwner([
        'visibility_settings' => ['pos_slim_mode' => true],
    ]);

    $response = $this->actingAs($owner)->post(route('settings.update-visibility'), [
        'show_bills_total_sales' => '1',
        'show_bills_total_profit' => '0',
        'show_bills_count' => '1',
        'show_bill_total_value' => '0',
        'show_bill_profit_column' => '1',
        'show_dashboard_total_sales' => '0',
        'show_product_cost_price' => '1',
    ]);

    $response->assertRedirect();

    $settings = $owner->fresh()->visibility_settings;
    expect($settings['pos_slim_mode'])->toBeTrue()
        ->and($settings['show_bills_total_profit'])->toBeFalse()
        ->and($settings['show_product_cost_price'])->toBeTrue();
});

test('owner can update employee visibility settings but admin cannot', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_products']);
    $admin = $this->makeAdmin();

    $this->actingAs($owner)->post(route('settings.employee-visibility', $employee), [
        'show_bills_total_sales' => '0',
        'show_bills_total_profit' => '0',
        'show_bills_count' => '0',
        'show_bill_total_value' => '0',
        'show_bill_profit_column' => '0',
        'show_dashboard_total_sales' => '0',
        'show_product_cost_price' => '0',
    ])->assertRedirect();

    expect($employee->fresh()->getVisibilitySetting('show_bills_total_sales'))->toBeFalse();

    $this->actingAs($admin)->post(route('settings.employee-visibility', $employee), [
        'show_bills_total_sales' => '1',
    ])->assertForbidden();
});

test('owner profile deletion is blocked but employee can still delete their own account', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_products']);

    $this->actingAs($owner)->delete('/profile', [
        'password' => 'password',
    ])->assertRedirect(route('profile.edit'));

    expect(User::find($owner->id))->not->toBeNull();

    $this->actingAs($employee)->delete('/profile', [
        'password' => 'password',
    ])->assertRedirect('/');

    expect(User::find($employee->id))->toBeNull();
    expect(ActivityLog::query()
        ->where('subject_type', 'team_account')
        ->where('action', 'deleted')
        ->where('subject_label', $employee->name)
        ->exists())->toBeTrue();
});

test('tags deletion is tenant isolated', function () {
    $owner = $this->makeOwner();
    $otherOwner = $this->makeOwner();
    $foreignTag = Tag::create([
        'name' => 'Foreign',
        'price' => 1,
        'user_id' => $otherOwner->id,
    ]);

    $this->actingAs($owner)->delete(route('tags.destroy', $foreignTag))->assertNotFound();
});

test('sales creation rejects products from another tenant', function () {
    $owner = $this->makeOwner();
    $otherOwner = $this->makeOwner();
    $foreignProduct = $this->makeProduct($otherOwner);

    $response = $this->actingAs($owner)->from('/sales')->post(route('sales.store'), [
        'name' => 'October Offer',
        'is_active' => '1',
        'rules' => [
            [
                'product_id' => $foreignProduct->id,
                'discount_type' => 'amount',
                'discount_value' => 1,
                'applies_every_n' => 1,
            ],
        ],
    ]);

    $response->assertRedirect('/sales');
    $response->assertSessionHasErrors('rules');
    expect(Sale::query()->where('user_id', $owner->id)->count())->toBe(0);
});

test('legacy manage products permission still authorizes product workflows and expenses navigation', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['manage_products', 'manage_expenses']);
    $product = $this->makeProduct($owner);

    expect($employee->hasPermission('view_products'))->toBeTrue()
        ->and($employee->hasPermission('create_products'))->toBeTrue()
        ->and($employee->hasPermission('edit_products'))->toBeTrue()
        ->and($employee->hasPermission('delete_products'))->toBeTrue()
        ->and($employee->hasPermission('view_expenses'))->toBeTrue()
        ->and($employee->hasPermission('create_expenses'))->toBeTrue()
        ->and($employee->hasPermission('edit_expenses'))->toBeTrue()
        ->and($employee->hasPermission('delete_expenses'))->toBeTrue();

    $this->actingAs($employee)->get('/products')->assertOk();
    $this->actingAs($employee)->get('/products/create')->assertOk();
    $this->actingAs($employee)->get(route('products.edit', $product))->assertOk();
    $this->actingAs($employee)->post(route('products.toggle-active', $product))->assertRedirect();
    expect($product->fresh()->is_active)->toBeFalse();
    $this->actingAs($employee)->get('/products')->assertSee(route('shopowner.expenses.index'), false);
});

test('read only employees do not see forbidden sales or tags controls', function () {
    $owner = $this->makeOwner();
    $tag = Tag::create([
        'name' => 'Read only tag',
        'price' => 2,
        'user_id' => $owner->id,
    ]);
    $product = $this->makeProduct($owner);
    $sale = Sale::create([
        'user_id' => $owner->id,
        'name' => 'Read only sale',
        'is_active' => true,
    ]);
    $sale->rules()->create([
        'product_id' => $product->id,
        'discount_type' => 'amount',
        'discount_value' => 1,
        'applies_every_n' => 1,
    ]);
    $employee = $this->makeEmployee($owner, ['view_tags', 'view_sales']);

    $salesResponse = $this->actingAs($employee)->get('/sales');
    $salesResponse->assertOk();
    $salesResponse->assertDontSee(__('sales.New Sale'));
    $salesResponse->assertDontSee((string) route('sales.destroy', $sale), false);

    $tagsResponse = $this->actingAs($employee)->get('/tags');
    $tagsResponse->assertOk();
    $tagsResponse->assertDontSee(__('messages.Add Tag'));
    $tagsResponse->assertDontSee((string) route('tags.destroy', $tag), false);
});
