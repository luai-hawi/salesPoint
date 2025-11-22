<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $query = Supplier::where('user_id', $ownerId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->orderBy('name')->paginate(20);

        if ($request->ajax()) {
            return view('suppliers.index', compact('suppliers'))->render();
        }

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'initial_balance' => 'nullable|numeric|min:-999999|max:999999',
        ]);

        $supplier = Supplier::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'address' => $validated['address'],
            'notes' => $validated['notes'],
            'balance' => $validated['initial_balance'] ?? 0,
            'user_id' => $ownerId,
        ]);

        // If there's an initial balance, create a payment record
        if (!empty($validated['initial_balance']) && $validated['initial_balance'] != 0) {
            $supplier->payments()->create([
                'amount' => $validated['initial_balance'],
                'type' => 'cash',
                'note' => 'Initial balance',
                'payment_date' => now(),
                'user_id' => $ownerId,
            ]);
        }

        return redirect()->route('suppliers.index')->with('success', 'Supplier created successfully!');
    }

    public function show(Supplier $supplier)
    {
        $this->authorizeSupplier($supplier);
        return redirect()->route('suppliers.edit', $supplier);
    }

    public function edit(Supplier $supplier)
    {
        $this->authorizeSupplier($supplier);
        
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        
        // Get recent purchase bills and payments
        $recentBills = $supplier->purchaseBills()
            ->where('user_id', $ownerId)
            ->latest('purchase_date')
            ->take(10)
            ->get();
            
        $recentPayments = $supplier->payments()
            ->where('user_id', $ownerId)
            ->latest('payment_date')
            ->take(10)
            ->get();
        
        return view('suppliers.edit', compact('supplier', 'recentBills', 'recentPayments'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorizeSupplier($supplier);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully!');
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorizeSupplier($supplier);
        
        // Check if supplier has purchase bills
        if ($supplier->purchaseBills()->count() > 0) {
            return redirect()->route('suppliers.index')
                ->with('error', 'Cannot delete supplier with existing purchase bills. Please delete all purchase bills first.');
        }
        
        // Check if supplier has payments
        if ($supplier->payments()->count() > 0) {
            return redirect()->route('suppliers.index')
                ->with('error', 'Cannot delete supplier with payment history. Please clear all payments first.');
        }
        
        // Check if supplier has outstanding balance
        if ($supplier->balance != 0) {
            return redirect()->route('suppliers.index')
                ->with('error', 'Cannot delete supplier with outstanding balance. Please settle the account first.');
        }
        
        $supplierName = $supplier->name;
        $supplier->delete();
        
        return redirect()->route('suppliers.index')
            ->with('success', "Supplier '{$supplierName}' deleted successfully!");
    }

   public function storePayment(Request $request, Supplier $supplier)
{
    $this->authorizeSupplier($supplier);
    
    $user = auth()->user();
    $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
    
    $request->validate([
        'amount' => 'required|numeric|not_in:0',
        'type' => 'required|string|in:cash,card,transfer,check',
        'note' => 'nullable|string|max:255',
        'payment_date' => 'required|date',
    ]);

    // Create payment record
    $payment = $supplier->payments()->create([
        'amount' => $request->amount,
        'type' => $request->type,
        'note' => $request->note,
        'payment_date' => $request->payment_date,
        'user_id' => $ownerId
    ]);

    // Update supplier balance - FIXED: subtract when we pay them
    $supplier->balance -= $request->amount; // Changed from += to -=
    $supplier->save();

    if ($request->expectsJson()) {
        return response()->json([
            'success' => true,
            'message' => 'Payment added successfully',
            'payment' => $payment,
            'new_balance' => $supplier->balance,
        ]);
    }

    return redirect()->back()->with('success', 'Payment added successfully');
}
    public function getRecentPayments(Supplier $supplier)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $this->authorizeSupplier($supplier);
        
        // Get the last purchase bill data using our model method
        $lastBillData = $supplier->getLastPurchaseBillData($ownerId);
        
        // Get the last 10 payments for this supplier
        $payments = $supplier->payments()
            ->where('user_id', $ownerId)
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'type' => $payment->type,
                    'note' => $payment->note,
                    'payment_date' => $payment->payment_date->format('M d, Y'),
                    'created_at_human' => $payment->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'payments' => $payments,
            'last_bill_amount' => $lastBillData['amount'],
            'last_bill_id' => $lastBillData['bill_id'],
            'last_bill_date' => $lastBillData['date']
        ]);
    }

    public function search(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $search = $request->query('search', '');
        
        $query = Supplier::where('user_id', $ownerId);
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        $suppliers = $query->orderBy('name')->take(20)->get();
        
        return response()->json($suppliers);
    }

    /**
     * Helper to ensure supplier belongs to the current user
     */
    private function authorizeSupplier(Supplier $supplier)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        if ($supplier->user_id !== $ownerId) {
            abort(403, 'Unauthorized access to supplier.');
        }
    }

    /**
     * Update a supplier payment
     */
    public function updatePayment(Request $request, SupplierPayment $supplier_payment)
{
    $user = auth()->user();
    $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
    
    if ($supplier_payment->user_id !== $ownerId) {
        abort(403, 'Unauthorized');
    }

    $validated = $request->validate([
        'amount' => 'required|numeric|not_in:0',
        'type' => 'required|string|in:cash,card,transfer,check',
        'note' => 'nullable|string|max:255',
        'payment_date' => 'required|date',
    ]);

    $previousAmount = $supplier_payment->amount;

    $supplier_payment->update($validated);

    $difference = $validated['amount'] - $previousAmount;
    $supplier = $supplier_payment->supplier;
    
    // FIXED: subtract the difference (when payment increases, balance decreases)
    $supplier->update(['balance' => $supplier->balance - $difference]); // Changed from + to -

    return response()->json(['success' => true]);
}

    /**
     * Quick store payment for AJAX calls
     */
    public function quickStorePayment(Request $request, Supplier $supplier) {
    $user = auth()->user();
    $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
    $this->authorizeSupplier($supplier);

    $validated = $request->validate([
        'amount' => 'required|numeric|not_in:0',
        'type' => 'required|string|in:cash,card,transfer,check',
        'note' => 'nullable|string|max:255',
        'payment_date' => 'required|date',
    ]);

    $supplier->payments()->create([
        'amount' => $validated['amount'],
        'type' => $validated['type'],
        'note' => $validated['note'] ?? null,
        'payment_date' => $validated['payment_date'],
        'user_id' => $ownerId,
    ]);

    // FIXED: subtract payment from balance
    $supplier->update(['balance' => $supplier->balance - $validated['amount']]); // Changed from + to -

    // Return the updated balance
    return response()->json([
        'success' => true, 
        'message' => 'Payment added successfully!',
        'new_balance' => $supplier->fresh()->balance
    ]);
}

    /**
     * Show payments for a specific supplier
     */
    public function showPayments(Supplier $supplier)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $this->authorizeSupplier($supplier);

        $payments = $supplier->payments()->where('user_id', $ownerId)->latest('payment_date')->paginate(20);

        // Load shop owner data if user is an employee
        $shopOwner = null;
        if ($user->role === 'employee' && $user->shop_owner_id) {
            $shopOwner = \App\Models\User::find($user->shop_owner_id);
        }

        return view('suppliers.payments', compact('supplier', 'payments', 'shopOwner'));
    }

    /**
     * Export suppliers to CSV
     */
    public function export()
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        
        $suppliers = Supplier::where('user_id', $ownerId)
            ->orderBy('name')
            ->get();

        $filename = 'suppliers_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($suppliers) {
            $file = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($file, [
                'ID',
                'Name',
                'Phone',
                'Email',
                'Address',
                'Balance',
                'Balance Status',
                'Total Purchases',
                'Total Payments',
                'Notes',
                'Created Date',
                'Last Updated'
            ]);

            // Add supplier data
            foreach ($suppliers as $supplier) {
                $balanceStatus = $supplier->balance > 0 ? 'We Owe Them' : 
                               ($supplier->balance < 0 ? 'They Owe Us' : 'Even');
                
                fputcsv($file, [
                    $supplier->id,
                    $supplier->name,
                    $supplier->phone ?? '',
                    $supplier->email ?? '',
                    $supplier->address ?? '',
                    number_format($supplier->balance, 2),
                    $balanceStatus,
                    number_format($supplier->getTotalPurchases(), 2),
                    number_format($supplier->getTotalPayments(), 2),
                    $supplier->notes ?? '',
                    $supplier->created_at->format('Y-m-d H:i:s'),
                    $supplier->updated_at->format('Y-m-d H:i:s')
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
    /**
 * Delete a supplier payment
 */
public function deletePayment(Request $request, SupplierPayment $supplier_payment)
{
    $user = auth()->user();
    $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
    
    if ($supplier_payment->user_id !== $ownerId) {
        abort(403, 'Unauthorized');
    }

    $supplier = $supplier_payment->supplier;
    $paymentAmount = $supplier_payment->amount;
    
    try {
        // FIXED: When deleting a payment, add it back to balance (we didn't pay them after all)
        $supplier->balance += $paymentAmount; // Changed from -= to +=
        $supplier->save();
        
        // Delete the payment
        $supplier_payment->delete();
        
        return response()->json(['success' => true]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
}