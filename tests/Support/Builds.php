<?php

namespace Tests\Support;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Small builders for feature tests (the stock UserFactory returns a fixed e-mail address).
 */
trait Builds
{
    private static int $buildCounter = 0;

    protected function nextSeq(): int
    {
        return ++self::$buildCounter;
    }

    protected function makeAdmin(array $attributes = []): User
    {
        return $this->makeUser(array_merge(['role' => 'admin', 'account_type' => 'full'], $attributes));
    }

    protected function makeOwner(array $attributes = []): User
    {
        return $this->makeUser(array_merge([
            'role' => 'shop_owner',
            'account_type' => 'full',
            'license_expires_at' => now()->addMonths(3)->toDateString(),
        ], $attributes));
    }

    protected function makeRestaurant(array $attributes = []): User
    {
        return $this->makeOwner(array_merge(['role' => 'restaurant'], $attributes));
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function makeEmployee(User $owner, array $permissions = [], array $attributes = []): User
    {
        return $this->makeUser(array_merge([
            'role' => 'employee',
            'shop_owner_id' => $owner->id,
            'permissions' => $permissions,
        ], $attributes));
    }

    protected function makeUser(array $attributes = []): User
    {
        $n = $this->nextSeq();

        $user = new User(array_merge([
            'name' => 'User ' . $n,
            'email' => "user{$n}@example.test",
            'password' => 'password',
            'role' => 'shop_owner',
        ], $attributes));
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    protected function makeProduct(User $owner, array $attributes = []): Product
    {
        $n = $this->nextSeq();

        $product = new Product(array_merge([
            'name' => 'Product ' . $n,
            'barcode' => 'BC' . str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'quantity' => 10,
            'cost_price' => 5,
            'selling_price' => 10,
            'is_active' => true,
        ], $attributes));
        $product->user_id = $owner->id;
        $product->save();

        return $product;
    }

    protected function makeCustomer(User $owner, array $attributes = []): Customer
    {
        $n = $this->nextSeq();

        $customer = new Customer(array_merge([
            'name' => 'Customer ' . $n,
            'phone' => '0599' . str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'balance' => 0,
        ], $attributes));
        $customer->user_id = $owner->id;
        $customer->save();

        return $customer;
    }

    protected function makeSupplier(User $owner, array $attributes = []): Supplier
    {
        $n = $this->nextSeq();

        $supplier = new Supplier(array_merge([
            'name' => 'Supplier ' . $n,
            'phone' => '0598' . str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'balance' => 0,
        ], $attributes));
        $supplier->user_id = $owner->id;
        $supplier->save();

        return $supplier;
    }
}
