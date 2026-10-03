<?php

use App\Exceptions\EntryLimitReached;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\Admin\AccountStatus;
use App\Services\Admin\PlatformSettings;
use App\Services\Admin\ShopPurger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Builds;

uses(Builds::class);

test('account status service classifies key shop states', function () {
    $service = app(AccountStatus::class);

    $active = $this->makeOwner(['license_expires_at' => now()->addDays(90)->toDateString()]);
    $dueSoon = $this->makeOwner(['license_expires_at' => now()->addDays(5)->toDateString()]);
    $overdue = $this->makeOwner(['license_expires_at' => now()->subDays(2)->toDateString()]);
    $trialEnding = $this->makeUser(['role' => 'shop_owner', 'account_type' => 'temp', 'temp_expires_at' => now()->addDays(3)->toDateString()]);
    $trialEnded = $this->makeUser(['role' => 'shop_owner', 'account_type' => 'temp', 'temp_expires_at' => now()->subDay()->toDateString()]);
    $disabled = $this->makeUser(['role' => 'disabled', 'disabled_from_role' => 'restaurant']);
    $missing = $this->makeOwner(['license_expires_at' => null]);

    expect($service->describe($active)['key'])->toBe('active')
        ->and($service->describe($dueSoon)['key'])->toBe('payment_due_soon')
        ->and($service->describe($overdue)['key'])->toBe('payment_overdue')
        ->and($service->describe($trialEnding)['key'])->toBe('trial_ending')
        ->and($service->describe($trialEnded)['key'])->toBe('trial_ended')
        ->and($service->describe($disabled)['key'])->toBe('disabled')
        ->and($service->describe($missing)['key'])->toBe('license_missing');
});

test('admin can save private note by ajax', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeOwner();

    $this->actingAs($admin)
        ->putJson(route('admin.shop-owners.note', $owner), ['admin_notes' => 'Private agreement'])
        ->assertOk()
        ->assertJson(['message' => __('admin.messages.note_saved')]);

    expect($owner->fresh()->admin_notes)->toBe('Private agreement');
});

test('recording and deleting subscription payment recalculates expiry', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeOwner([
        'license_expires_at' => now()->subDays(30)->toDateString(),
        'subscription_cost' => 120,
        'subscription_currency' => 'USD',
    ]);

    $this->actingAs($admin)->withSession([
        'admin.payment_tokens.' . $owner->id => ['token-1' => true],
    ])
        ->post(route('admin.shop-owners.mark-paid', $owner), [
            'months' => 2,
            'amount' => 120,
            'currency' => 'USD',
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
            'continue_mode' => 'auto',
            'idempotency_key' => 'token-1',
        ])
        ->assertRedirect();

    $owner->refresh();
    expect($owner->subscriptionPayments)->toHaveCount(1)
        ->and($owner->license_expires_at?->toDateString())->toBe(now()->addMonths(2)->toDateString());

    $payment = $owner->subscriptionPayments()->first();

    $this->actingAs($admin)
        ->delete(route('admin.shop-owners.payments.destroy', [$owner, $payment]))
        ->assertRedirect();

    expect($owner->fresh()->subscriptionPayments)->toHaveCount(0)
        ->and($owner->fresh()->license_expires_at)->toBeNull();
});

test('disable then enable restores original restaurant role', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeRestaurant();

    $this->actingAs($admin)->post(route('admin.shop-owners.toggle-status', $owner), [
        'disabled_reason' => 'Test',
    ])->assertRedirect();

    expect($owner->fresh()->role)->toBe('disabled')
        ->and($owner->fresh()->disabled_from_role)->toBe('restaurant');

    $this->actingAs($admin)->post(route('admin.shop-owners.toggle-status', $owner))->assertRedirect();

    expect($owner->fresh()->role)->toBe('restaurant');
});

test('employee create form accepts legacy manage aliases and stores normalized permissions', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeOwner();

    $this->actingAs($admin)->post(route('admin.employees.store'), [
        'name' => 'Cashier',
        'email' => 'cashier@example.test',
        'password' => 'password123',
        'shop_owner_id' => $owner->id,
        'permissions' => ['manage_products', 'manage_purchase_bills'],
    ])->assertRedirect();

    $employee = User::where('email', 'cashier@example.test')->firstOrFail();

    expect($employee->getPermissions())->toContain('view_products', 'create_products', 'edit_products', 'delete_products')
        ->and($employee->getPermissions())->toContain('view_purchase_bills', 'create_purchase_bills', 'edit_purchase_bills', 'delete_purchase_bills');
});

test('shop purger removes tenant data and keeps shared image files', function () {
    Storage::fake('public');

    $owner = $this->makeOwner();
    $other = $this->makeOwner();
    $sharedPath = 'products/shared.jpg';
    Storage::disk('public')->put($sharedPath, 'image');

    Product::create([
        'name' => 'P1',
        'barcode' => 'A1',
        'quantity' => 1,
        'cost_price' => 1,
        'selling_price' => 2,
        'pictures' => json_encode([$sharedPath]),
        'user_id' => $owner->id,
    ]);
    Product::create([
        'name' => 'P2',
        'barcode' => 'A2',
        'quantity' => 1,
        'cost_price' => 1,
        'selling_price' => 2,
        'pictures' => json_encode([$sharedPath]),
        'user_id' => $other->id,
    ]);
    Customer::create(['name' => 'Customer', 'phone' => '0599123456', 'balance' => 0, 'user_id' => $owner->id]);
    $employee = $this->makeEmployee($owner, ['view_products']);

    app(ShopPurger::class)->purge($owner);

    expect(User::find($employee->id))->toBeNull()
        ->and(Product::where('user_id', $owner->id)->count())->toBe(0)
        ->and(Customer::where('user_id', $owner->id)->count())->toBe(0)
        ->and(Product::where('user_id', $other->id)->count())->toBe(1);

    Storage::disk('public')->assertExists($sharedPath);
});

test('shop purger deletes only audit logs owned by the purged shop and clears reset tokens', function () {
    $owner = $this->makeOwner(['email' => 'owner-a@example.test']);
    $other = $this->makeOwner(['email' => 'owner-b@example.test']);
    $employee = $this->makeEmployee($other, ['view_products'], ['email' => 'shared-employee@example.test']);
    $employee->update(['shop_owner_id' => $owner->id]);

    ActivityLog::create([
        'owner_id' => $owner->id,
        'actor_id' => $employee->id,
        'actor_name' => $employee->name,
        'actor_role' => 'employee',
        'action' => 'updated',
        'subject_type' => 'team_account',
        'subject_id' => $employee->id,
    ]);
    ActivityLog::create([
        'owner_id' => $other->id,
        'actor_id' => $employee->id,
        'actor_name' => $employee->name,
        'actor_role' => 'employee',
        'action' => 'updated',
        'subject_type' => 'team_account',
        'subject_id' => $employee->id,
    ]);

    DB::table('password_reset_tokens')->insert([
        ['email' => $owner->email, 'token' => 'a', 'created_at' => now()],
        ['email' => $employee->email, 'token' => 'b', 'created_at' => now()],
        ['email' => $other->email, 'token' => 'c', 'created_at' => now()],
    ]);

    app(ShopPurger::class)->purge($owner);

    expect(ActivityLog::where('owner_id', $owner->id)->count())->toBe(0)
        ->and(ActivityLog::where('owner_id', $other->id)->count())->toBe(1)
        ->and(DB::table('password_reset_tokens')->where('email', $owner->email)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', $employee->email)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', $other->email)->count())->toBe(1);
});

test('impersonation keeps owner session id untouched and can be stopped', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeOwner(['session_id' => 'owner-session']);

    $this->actingAs($admin)
        ->post(route('admin.shop-owners.impersonate', $owner))
        ->assertRedirect(route('dashboard'));

    expect(session('impersonator_id'))->toBe($admin->id)
        ->and($owner->fresh()->session_id)->toBe('owner-session');

    $this->post(route('admin.impersonate.stop'))->assertRedirect(route('admin.dashboard'));
});

test('disabled and inactive shops cannot be impersonated', function () {
    $admin = $this->makeAdmin();
    $disabled = $this->makeUser(['role' => 'disabled', 'disabled_from_role' => 'shop_owner']);
    $inactive = $this->makeOwner(['is_active' => false]);

    $this->actingAs($admin)->post(route('admin.shop-owners.impersonate', $disabled))->assertSessionHasErrors();
    $this->actingAs($admin)->post(route('admin.shop-owners.impersonate', $inactive))->assertSessionHasErrors();
});

test('entry limit block mode throws and off mode allows creation', function () {
    $owner = $this->makeOwner(['entry_limit' => 1, 'entry_limit_mode' => 'block']);

    $this->actingAs($owner);
    Customer::create(['name' => 'First', 'phone' => '0599000001', 'balance' => 0]);

    expect(fn () => Customer::create(['name' => 'Second', 'phone' => '0599000002', 'balance' => 0]))
        ->toThrow(EntryLimitReached::class);

    $freeOwner = $this->makeOwner(['entry_limit' => 1, 'entry_limit_mode' => 'off']);
    $this->actingAs($freeOwner);
    Customer::create(['name' => 'Allowed', 'phone' => '0599000003', 'balance' => 0]);

    expect(Customer::withoutGlobalScopes()->where('user_id', $freeOwner->id)->count())->toBe(1);
});

test('subscription payment uses today, avoids month overflow, and is idempotent', function () {
    $this->travelTo(\Carbon\Carbon::create(2026, 1, 31, 12));

    $admin = $this->makeAdmin();
    $owner = $this->makeOwner([
        'license_expires_at' => '2026-01-31',
        'subscription_cost' => 70,
        'subscription_currency' => 'ILS',
    ]);

    $payload = [
        'months' => 1,
        'amount' => 70,
        'currency' => 'ILS',
        'method' => 'cash',
        'paid_at' => '2026-01-01',
        'continue_mode' => 'today',
        'idempotency_key' => 'once-token',
    ];

    $this->actingAs($admin)->withSession([
        'admin.payment_tokens.' . $owner->id => ['once-token' => true],
    ])->post(route('admin.shop-owners.mark-paid', $owner), $payload)->assertRedirect();

    $owner->refresh();
    expect($owner->license_expires_at?->toDateString())->toBe('2026-02-28')
        ->and($owner->subscriptionPayments()->count())->toBe(1);

    $this->actingAs($admin)->post(route('admin.shop-owners.mark-paid', $owner), $payload)->assertStatus(422);

    $this->travelBack();
});

test('non admins are forbidden from admin pages', function () {
    $owner = $this->makeOwner();

    foreach ([
        route('admin.dashboard'),
        route('admin.shop-owners.index'),
        route('admin.audit.index'),
        route('admin.settings.index'),
    ] as $url) {
        $this->actingAs($owner)->get($url)->assertForbidden();
    }
});

test('platform settings update and billing banner render for due soon owner', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeOwner(['license_expires_at' => now()->addDays(5)->toDateString()]);

    $this->actingAs($admin)->put(route('admin.settings.update'), [
        'support_whatsapp' => '970599123456',
        'support_phone' => '0599123456',
        'support_email' => 'help@example.test',
        'default_currency' => 'USD',
        'default_country_code' => '970',
        'default_trial_days' => 10,
        'default_subscription_cost' => 50,
        'due_soon_days' => 10,
        'reminder_template_ar' => 'AR',
        'reminder_template_en' => 'EN',
        'announcement_tone' => 'blue',
    ])->assertRedirect();

    app(PlatformSettings::class);
    expect(DB::table('platform_settings')->where('key', 'default_currency')->value('value'))->toBe('USD');

    $this->actingAs($owner)->get('/dashboard')->assertSee(__('admin.messages.billing_banner_due_soon', ['days' => 5]), false);
});

test('status helper keeps expiry date valid for the whole day and due windows ignore disabled accounts', function () {
    $this->travelTo(\Carbon\Carbon::create(2026, 10, 3, 12));

    $service = app(AccountStatus::class);
    $sameDay = $this->makeOwner(['license_expires_at' => '2026-10-03']);
    $disabled = $this->makeUser([
        'role' => 'disabled',
        'disabled_from_role' => 'shop_owner',
        'account_type' => 'full',
        'license_expires_at' => now()->addDays(14)->toDateString(),
    ]);

    expect($sameDay->fresh()->isLicenseExpired())->toBeFalse()
        ->and($service->describe($sameDay)['key'])->toBe('payment_due_soon')
        ->and($service->applyFilter(User::query()->whereKey($disabled->id), 'due_14')->count())->toBe(0);

    $this->travelBack();
});

test('disabled shop edit keeps disabled role and business role is read only through the form flow', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeUser([
        'role' => 'disabled',
        'disabled_from_role' => 'merchant',
        'name' => 'Merchant Shop',
        'email' => 'merchant@example.test',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.shop-owners.update', $owner), [
            'name' => 'Merchant Shop Updated',
            'owner_name' => 'Owner',
            'email' => 'merchant@example.test',
            'role' => 'disabled',
            'phone_number' => '0599000001',
            'subscription_cost' => 50,
            'subscription_currency' => 'ILS',
            'account_type' => 'full',
            'license_expires_at' => now()->addMonth()->toDateString(),
            'entry_limit_mode' => 'off',
        ])
        ->assertRedirect();

    expect($owner->fresh()->role)->toBe('disabled')
        ->and($owner->fresh()->businessRole())->toBe('merchant');
});

test('admin role is rejected by the shop owner module and member routes guard non-owner accounts', function () {
    $admin = $this->makeAdmin();
    $otherAdmin = $this->makeAdmin(['email' => 'other-admin@example.test']);

    $this->actingAs($admin)->post(route('admin.shop-owners.store'), [
        'name' => 'Bad',
        'owner_name' => 'Bad',
        'email' => 'bad@example.test',
        'password' => 'password123',
        'role' => 'admin',
        'account_type' => 'full',
        'subscription_currency' => 'ILS',
        'entry_limit_mode' => 'off',
    ])->assertSessionHasErrors('role');

    $this->actingAs($admin)->get(route('admin.shop-owners.edit', $otherAdmin))->assertNotFound();
});

test('bulk expired deletion endpoints require typed confirmation', function () {
    $admin = $this->makeAdmin();
    $expired = $this->makeUser([
        'role' => 'shop_owner',
        'account_type' => 'temp',
        'temp_expires_at' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($admin)->delete(route('admin.shop-owners.delete-expired'), [
        'user_ids' => [$expired->id],
        'confirmation' => 'wrong',
    ])->assertSessionHasErrors('confirmation');

    expect(User::find($expired->id))->not->toBeNull();
});

test('legacy users with null new columns still render admin pages', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeOwner([
        'subscription_currency' => null,
        'admin_notes' => null,
        'disabled_from_role' => null,
        'disabled_reason' => null,
        'entry_limit_mode' => null,
        'license_expires_at' => null,
    ]);

    $this->actingAs($admin)->get(route('admin.shop-owners.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.shop-owners.show', $owner))->assertOk();
});
