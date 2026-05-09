<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\PurchaseBill;
use App\Models\CustomerPayment;
use App\Models\Tag;
use App\Models\Batch;
use App\Models\ProductBarcode;
use App\Models\ProductVariantGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ShopOwnerController extends Controller
{
    /**
     * Display a listing of all users (shop owners and admins).
     */
    public function index()
    {
        $users = User::whereIn('role', ['shop_owner', 'admin', 'disabled', 'restaurant', 'merchant'])
            ->withCount('employees')
            ->latest()
            ->get();

        return view('admin.shop-owners.index', compact('users'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        return view('admin.shop-owners.create');
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:shop_owner,admin,restaurant,merchant',
            'phone_number' => 'nullable|string|max:20',
            'subscription_cost' => 'nullable|numeric|min:0',
            'image_limit' => 'nullable|integer|min:0|max:10000',
            'account_type' => 'nullable|in:full,temp',
            'temp_period_days' => 'nullable|integer|min:1|max:365',
        ]);

        try {
            DB::beginTransaction();

            $validated['password'] = Hash::make($validated['password']);

            // Set default values
            if (!isset($validated['account_type'])) {
                $validated['account_type'] = 'temp';
            }
            if (!isset($validated['subscription_cost'])) {
                $validated['subscription_cost'] = 300;
            }
            if (!isset($validated['image_limit'])) {
                $validated['image_limit'] = 100;
            }

            // Calculate temp_expires_at if temp account and period is set
            if ($validated['account_type'] === 'temp' && !empty($validated['temp_period_days'])) {
                $validated['temp_expires_at'] = now()->addDays((int) $validated['temp_period_days']);
            }

            $user = User::create($validated);

            DB::commit();

            return redirect()->route('admin.shop-owners.index')
                ->with('success', ucfirst(str_replace('_', ' ', $user->role)) . ' created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create user. Please try again.']);
        }
    }

    /**
     * Display the specified shop owner with detailed information.
     */
    public function show(User $shopOwner)
    {
        // Ensure we're only showing shop owners
        if (!in_array($shopOwner->role, ['shop_owner', 'disabled', 'restaurant', 'merchant'])) {
            abort(404);
        }

        // Load employees with pagination-like limit for performance
        $shopOwner->load(['employees' => function ($query) {
            $query->latest()->take(50); // Limit to prevent memory issues
        }]);

        // Get all user IDs for this shop (owner + employees)
        $userIds = collect([$shopOwner->id])->merge($shopOwner->employees->pluck('id'))->filter();

        // Calculate statistics with better error handling
        try {
            $shopOwner->total_sales = Bill::whereIn('user_id', $userIds)->sum('total_price') ?? 0;

            $shopOwner->sales_this_month = Bill::whereIn('user_id', $userIds)
                ->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->sum('total_price') ?? 0;

            $shopOwner->sales_today = Bill::whereIn('user_id', $userIds)
                ->whereDate('created_at', Carbon::today())
                ->sum('total_price') ?? 0;

            $shopOwner->products_count = Product::where('user_id', $shopOwner->id)->count();
            $shopOwner->customers_count = Customer::where('user_id', $shopOwner->id)->count();
            $shopOwner->employees_count = $shopOwner->employees->count();

            // Count total images for this user
            $shopOwner->total_images = 0;
            $products = Product::where('user_id', $shopOwner->id)->whereNotNull('pictures')->get();
            foreach ($products as $product) {
                $pictures = json_decode($product->pictures, true);
                if (is_array($pictures)) {
                    $shopOwner->total_images += count($pictures);
                }
            }
        } catch (\Exception $e) {
            // Set default values if queries fail
            $shopOwner->total_sales = 0;
            $shopOwner->sales_this_month = 0;
            $shopOwner->sales_today = 0;
            $shopOwner->products_count = 0;
            $shopOwner->customers_count = 0;
            $shopOwner->employees_count = 0;
        }

        return view('admin.shop-owners.show', compact('shopOwner'));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $shopOwner)
    {
        $shopOwner->loadCount('employees');
        // Calculate the trial period based on created_at and temp_expires_at
        $calculatedTrialPeriod = $shopOwner->getCalculatedTrialPeriod();
        return view('admin.shop-owners.edit', compact('shopOwner', 'calculatedTrialPeriod'));
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $shopOwner)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($shopOwner->id)],
            'password' => 'nullable|string|min:8',
            'role' => 'required|in:shop_owner,admin,disabled,restaurant,merchant',
            'phone_number' => 'nullable|string|max:20',
            'subscription_cost' => 'nullable|numeric|min:0',
            'image_limit' => 'nullable|integer|min:0|max:10000',
            'account_type' => 'nullable|in:full,temp',
            'temp_period_days' => 'nullable|integer|min:0|max:365',
            'extend_days' => 'nullable|integer|min:-365|max:365',
        ]);

        try {
            DB::beginTransaction();

            if ($request->filled('password')) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }

            // Handle account type and temp period
            if (isset($validated['account_type'])) {
                if ($validated['account_type'] === 'temp') {
                    // If temp_period_days is provided and > 0, recalculate expiration from creation date
                    if (!empty($validated['temp_period_days']) && $validated['temp_period_days'] > 0) {
                        $validated['temp_period_days'] = (int) $validated['temp_period_days'];
                        $validated['temp_expires_at'] = $shopOwner->created_at->addDays($validated['temp_period_days']);
                    }
                    // If temp_period_days is 0 or empty, keep the current value (don't update temp_period_days or temp_expires_at)
                    elseif (empty($validated['temp_period_days']) || $validated['temp_period_days'] == 0) {
                        unset($validated['temp_period_days']);
                        unset($validated['temp_expires_at']);
                    }
                } elseif ($validated['account_type'] === 'full') {
                    $validated['temp_expires_at'] = null;
                    $validated['temp_period_days'] = null;
                }
            }

            // Handle extend expiry - adds to trial period and extends expiration
            if ($request->filled('extend_days') && $shopOwner->account_type === 'temp') {
                $extendDays = (int) $request->extend_days;
                if ($extendDays != 0) {
                    // Get current trial period or calculate from expiration
                    $currentPeriod = $shopOwner->temp_period_days;
                    if (!$currentPeriod && $shopOwner->temp_expires_at) {
                        $currentPeriod = (int) $shopOwner->created_at->diffInDays($shopOwner->temp_expires_at);
                    }
                    if (!$currentPeriod) {
                        $currentPeriod = 0;
                    }

                    // New total trial period (can be negative, but we cap at minimum 1)
                    $newPeriod = $currentPeriod + $extendDays;
                    if ($newPeriod < 1) {
                        $newPeriod = 1;
                    }
                    $validated['temp_period_days'] = $newPeriod;

                    // Extend/subtract expiration by extend_days from current expiration
                    $currentExpiry = $shopOwner->temp_expires_at;
                    if ($currentExpiry) {
                        $validated['temp_expires_at'] = $currentExpiry->addDays($extendDays);
                    } else {
                        // If no valid expiry, set from now
                        $validated['temp_expires_at'] = now()->addDays($extendDays);
                    }
                }
            }

            // Remove extend_days from validated data as it's not a column
            unset($validated['extend_days']);

            $shopOwner->update($validated);

            DB::commit();

            $redirectRoute = in_array($shopOwner->role, ['shop_owner', 'disabled', 'restaurant', 'merchant'])
                ? 'admin.shop-owners.show'
                : 'admin.shop-owners.index';

            return redirect()->route($redirectRoute, $shopOwner->id)
                ->with('success', __('messages.User updated successfully.'));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update user. Please try again.']);
        }
    }

    /**
     * Remove the specified user and all related data.
     */
    public function destroy(User $shopOwner)
    {
        try {
            DB::beginTransaction();

            // Delete all employees first
            if ($shopOwner->role === 'shop_owner' || $shopOwner->role === 'restaurant' || $shopOwner->role === 'merchant' || $shopOwner->role === 'disabled') {
                // Get all employee IDs for this shop owner
                $employeeIds = Employee::where('shop_owner_id', $shopOwner->id)->pluck('id');

                // Delete employee payments
                EmployeePayment::whereIn('employee_id', $employeeIds)->delete();

                // Delete employees
                Employee::where('shop_owner_id', $shopOwner->id)->delete();

                // Delete product-related data (before deleting products)
                // Get all product IDs for this user
                $productIds = Product::where('user_id', $shopOwner->id)->pluck('id');

                // Delete product barcodes
                ProductBarcode::whereIn('product_id', $productIds)->delete();

                // Delete batches
                Batch::whereIn('product_id', $productIds)->delete();

                // Delete product variant groups
                ProductVariantGroup::where('user_id', $shopOwner->id)->delete();

                // Delete product images from storage
                $products = Product::where('user_id', $shopOwner->id)->get();
                foreach ($products as $product) {
                    $pictures = json_decode($product->pictures, true);
                    if (is_array($pictures)) {
                        foreach ($pictures as $picture) {
                            if ($picture && Storage::disk('public')->exists($picture)) {
                                Storage::disk('public')->delete($picture);
                            }
                        }
                    }
                }

                // Delete products
                Product::where('user_id', $shopOwner->id)->delete();

                // Delete customer-related data (before deleting customers)
                $customerIds = Customer::where('user_id', $shopOwner->id)->pluck('id');

                // Delete customer payments
                CustomerPayment::whereIn('customer_id', $customerIds)->delete();

                // Delete bills (cascade from customers - bill_product pivot will be deleted automatically)
                Bill::whereIn('customer_id', $customerIds)->delete();

                // Delete bills directly associated with user
                Bill::where('user_id', $shopOwner->id)->delete();

                // Delete customers
                Customer::where('user_id', $shopOwner->id)->delete();

                // Delete supplier-related data (before deleting suppliers)
                $supplierIds = Supplier::where('user_id', $shopOwner->id)->pluck('id');

                // Delete supplier payments
                SupplierPayment::whereIn('supplier_id', $supplierIds)->delete();

                // Delete purchase bills
                PurchaseBill::whereIn('supplier_id', $supplierIds)->delete();

                // Delete purchase bills directly associated with user
                PurchaseBill::where('user_id', $shopOwner->id)->delete();

                // Delete suppliers
                Supplier::where('user_id', $shopOwner->id)->delete();

                // Delete other user-related data
                Expense::where('user_id', $shopOwner->id)->delete();
                Tag::where('user_id', $shopOwner->id)->delete();
            }

            $shopOwner->delete();

            DB::commit();

            return redirect()->route('admin.shop-owners.index')
                ->with('success', 'User and all related data deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withErrors(['error' => 'Failed to delete user. Please try again.']);
        }
    }

    /**
     * Toggle user status (active/disabled).
     */
    public function toggleStatus(User $shopOwner)
    {
        try {
            // Toggle between enabled roles and disabled
            $currentRole = $shopOwner->role;

            // Define enabled roles
            $enabledRoles = ['shop_owner', 'restaurant', 'merchant'];

            if (in_array($currentRole, $enabledRoles)) {
                // Disable the shop
                $newRole = 'disabled';
                $status = 'disabled';
            } else {
                // Enable the shop (restore to shop_owner)
                $newRole = 'shop_owner';
                $status = 'activated';
            }

            $shopOwner->update(['role' => $newRole]);

            return redirect()->back()
                ->with('success', "Shop owner has been {$status} successfully.");
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to update status. Please try again.']);
        }
    }

    /**
     * Mark subscription as paid for a user.
     */
    public function markPaid(User $shopOwner)
    {
        try {
            $shopOwner->update([
                'subscription_paid' => true,
                'account_type' => 'full'
            ]);

            return redirect()->back()
                ->with('success', 'Subscription marked as paid and account converted to full successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to mark subscription as paid. Please try again.']);
        }
    }

    /**
     * Convert a temp account to full account.
     */
    public function convertToFull(User $shopOwner)
    {
        try {
            $shopOwner->update([
                'account_type' => 'full',
                'temp_expires_at' => null,
                'temp_period_days' => null
            ]);

            return redirect()->back()
                ->with('success', __('messages.Account converted to full successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => __('messages.Failed to convert account.')]);
        }
    }

    /**
     * Delete all expired temporary accounts.
     */
    public function deleteExpiredTempAccounts()
    {
        try {
            // Get all expired temp accounts (only shop owners, not employees)
            $expiredAccounts = User::expiredTempAccounts()
                ->whereIn('role', ['shop_owner', 'disabled', 'restaurant', 'merchant'])
                ->get();

            $count = $expiredAccounts->count();

            if ($count === 0) {
                return redirect()->back()
                    ->with('info', __('messages.No expired temporary accounts found.'));
            }

            // Delete each expired account (this will handle all related data through the destroy method)
            foreach ($expiredAccounts as $account) {
                // Get all employee IDs for this shop owner
                $employeeIds = Employee::where('shop_owner_id', $account->id)->pluck('id');

                // Delete employee payments
                EmployeePayment::whereIn('employee_id', $employeeIds)->delete();

                // Delete employees
                Employee::where('shop_owner_id', $account->id)->delete();

                // Delete product-related data
                $productIds = Product::where('user_id', $account->id)->pluck('id');
                ProductBarcode::whereIn('product_id', $productIds)->delete();
                Batch::whereIn('product_id', $productIds)->delete();
                ProductVariantGroup::where('user_id', $account->id)->delete();

                // Delete product images from storage
                $products = Product::where('user_id', $account->id)->get();
                foreach ($products as $product) {
                    $pictures = json_decode($product->pictures, true);
                    if (is_array($pictures)) {
                        foreach ($pictures as $picture) {
                            if ($picture && Storage::disk('public')->exists($picture)) {
                                Storage::disk('public')->delete($picture);
                            }
                        }
                    }
                }

                // Delete products
                Product::where('user_id', $account->id)->delete();

                // Delete customer-related data
                $customerIds = Customer::where('user_id', $account->id)->pluck('id');
                CustomerPayment::whereIn('customer_id', $customerIds)->delete();
                Bill::whereIn('customer_id', $customerIds)->delete();
                Bill::where('user_id', $account->id)->delete();
                Customer::where('user_id', $account->id)->delete();

                // Delete supplier-related data
                $supplierIds = Supplier::where('user_id', $account->id)->pluck('id');
                SupplierPayment::whereIn('supplier_id', $supplierIds)->delete();
                PurchaseBill::whereIn('supplier_id', $supplierIds)->delete();
                PurchaseBill::where('user_id', $account->id)->delete();
                Supplier::where('user_id', $account->id)->delete();

                // Delete other user-related data
                Expense::where('user_id', $account->id)->delete();
                Tag::where('user_id', $account->id)->delete();

                // Delete the account
                $account->delete();
            }

            return redirect()->route('admin.shop-owners.index')
                ->with('success', __('messages.Expired accounts deleted successfully.', ['count' => $count]));
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => __('messages.Failed to delete expired accounts.')]);
        }
    }

    /**
     * Disable all expired temporary accounts.
     */
    public function disableExpiredTempAccounts()
    {
        try {
            // Get all expired temp accounts that are not already disabled (only shop owners, not employees)
            $expiredAccounts = User::expiredTempAccounts()
                ->whereIn('role', ['shop_owner', 'restaurant', 'merchant'])
                ->get();

            $count = $expiredAccounts->count();

            if ($count === 0) {
                return redirect()->back()
                    ->with('info', __('messages.No expired temporary accounts found to disable.'));
            }

            // Disable each expired account by changing role to 'disabled'
            foreach ($expiredAccounts as $account) {
                $account->update(['role' => 'disabled']);
            }

            return redirect()->route('admin.dashboard')
                ->with('success', __('messages.Expired accounts disabled successfully.', ['count' => $count]));
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => __('messages.Failed to disable expired accounts.')]);
        }
    }

    /**
     * Delete all disabled expired accounts.
     */
    public function deleteDisabledExpiredAccounts()
    {
        try {
            // Get all disabled expired temp accounts (only shop owners, not employees)
            $disabledAccounts = User::where('account_type', 'temp')
                ->whereNotNull('temp_expires_at')
                ->where('temp_expires_at', '<')
                ->where('role', 'disabled')
                ->get();

            $count = $disabledAccounts->count();

            if ($count === 0) {
                return redirect()->back()
                    ->with('info', __('messages.No disabled expired accounts found to delete.'));
            }

            // Delete each disabled account (this will handle all related data through the destroy method)
            foreach ($disabledAccounts as $account) {
                // Get all employee IDs for this shop owner
                $employeeIds = Employee::where('shop_owner_id', $account->id)->pluck('id');

                // Delete employee payments
                EmployeePayment::whereIn('employee_id', $employeeIds)->delete();

                // Delete employees
                Employee::where('shop_owner_id', $account->id)->delete();

                // Delete product-related data
                $productIds = Product::where('user_id', $account->id)->pluck('id');
                ProductBarcode::whereIn('product_id', $productIds)->delete();
                Batch::whereIn('product_id', $productIds)->delete();
                ProductVariantGroup::where('user_id', $account->id)->delete();

                // Delete product images from storage
                $products = Product::where('user_id', $account->id)->get();
                foreach ($products as $product) {
                    $pictures = json_decode($product->pictures, true);
                    if (is_array($pictures)) {
                        foreach ($pictures as $picture) {
                            if ($picture && Storage::disk('public')->exists($picture)) {
                                Storage::disk('public')->delete($picture);
                            }
                        }
                    }
                }

                // Delete products
                Product::where('user_id', $account->id)->delete();

                // Delete customer-related data
                $customerIds = Customer::where('user_id', $account->id)->pluck('id');
                CustomerPayment::whereIn('customer_id', $customerIds)->delete();
                Bill::whereIn('customer_id', $customerIds)->delete();
                Bill::where('user_id', $account->id)->delete();
                Customer::where('user_id', $account->id)->delete();

                // Delete supplier-related data
                $supplierIds = Supplier::where('user_id', $account->id)->pluck('id');
                SupplierPayment::whereIn('supplier_id', $supplierIds)->delete();
                PurchaseBill::whereIn('supplier_id', $supplierIds)->delete();
                PurchaseBill::where('user_id', $account->id)->delete();
                Supplier::where('user_id', $account->id)->delete();

                // Delete other user-related data
                Expense::where('user_id', $account->id)->delete();
                Tag::where('user_id', $account->id)->delete();

                // Delete the account
                $account->delete();
            }

            return redirect()->route('admin.dashboard')
                ->with('success', __('messages.Disabled expired accounts deleted successfully.', ['count' => $count]));
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => __('messages.Failed to delete disabled expired accounts.')]);
        }
    }

    // Employee Management Methods

    /**
     * Display all employees across all shop owners.
     */
    public function allEmployees()
    {
        $user = auth()->user();
        $query = User::where('role', 'employee')
            ->with(['shopOwner' => function ($query) {
                $query->select('id', 'name', 'email', 'role');
            }]);

        // If user is employee, only show employees of their shop owner
        if ($user->role === 'employee') {
            $query->where('shop_owner_id', $user->shop_owner_id);
        }

        $employees = $query->latest()->get();

        return view('admin.employees.index', compact('employees'));
    }

    /**
     * Show the form for creating a new employee.
     */
    public function createEmployee()
    {
        $user = auth()->user();
        $query = User::whereIn('role', ['shop_owner', 'disabled', 'restaurant', 'merchant'])
            ->select('id', 'name', 'email')
            ->orderBy('name');

        // If user is employee, only show their shop owner
        if ($user->role === 'employee') {
            $query->where('id', $user->shop_owner_id);
        }

        $shopOwners = $query->get();

        return view('admin.employees.create', compact('shopOwners'));
    }

    /**
     * Store a newly created employee.
     */
    public function storeEmployee(Request $request)
    {
        $user = auth()->user();

        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone_number' => 'nullable|string|max:20',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:view_products,create_products,edit_products,delete_products,view_bills,create_bills,edit_bills,delete_bills,view_customers,create_customers,edit_customers,delete_customers,view_suppliers,create_suppliers,edit_suppliers,delete_suppliers,view_purchase_bills,create_purchase_bills,edit_purchase_bills,delete_purchase_bills,view_tags,create_tags,edit_tags,delete_tags,view_expenses,create_expenses,edit_expenses,delete_expenses,manage_settings,view_financial,manage_employees,view_sales,create_sales,edit_sales,delete_sales',
        ];

        // If user is employee, don't require shop_owner_id and force it to their owner
        if ($user->role === 'employee') {
            $validated = $request->validate($validationRules);
            $validated['shop_owner_id'] = $user->shop_owner_id;
        } else {
            $validationRules['shop_owner_id'] = 'required|exists:users,id';
            $validated = $request->validate($validationRules);
        }

        // Verify the shop_owner_id belongs to an actual shop owner
        $shopOwner = User::where('id', $validated['shop_owner_id'])
            ->whereIn('role', ['shop_owner', 'disabled', 'restaurant', 'merchant'])
            ->first();

        if (!$shopOwner) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['shop_owner_id' => 'Invalid shop owner selected.']);
        }

        try {
            DB::beginTransaction();

            $validated['password'] = Hash::make($validated['password']);
            $validated['role'] = 'employee';

            $employee = User::create($validated);

            // Set permissions if provided
            if (isset($validated['permissions'])) {
                $employee->setPermissions($validated['permissions']);
            }

            DB::commit();

            // Redirect based on where we came from
            if ($request->has('from_shop') && $request->from_shop) {
                return redirect()->route('admin.shop-owners.show', $validated['shop_owner_id'])
                    ->with('success', 'Employee created successfully.');
            }

            return redirect()->route('admin.employees.index')
                ->with('success', 'Employee created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create employee. Please try again.']);
        }
    }

    /**
     * Show the form for editing an employee.
     */
    public function editEmployee(User $employee)
    {
        // Ensure this is actually an employee
        if ($employee->role !== 'employee') {
            abort(404);
        }

        $user = auth()->user();
        $query = User::whereIn('role', ['shop_owner', 'disabled', 'restaurant', 'merchant'])
            ->select('id', 'name', 'email')
            ->orderBy('name');

        // If user is employee, only show their shop owner
        if ($user->role === 'employee') {
            $query->where('id', $user->shop_owner_id);
        }

        $shopOwners = $query->get();

        return view('admin.employees.edit', compact('employee', 'shopOwners'));
    }

    /**
     * Update the specified employee.
     */
    public function updateEmployee(Request $request, User $employee)
    {
        // Ensure this is actually an employee
        if ($employee->role !== 'employee') {
            abort(404);
        }

        $user = auth()->user();

        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($employee->id)],
            'password' => 'nullable|string|min:8',
            'phone_number' => 'nullable|string|max:20',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:view_products,create_products,edit_products,delete_products,view_bills,create_bills,edit_bills,delete_bills,view_customers,create_customers,edit_customers,delete_customers,view_suppliers,create_suppliers,edit_suppliers,delete_suppliers,view_purchase_bills,create_purchase_bills,edit_purchase_bills,delete_purchase_bills,view_tags,create_tags,edit_tags,delete_tags,view_expenses,create_expenses,edit_expenses,delete_expenses,manage_settings,view_financial,manage_employees,manage_payments_receipts,view_installments,create_installments,dismiss_installment_notifications,delete_installments,view_sales,create_sales,edit_sales,delete_sales',
        ];

        // If user is employee, don't require shop_owner_id and force it to their owner
        if ($user->role === 'employee') {

            $validated = $request->validate($validationRules);
            $validated['shop_owner_id'] = $user->shop_owner_id;
        } else {

            $validationRules['shop_owner_id'] = 'required|exists:users,id';

            $validated = $request->validate($validationRules);
        }

        // Verify the shop_owner_id belongs to an actual shop owner
        $shopOwner = User::where('id', $validated['shop_owner_id'])
            ->whereIn('role', ['shop_owner', 'disabled', 'restaurant', 'merchant'])
            ->first();

        if (!$shopOwner) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['shop_owner_id' => 'Invalid shop owner selected.']);
        }

        try {
            DB::beginTransaction();

            if ($request->filled('password')) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }

            $employee->update($validated);

            // Update permissions
            if (isset($validated['permissions'])) {
                $employee->setPermissions($validated['permissions']);
            } else {
                // If no permissions sent, clear them
                $employee->permissions = null;
                $employee->save();
            }

            DB::commit();

            return redirect()->route('admin.employees.index')
                ->with('success', 'Employee updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update employee. Please try again.']);
        }
    }

    /**
     * Remove the specified employee.
     */
    public function destroyEmployee(User $employee)
    {
        // Ensure this is actually an employee
        if ($employee->role !== 'employee') {
            abort(404);
        }

        $user = auth()->user();

        // If user is employee, ensure they can only delete employees of their shop owner
        if ($user->role === 'employee' && $employee->shop_owner_id !== $user->shop_owner_id) {
            abort(403, 'Unauthorized');
        }

        try {
            $shopOwnerId = $employee->shop_owner_id;
            $employee->delete();

            // Redirect based on context
            if ($shopOwnerId && request()->has('from_shop')) {
                return redirect()->route('admin.shop-owners.show', $shopOwnerId)
                    ->with('success', 'Employee removed successfully.');
            }

            return redirect()->route('admin.employees.index')
                ->with('success', 'Employee deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to delete employee. Please try again.']);
        }
    }
}
