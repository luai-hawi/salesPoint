<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $query = Customer::where('user_id', $ownerId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name')->paginate(20);

        if ($request->ajax()) {
            return view('customers.index', compact('customers'))->render();
        }

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'initial_balance' => 'nullable|numeric|min:-999999|max:999999',
        ]);

        $customer = Customer::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'balance' => $validated['initial_balance'] ?? 0,
            'user_id' => $ownerId,
        ]);

        // If there's an initial balance, create a payment record
        if (!empty($validated['initial_balance']) && $validated['initial_balance'] != 0) {
            $customer->payments()->create([
                'amount' => $validated['initial_balance'],
                'note' => 'Initial balance',
                'user_id' => $ownerId,
            ]);
        }

        return redirect()->route('customers.index')->with('success', 'Customer created successfully!');
    }

    public function show(Customer $customer)
    {
        $this->authorizeCustomer($customer);
        // Redirect to edit page since we're using edit as the main customer detail page
        return redirect()->route('customers.edit', $customer);
    }

    public function edit(Customer $customer)
    {
        $this->authorizeCustomer($customer);
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeCustomer($customer);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $customer->update($validated);

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully!');
    }

    public function destroy(Customer $customer)
    {
        $this->authorizeCustomer($customer);
        
        // Check if customer has any payments
        if ($customer->payments()->count() > 0) {
            return redirect()->route('customers.index')
                ->with('error', 'Cannot delete customer with payment history. Please clear all payments first.');
        }
        
        // Check if customer has outstanding balance
        if ($customer->balance != 0) {
            return redirect()->route('customers.index')
                ->with('error', 'Cannot delete customer with outstanding balance. Please settle the account first.');
        }
        
        // Check if customer has any bills
        $billsCount = \App\Models\Bill::where('customer_id', $customer->id)->count();
        if ($billsCount > 0) {
            return redirect()->route('customers.index')
                ->with('error', 'Cannot delete customer with existing bills. Please remove bill associations first.');
        }
        
        $customerName = $customer->name;
        $customer->delete();
        
        return redirect()->route('customers.index')
            ->with('success', "Customer '{$customerName}' deleted successfully!");
    }

    public function showPayments(Customer $customer)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $this->authorizeCustomer($customer);

        $payments = $customer->payments()->where('user_id', $ownerId)->latest()->get();

        return view('customers.payments', compact('customer', 'payments'));
    }

    public function storePayment(Request $request, Customer $customer)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $this->authorizeCustomer($customer);

        $validated = $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'note' => 'nullable|string|max:255',
        ]);

        $customer->payments()->create([
            'amount' => $validated['amount'],
            'note' => $validated['note'] ?? null,
            'user_id' => $ownerId,
        ]);

        $customer->update(['balance' => $customer->balance + $validated['amount']]);

        return back()->with('success', 'Payment added successfully!');
    }

    public function updatePayment(Request $request, CustomerPayment $customer_payment)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        
        if ($customer_payment->user_id !== $ownerId) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric',
            'note' => 'nullable|string|max:255',
        ]);

        $previousAmount = $customer_payment->amount;

        $customer_payment->update($validated);

        $difference = $validated['amount'] - $previousAmount;
        $customer = $customer_payment->customer;
        $customer->update(['balance' => $customer->balance + $difference]);

        return response()->json(['success' => true]);
    }

    /**
     * Helper to ensure customer belongs to the current user
     */
    private function authorizeCustomer(Customer $customer)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        if ($customer->user_id !== $ownerId) {
            abort(403, 'Unauthorized access to customer.');
        }
    }
}