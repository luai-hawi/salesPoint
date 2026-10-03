<?php

use App\Models\Bill;
use App\Services\Admin\ShopPerformanceService;
use App\Support\ReportCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\Builds;

uses(Builds::class);

function sellLine(\App\Models\User $owner, $product, float $qty, float $price, float $cost, float $discount = 0, array $bill = []): Bill
{
    $sale = Bill::create(array_merge(['user_id' => $owner->id, 'created_by' => $owner->id, 'total_price' => $price * $qty - $discount,
        'is_damaged' => false, 'is_returned' => $qty < 0], $bill));
    $sale->products()->attach($product->id, ['quantity' => $qty, 'cost_price' => $cost, 'selling_price' => $price, 'discount' => $discount]);

    return $sale;
}

test('product sales reports count sold, returned and damaged quantities with correct profit and tenant isolation', function () {
    $owner = $this->makeOwner();
    $other = $this->makeOwner();
    $phone = $this->makeProduct($owner, ['name' => 'Phone X', 'category' => 'Mobiles', 'cost_price' => 50, 'selling_price' => 80, 'quantity' => 7]);
    $cable = $this->makeProduct($owner, ['name' => 'Cable Y', 'category' => null, 'cost_price' => 2, 'selling_price' => 5, 'quantity' => 40]);
    $idle = $this->makeProduct($owner, ['name' => 'Dusty Z', 'cost_price' => 10, 'selling_price' => 15, 'quantity' => 3]);
    $foreign = $this->makeProduct($other, ['name' => 'Foreign Q', 'cost_price' => 1, 'selling_price' => 2, 'quantity' => 1]);

    sellLine($owner, $phone, 3, 80, 50, 10);
    sellLine($owner, $phone, -1, 80, 50);
    sellLine($owner, $cable, 10, 5, 2);
    sellLine($owner, $phone, 1, 80, 50, 0, ['is_damaged' => true, 'total_price' => 0]);
    sellLine($other, $foreign, 5, 2, 1);

    $this->actingAs($owner);
    $today = \App\Support\ShopTime::today($owner->id);
    $json = fn (string $type, array $extra = []) => $this->getJson(route('reports.generate', ['type' => $type, 'from' => $today, 'to' => $today] + $extra))
        ->assertOk()->json();

    $sales = collect($json('product_sales')['rows'])->keyBy('name');
    expect($sales)->not->toHaveKey('Foreign Q')
        ->and($sales['Phone X']['qty_sold'])->toEqual(3)
        ->and($sales['Phone X']['qty_returned'])->toEqual(1)
        ->and($sales['Phone X']['net_qty'])->toEqual(2)
        ->and($sales['Phone X']['gross_sales'])->toEqual(240)
        ->and($sales['Phone X']['discounts'])->toEqual(10)
        ->and($sales['Phone X']['revenue'])->toEqual(150) // 240 - 10 discount - 80 returned
        ->and($sales['Phone X']['profit'])->toEqual(50) // (30*3 - 10) - 30
        ->and($sales['Cable Y']['profit'])->toEqual(30);

    $summary = $json('product_sales')['summary'];
    expect($summary['total'])->toEqual(200)->and($summary['profit'])->toEqual(80);

    expect(collect($json('top_selling_products')['rows'])->pluck('name')->first())->toBe('Cable Y')
        ->and(collect($json('most_profitable_products')['rows'])->pluck('name')->first())->toBe('Phone X')
        ->and(collect($json('unsold_products')['rows'])->pluck('name')->all())->toBe(['Dusty Z'])
        ->and(collect($json('returned_products')['rows'])->pluck('name')->all())->toBe(['Phone X']);

    $damaged = $json('damaged_products')['rows'];
    expect($damaged)->toHaveCount(1)->and($damaged[0]['damaged_loss'])->toEqual(50);

    $movement = $json('product_movement', ['product_id' => $phone->id])['rows'];
    expect($movement)->toHaveCount(3)
        ->and(collect($movement)->pluck('movement')->sort()->values()->all())
        ->toBe(collect([__('charts.products.movement_types.sale'), __('charts.products.movement_types.return'), __('charts.products.movement_types.damaged')])->sort()->values()->all());

    $categories = collect($json('category_sales')['rows'])->keyBy('category');
    expect($categories['Mobiles']['revenue'])->toEqual(150)
        ->and($categories[__('charts.products.uncategorized')]['revenue'])->toEqual(50)
        ->and($categories['Mobiles']['share'])->toEqual(75);

    expect(collect($json('product_sales', ['category' => '__none'])['rows'])->pluck('name')->all())->toBe(['Cable Y'])
        ->and(collect($json('product_sales', ['product_search' => 'phone'])['rows'])->pluck('name')->all())->toBe(['Phone X']);

    $this->get(route('reports.print', ['type' => 'product_sales', 'from' => $today, 'to' => $today]))->assertOk()
        ->assertSee(__('charts.products.types.product_sales'))->assertSee('Phone X')->assertSee('33.3%')->assertDontSee('Foreign Q');
    $this->get(route('reports.export', ['type' => 'product_sales', 'from' => $today, 'to' => $today]))->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

test('stock, low stock and purchase product reports use current stock and supplier purchases', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner, ['name' => 'Acme Supply']);
    $low = $this->makeProduct($owner, ['name' => 'Low Item', 'quantity' => 2, 'low_stock_threshold' => 5, 'cost_price' => 4, 'selling_price' => 6]);
    $plenty = $this->makeProduct($owner, ['name' => 'Plenty Item', 'quantity' => 50, 'low_stock_threshold' => 5, 'cost_price' => 1, 'selling_price' => 3]);
    $purchaseId = DB::table('purchase_bills')->insertGetId(['user_id' => $owner->id, 'created_by' => $owner->id, 'supplier_id' => $supplier->id,
        'purchase_date' => \App\Support\ShopTime::today($owner->id), 'total_amount' => 40, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('purchase_bill_product')->insert(['purchase_bill_id' => $purchaseId, 'product_id' => $plenty->id, 'quantity' => 40,
        'unit_cost' => 1, 'total_cost' => 40, 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($owner);
    $today = \App\Support\ShopTime::today($owner->id);
    $rows = fn (string $type) => collect($this->getJson(route('reports.generate', ['type' => $type, 'from' => $today, 'to' => $today]))->assertOk()->json('rows'));

    expect($rows('low_stock_products')->pluck('name')->all())->toBe(['Low Item'])
        ->and($rows('low_stock_products')->first()['suggested_order'])->toEqual(3);
    $valuation = $rows('product_stock_valuation')->keyBy('name');
    expect($valuation['Plenty Item']['stock_cost'])->toEqual(50)->and($valuation['Plenty Item']['potential_profit'])->toEqual(100);
    $purchases = $rows('product_purchases');
    expect($purchases)->toHaveCount(1)
        ->and($purchases->first()['qty_purchased'])->toEqual(40)
        ->and($purchases->first()['suppliers'])->toBe('Acme Supply');

    $this->get(route('reports.index'))->assertOk()->assertSee('product-reports', false)
        ->assertSee(__('charts.products.types.low_stock_products'));
    $this->get(route('products.edit', $low))->assertOk()->assertSee(__('charts.products.sales_report'));
});

test('employees without the reports permission cannot open product reports', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['use_pos']);
    $this->actingAs($employee)->get(route('reports.print', ['type' => 'product_sales']))->assertForbidden();
});

test('admin dashboard and shop page show per-shop profit that excludes damaged and returned bills', function () {
    Cache::flush();
    $admin = $this->makeAdmin();
    $shop = $this->makeOwner(['name' => 'Profit Shop Owner']);
    $product = $this->makeProduct($shop, ['name' => 'Admin Visible Product', 'cost_price' => 6, 'selling_price' => 10]);
    sellLine($shop, $product, 5, 10, 6);
    sellLine($shop, $product, -1, 10, 6);
    sellLine($shop, $product, 2, 10, 6, 0, ['is_damaged' => true, 'total_price' => 0]);

    $summary = app(ShopPerformanceService::class)->monthSummaries(null, [$shop->id])->get($shop->id);
    expect((float) $summary['sales_month'])->toEqual(50)
        ->and((float) $summary['profit_month'])->toEqual(16);

    $this->actingAs($admin)->get(route('admin.dashboard', ['refresh' => 1]))->assertOk()
        ->assertSee(__('charts.admin.shop_performance'))->assertSee('Profit Shop Owner');
    $this->get(route('admin.shop-owners.show', $shop))->assertOk()
        ->assertSee(__('charts.admin.shop_profit_title'))->assertSee('Admin Visible Product');
    $this->get(route('admin.shop-owners.index'))->assertOk()->assertSee(__('charts.admin.month_performance'));
});

test('financial dashboard charts appear for every business account type and permitted employees', function () {
    $owner = $this->makeOwner();
    $accounts = [
        $owner,
        $this->makeRestaurant(),
        $this->makeOwner(['role' => 'merchant']),
        $this->makeEmployee($owner, ['view_financial']),
    ];
    foreach ($accounts as $account) {
        $product = $this->makeProduct($account->role === 'employee' ? $owner : $account, ['cost_price' => 2, 'selling_price' => 5]);
        sellLine($account->role === 'employee' ? $owner : $account, $product, 2, 5, 2);
        $response = $this->actingAs($account)->get(route('dashboard.financial'));
        $response->assertOk();
        expect(substr_count($response->getContent(), 'data-chart='))->toBeGreaterThanOrEqual(5, $account->role);
    }
});

test('financial dashboard renders KPI cards and chart canvases', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner, ['name' => 'Chart Product', 'cost_price' => 3, 'selling_price' => 7]);
    sellLine($owner, $product, 4, 7, 3);
    $this->actingAs($owner)->get(route('dashboard.financial'))->assertOk()
        ->assertSee('data-chart', false)
        ->assertSee(__('charts.finance.where_money_went'))
        ->assertSee(__('charts.finance.top_products'))
        ->assertSee(__('charts.vs_previous'));
});

test('every product report type is registered in the catalog with translated labels', function () {
    foreach (\App\Services\Reports\ProductReportService::TYPES as $type) {
        expect(ReportCatalog::isProductReport($type))->toBeTrue();
        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            expect(__('charts.products.types.'.$type))->not->toStartWith('charts.');
            foreach (array_keys(ReportCatalog::rows()[$type]['columns']) as $column) {
                expect(__('charts.products.columns.'.$column))->not->toStartWith('charts.');
            }
        }
    }
});
