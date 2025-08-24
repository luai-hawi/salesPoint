<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Bill;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
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
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:shop_owner,admin,restaurant,merchant',
        ]);

        try {
            DB::beginTransaction();
            
            $validated['password'] = Hash::make($validated['password']);
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
        $shopOwner->load(['employees' => function($query) {
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
        return view('admin.shop-owners.edit', compact('shopOwner'));
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $shopOwner)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($shopOwner->id)],
            'password' => 'nullable|string|min:8',
            'role' => 'required|in:shop_owner,admin,disabled,restaurant,merchant',
        ]);

        try {
            DB::beginTransaction();
            
            if ($request->filled('password')) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }

            $shopOwner->update($validated);
            
            DB::commit();
            
            $redirectRoute = in_array($shopOwner->role, ['shop_owner', 'disabled', 'restaurant', 'merchant']) 
                ? 'admin.shop-owners.show' 
                : 'admin.shop-owners.index';
                
            return redirect()->route($redirectRoute, $shopOwner->id)
                ->with('success', 'User updated successfully.');
                
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
                $shopOwner->employees()->delete();
                
                // Delete related business data
                Product::where('user_id', $shopOwner->id)->delete();
                Customer::where('user_id', $shopOwner->id)->delete();
                
                // Note: You might want to keep bills for accounting purposes
                // Bill::where('user_id', $shopOwner->id)->delete();
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
            $newRole = $shopOwner->role;
            $shopOwner->update(['role' => $newRole]);

            $status = ($newRole === 'shop_owner' || $newRole === 'restaurant' || $newRole === 'merchant') ? 'activated' : 'disabled';
            return redirect()->back()
                ->with('success', "Shop owner has been {$status} successfully.");
                
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to update status. Please try again.']);
        }
    }

    // Employee Management Methods

    /**
     * Display all employees across all shop owners.
     */
    public function allEmployees()
    {
        $employees = User::where('role', 'employee')
            ->with(['shopOwner' => function($query) {
                $query->select('id', 'name', 'email', 'role');
            }])
            ->latest()
            ->get();

        return view('admin.employees.index', compact('employees'));
    }

    /**
     * Show the form for creating a new employee.
     */
    public function createEmployee()
    {
        $shopOwners = User::whereIn('role', ['shop_owner','disabled','restaurant','merchant'])
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();
            
        return view('admin.employees.create', compact('shopOwners'));
    }

    /**
     * Store a newly created employee.
     */
    public function storeEmployee(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'shop_owner_id' => 'required|exists:users,id',
        ]);

        // Verify the shop_owner_id belongs to an actual shop owner
        $shopOwner = User::where('id', $validated['shop_owner_id'])
            ->whereIn('role', ['shop_owner','disabled','restaurant','merchant'])
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
        
        $shopOwners = User::whereIn('role', ['shop_owner','disabled','restaurant','merchant'])
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();
            
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
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($employee->id)],
            'password' => 'nullable|string|min:8',
            'shop_owner_id' => 'required|exists:users,id',
        ]);

        // Verify the shop_owner_id belongs to an actual shop owner
        $shopOwner = User::where('id', $validated['shop_owner_id'])
            ->whereIn('role', ['shop_owner','disabled','restaurant','merchant'])
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