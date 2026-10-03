<?php

use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Builds;

uses(Builds::class);

test('owner cannot create a program account even with valid permissions', function () {
    $owner = $this->makeOwner();

    $response = $this->actingAs($owner)->post(route('shopowner.team.store'), [
        'name' => 'Cashier User',
        'email' => 'cashier@example.test',
        'phone_number' => '0599000000',
        'password' => 'Pass1234!',
        'password_confirmation' => 'Pass1234!',
        'permissions' => ['manage_tags', 'view_sales'],
        'is_active' => '1',
        'show_bills_total_sales' => '1',
        'show_bills_total_profit' => '0',
        'show_bills_count' => '1',
        'show_bill_total_value' => '1',
        'show_bill_profit_column' => '0',
        'show_dashboard_total_sales' => '1',
        'show_product_cost_price' => '0',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('users', ['email' => 'cashier@example.test']);
    $this->get(route('shopowner.team.create'))->assertForbidden();
    $this->get(route('shopowner.team.index'))->assertOk()
        ->assertDontSee(route('shopowner.team.create'), false)
        ->assertSee(__('team.admin_provisioning'));
});

test('team account creation is denied before validation', function () {
    $owner = $this->makeOwner();
    $existing = $this->makeEmployee($owner);

    $response = $this->actingAs($owner)->from(route('shopowner.team.create'))->post(route('shopowner.team.store'), [
        'name' => 'Duplicate',
        'email' => $existing->email,
        'password' => 'Pass1234!',
        'password_confirmation' => 'Pass1234!',
        'permissions' => ['unknown_permission'],
    ]);

    $response->assertForbidden();
});

test('owner can copy permissions from another team member', function () {
    $owner = $this->makeOwner();
    $source = $this->makeEmployee($owner, ['manage_tags', 'create_bills']);
    $target = $this->makeEmployee($owner, ['view_products']);

    $response = $this->actingAs($owner)->post(route('shopowner.team.copy-permissions', $target), [
        'source_user_id' => $source->id,
    ]);

    $response->assertRedirect();
    expect($target->fresh()->getPermissions())->toBe(PermissionCatalog::normalize($source->getPermissions()));
});

test('owner can reset employee password and force logout', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_products'], [
        'remember_token' => 'before-reset-token',
    ]);

    DB::table('sessions')->insert([
        'id' => 'team-session-1',
        'user_id' => $employee->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);
    $employee->update(['session_id' => 'team-session-1']);

    $response = $this->actingAs($owner)->post(route('shopowner.team.reset-password', $employee), [
        'password' => 'NewPass123!',
        'password_confirmation' => 'NewPass123!',
    ]);

    $response->assertRedirect();
    expect(Hash::check('NewPass123!', $employee->fresh()->password))->toBeTrue();
    expect(DB::table('sessions')->where('id', 'team-session-1')->exists())->toBeFalse();
    expect($employee->fresh()->session_id)->toBeNull();
    expect($employee->fresh()->remember_token)->not->toBe('before-reset-token');

    auth()->logout();

    $this->post('/login', [
        'email' => $employee->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $login = $this->post('/login', [
        'email' => $employee->email,
        'password' => 'NewPass123!',
    ]);

    $login->assertRedirect(route('dashboard', absolute: false));
});

test('inline password changes revoke sessions and remember tokens', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_products'], [
        'remember_token' => 'legacy-remember-token',
    ]);

    DB::table('sessions')->insert([
        'id' => 'team-session-inline-password',
        'user_id' => $employee->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);
    $employee->update(['session_id' => 'team-session-inline-password']);

    $response = $this->actingAs($owner)->put(route('shopowner.team.update', $employee), [
        'name' => $employee->name,
        'email' => $employee->email,
        'phone_number' => $employee->phone_number,
        'password' => 'InlinePass123!',
        'password_confirmation' => 'InlinePass123!',
        'permissions' => ['view_products'],
        'is_active' => '1',
        'show_bills_total_sales' => '1',
        'show_bills_total_profit' => '1',
        'show_bills_count' => '1',
        'show_bill_total_value' => '1',
        'show_bill_profit_column' => '1',
        'show_dashboard_total_sales' => '1',
        'show_product_cost_price' => '1',
    ]);

    $response->assertRedirect();
    expect(DB::table('sessions')->where('id', 'team-session-inline-password')->exists())->toBeFalse();
    expect($employee->fresh()->session_id)->toBeNull();
    expect($employee->fresh()->remember_token)->not->toBe('legacy-remember-token');
    expect(Hash::check('InlinePass123!', $employee->fresh()->password))->toBeTrue();
});

test('suspending a team member clears sessions and blocks login and active session access', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_products'], [
        'remember_token' => 'before-suspend-token',
    ]);

    DB::table('sessions')->insert([
        'id' => 'team-session-2',
        'user_id' => $employee->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);
    $employee->update(['session_id' => 'team-session-2']);

    $response = $this->actingAs($owner)->postJson(route('shopowner.team.toggle', $employee));

    $response->assertOk()->assertJson(['is_active' => false]);
    expect($employee->fresh()->is_active)->toBeFalse();
    expect(DB::table('sessions')->where('id', 'team-session-2')->exists())->toBeFalse();
    expect($employee->fresh()->remember_token)->not->toBe('before-suspend-token');

    auth()->logout();

    $this->post('/login', [
        'email' => $employee->email,
        'password' => 'password',
    ])->assertSessionHasErrors(['email' => __('auth.account_suspended')]);

    $this->actingAs($employee->fresh())->get('/dashboard')
        ->assertRedirect(route('login'));
});

test('owner can force sign out an employee without suspending them', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_products'], [
        'remember_token' => 'remember-before-logout',
    ]);

    DB::table('sessions')->insert([
        'id' => 'team-session-3',
        'user_id' => $employee->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);
    $employee->update(['session_id' => 'team-session-3']);

    $response = $this->actingAs($owner)->post(route('shopowner.team.logout', $employee));

    $response->assertRedirect();
    expect(DB::table('sessions')->where('id', 'team-session-3')->exists())->toBeFalse();
    expect($employee->fresh()->session_id)->toBeNull();
    expect($employee->fresh()->is_active !== false)->toBeTrue();
    expect($employee->fresh()->remember_token)->not->toBe('remember-before-logout');
});

test('team routes are forbidden to employees and admins', function () {
    $owner = $this->makeOwner();
    $employeeActor = $this->makeEmployee($owner, ['manage_employees']);
    $admin = $this->makeAdmin();

    $this->actingAs($employeeActor)->get(route('shopowner.team.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('shopowner.team.index'))->assertForbidden();
});

test('owner cannot edit another owners employee account', function () {
    $owner = $this->makeOwner();
    $otherOwner = $this->makeOwner();
    $foreignEmployee = $this->makeEmployee($otherOwner);

    $this->actingAs($owner)->get(route('shopowner.team.edit', $foreignEmployee))->assertNotFound();
    $this->actingAs($owner)->delete(route('shopowner.team.destroy', $foreignEmployee))->assertNotFound();
});
