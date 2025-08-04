<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\CustomerPayment;

class CustomerController extends Controller
{
    public function index(Request $request)
{
    $query = Customer::query();

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where('name', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%");
    }

    $customers = $query->orderBy('name')->paginate(10);

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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
        ]);

        Customer::create($validated);

        return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
    }
    public function show(Customer $customer)
{
    return view('customers.edit', compact('customer'));
}

public function update(Request $request, Customer $customer)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'phone' => 'nullable|string|max:20',
        'address' => 'nullable|string|max:255',
    ]);

    $customer->update($request->only('name', 'phone', 'address'));

    return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
}





//payments
public function showPayments(Customer $customer)
{
    $payments = $customer->payments()->latest()->get();

    return view('customers.payments', compact('customer', 'payments'));
}

public function storePayment(Request $request, Customer $customer)
{
    $request->validate([
        'amount' => 'required|numeric|min:0.01',
        'note' => 'nullable|string|max:255',
    ]);

    $customer->payments()->create([
        'amount' => $request->amount,
        'note' => $request->note,
    ]);
    $customer->update(['balance' => $customer->balance + $request->amount]);

    return back()->with('success', 'Payment added.');
}

public function updatePayment(Request $request, CustomerPayment $customer_payment)
{

    $request->validate([
        'amount' => 'required|numeric',
        'note'   => 'nullable|string|max:255',
    ]);
    $previousAmount = $customer_payment->amount;

    $customer_payment->update([
        'amount' => $request->amount,
        'note'   => $request->note,
    ]);
    $difference = $request->amount - $previousAmount;
    $customer = $customer_payment->customer;
    $customer->update(['balance' => $customer->balance + $difference]);

    return response()->json(['success' => true]);
}


}
