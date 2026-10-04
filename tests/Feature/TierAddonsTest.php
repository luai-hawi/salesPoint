<?php

use App\Http\Middleware\StaffPortalAuth;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Support\FeatureCatalog;
use Illuminate\Support\Facades\Route;
use Tests\Support\Builds;

uses(Builds::class);

test('admin can create a shop with add-ons and re-enable every blocked feature', function () {
    $admin = $this->makeAdmin();
    $data = [
        'name' => 'Tier shop',
        'email' => 'tier-shop@example.test',
        'password' => 'password123',
        'role' => 'shop_owner',
        'account_type' => 'full',
        'subscription_currency' => 'ILS',
        'blocked_features' => FeatureCatalog::KEYS,
    ];

    $this->actingAs($admin)->post(route('admin.shop-owners.store'), $data)
        ->assertSessionHasNoErrors()->assertRedirect();

    $owner = \App\Models\User::where('email', $data['email'])->firstOrFail();
    expect($owner->blocked_features)->toBe(FeatureCatalog::KEYS);

    $data['blocked_features'] = [''];
    $this->put(route('admin.shop-owners.update', $owner), $data)
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($owner->fresh()->blocked_features)->toBe([]);

    $data['blocked_features'] = ['not-a-feature'];
    $this->putJson(route('admin.shop-owners.update', $owner), $data)
        ->assertUnprocessable()->assertJsonValidationErrors('blocked_features.0');
});

test('all HR and report module routes have their subscription gate', function () {
    foreach (Route::getRoutes() as $route) {
        $name = $route->getName() ?? '';
        $feature = match (true) {
            str_starts_with($name, 'shopowner.employees.'),
            str_starts_with($name, 'shopowner.attendance.'),
            str_starts_with($name, 'shopowner.payroll.') => 'hr',
            str_starts_with($name, 'reports.'),
            str_starts_with($name, 'restaurant.insights.'),
            in_array($name, ['dashboard.export-data', 'dashboard.financial.print-report'], true) => 'reports',
            str_starts_with($name, 'shopowner.activity.'),
            str_starts_with($name, 'finance.team-summary.') => 'team_activity',
            default => null,
        };
        if ($feature) {
            expect($route->gatherMiddleware())->toContain('tier.feature:'.$feature);
        }
    }
});

test('blocked add-ons deny owners and program employees with matching permissions', function (string $feature, string $routeName) {
    $owner = $this->makeOwner(['blocked_features' => [$feature]]);
    $employee = $this->makeEmployee($owner, ['manage_employees', 'view_reports', 'view_financial', 'view_team_activity']);

    foreach ([$owner, $employee] as $user) {
        $this->actingAs($user)->getJson(route($routeName))
            ->assertForbidden()->assertJson(['error' => __('messages.tier_feature_blocked')]);
    }
})->with([
    ['hr', 'shopowner.employees.index'],
    ['hr', 'shopowner.attendance.index'],
    ['hr', 'shopowner.attendance.settings'],
    ['hr', 'shopowner.attendance.export'],
    ['hr', 'shopowner.payroll.index'],
    ['hr', 'shopowner.payroll.export'],
    ['reports', 'reports.index'],
    ['reports', 'reports.generate'],
    ['reports', 'reports.print'],
    ['reports', 'reports.export'],
    ['reports', 'reports.customer-bill-details'],
    ['reports', 'reports.customer-bill-details.data'],
    ['reports', 'dashboard.export-data'],
    ['reports', 'dashboard.financial.print-report'],
    ['team_activity', 'shopowner.activity.index'],
    ['team_activity', 'shopowner.activity.export'],
    ['team_activity', 'finance.team-summary.index'],
]);

test('HR blocking prevents management writes and employee payments without blocking customer payments', function () {
    $owner = $this->makeOwner(['blocked_features' => ['hr']]);
    $worker = Employee::create(['shop_owner_id' => $owner->id, 'name' => 'Worker', 'monthly_salary' => 100]);
    $this->actingAs($owner);

    $this->postJson(route('shopowner.employees.store'), [])->assertForbidden();
    $this->putJson(route('shopowner.attendance.settings.update'), ['enabled' => true])->assertForbidden();
    $this->postJson(route('shopowner.payroll.pay', $worker), [])->assertForbidden();
    $this->getJson(route('api.employees.search'))->assertForbidden();
    $this->postJson(route('payments-receipts.store'), [
        'transaction_type' => 'payment',
        'entity_type' => 'employee',
        'entity_id' => $worker->id,
        'amount' => 50,
        'payment_date' => now()->toDateString(),
        'type' => 'cash',
    ])->assertForbidden();

    expect($worker->payments()->count())->toBe(0);
    $this->getJson(route('api.customers.search'))->assertOk();
});

test('HR blocking closes the separate staff portal including writes and biometric routes', function () {
    $owner = $this->makeOwner([
        'staff_portal_key' => 'tier-staff-key',
        'blocked_features' => ['hr'],
        'attendance_settings' => ['enabled' => true],
    ]);

    expect(StaffPortalAuth::ownerIsAvailable($owner))->toBeFalse();
    $this->get(route('staff.portal', $owner->staff_portal_key))->assertForbidden();
    $this->getJson(route('staff.state', $owner->staff_portal_key))->assertForbidden();
    $this->postJson(route('staff.login', $owner->staff_portal_key), [])->assertForbidden();
    $this->postJson(route('staff.punch', $owner->staff_portal_key), [])->assertForbidden();
    $this->postJson(route('staff.webauthn.register.options', $owner->staff_portal_key), [])->assertForbidden();
});

test('cross-module reports cannot bypass HR or team activity blocks', function (string $feature, string $type) {
    $owner = $this->makeOwner(['blocked_features' => [$feature]]);
    $this->actingAs($owner);
    foreach (['reports.generate', 'reports.print', 'reports.export'] as $routeName) {
        $this->getJson(route($routeName, ['type' => $type]))->assertForbidden();
    }
    $this->get(route('reports.index'))->assertOk()->assertDontSee('value="'.$type.'"', false);
    $this->getJson(route('reports.generate', ['type' => 'sale_bills']))->assertOk();
})->with([
    ['hr', 'employee_payments'],
    ['team_activity', 'employee_work'],
]);

test('existing shops retain add-ons and blocked navigation links disappear', function () {
    $owner = $this->makeOwner();
    foreach (['hr', 'reports', 'team_activity'] as $feature) {
        expect($owner->canAccessFeature($feature))->toBeTrue();
    }
    $this->actingAs($owner)->get(route('shopowner.attendance.index'))->assertOk();
    $this->get(route('reports.index'))->assertOk();
    $this->get(route('shopowner.activity.index'))->assertOk();

    $owner->update(['blocked_features' => ['hr', 'reports', 'team_activity']]);
    $this->actingAs($owner)->get(route('products.index'))->assertOk()
        ->assertDontSee('href="'.route('shopowner.employees.index').'"', false)
        ->assertDontSee('href="'.route('shopowner.attendance.index').'"', false)
        ->assertDontSee('href="'.route('shopowner.payroll.index').'"', false)
        ->assertDontSee('href="'.route('reports.index').'"', false)
        ->assertDontSee('href="'.route('shopowner.activity.index').'"', false);
});

test('restaurant analytics are an optional reporting add-on', function () {
    $owner = $this->makeRestaurant(['blocked_features' => ['reports']]);
    $this->actingAs($owner)->getJson(route('restaurant.insights.index'))->assertForbidden();
    $this->getJson(route('restaurant.insights.export'))->assertForbidden();
});

test('admin form exposes every negotiated add-on', function () {
    $owner = $this->makeOwner(['blocked_features' => ['hr']]);
    $response = $this->actingAs($this->makeAdmin())->get(route('admin.shop-owners.edit', $owner))->assertOk();
    foreach (FeatureCatalog::KEYS as $feature) {
        $response->assertSee('value="'.$feature.'"', false)
            ->assertSee(__('admin.features.'.$feature));
    }
});

test('financial views and workbook omit blocked HR and team detail but retain bookkeeping totals', function (array $blocked) {
    $owner = $this->makeOwner(['blocked_features' => $blocked]);
    $worker = Employee::create(['shop_owner_id' => $owner->id, 'name' => 'Unique payroll worker', 'monthly_salary' => 100]);
    EmployeePayment::create([
        'employee_id' => $worker->id,
        'amount' => 25,
        'payment_date' => now()->toDateString(),
        'type' => 'cash',
    ]);
    $this->actingAs($owner);

    foreach (['dashboard.financial', 'dashboard.financial.print-report'] as $routeName) {
        $response = $this->get(route($routeName))->assertOk();
        if (in_array('hr', $blocked, true)) {
            $response->assertDontSee(__('finance.restored.staff_breakdown'));
        } else {
            $response->assertSee(__('finance.restored.staff_breakdown'))->assertSee($worker->name);
        }
        if (in_array('team_activity', $blocked, true)) {
            $response->assertDontSee(__('finance.dashboard.team_summary'));
        } else {
            $response->assertSee(__('finance.dashboard.team_summary'));
        }
        $response->assertSee(__('finance.dashboard.staff_payments'));
    }

    $response = $this->get(route('dashboard.export-data'))->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'tier-workbook-');
    try {
        file_put_contents($path, $response->streamedContent());
        $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        expect(in_array('team', $workbook->getSheetNames(), true))->toBe(! in_array('team_activity', $blocked, true))
            ->and(in_array('staff_breakdown', $workbook->getSheetNames(), true))->toBe(! in_array('hr', $blocked, true));
        $workbook->disconnectWorksheets();
    } finally {
        unlink($path);
    }
})->with([
    [[]],
    [['hr', 'team_activity']],
]);
