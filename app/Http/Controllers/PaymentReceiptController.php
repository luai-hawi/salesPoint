<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\CustomerPayment;
use App\Models\EmployeePayment;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentReceiptController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        // Get counts for autocomplete
        $customers = Customer::where('user_id', $ownerId)->get();
        $employees = Employee::where('shop_owner_id', $ownerId)->get();
        $suppliers = Supplier::where('user_id', $ownerId)->get();

        return view('payments_receipts', compact('customers', 'employees', 'suppliers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'transaction_type' => 'required|in:payment,receipt',
            'entity_type' => 'required|in:customer,employee,supplier',
            'entity_id' => 'required',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'type' => 'required|in:cash,card,transfer,check',
        ]);

        $user = Auth::user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $transactionType = $request->transaction_type;
        $entityType = $request->entity_type;
        $entityId = $request->entity_id;
        $amount = $request->amount;
        $note = $request->note;

        // Determine the sign of the amount based on transaction type and entity
        // For Customers:
        //   - Payment (customer pays us) = positive
        //   - Receipt (we receive from customer) = negative (reduces their debt)
        // For Employees:
        //   - Payment (we pay employee) = positive
        //   - Receipt (employee pays us) = negative
        // For Suppliers:
        //   - Payment (we pay supplier) = positive
        //   - Receipt (supplier pays us) = negative

        $signedAmount = $this->calculateSignedAmount($transactionType, $entityType, $amount);

        switch ($entityType) {
            case 'customer':
                $customer = Customer::where('user_id', $ownerId)->findOrFail($entityId);

                CustomerPayment::create([
                    'customer_id' => $customer->id,
                    'amount' => $signedAmount,
                    'type' => $request->type,
                    'note' => $note,
                    'user_id' => $ownerId,
                ]);

                // Update customer balance
                $customer->balance = ($customer->balance ?? 0) + $signedAmount;
                $customer->save();

                $message = $transactionType === 'payment'
                    ? __('messages.payment_recorded_for_customer')
                    : __('messages.receipt_recorded_for_customer');
                break;

            case 'employee':
                $employee = Employee::where('shop_owner_id', $ownerId)->findOrFail($entityId);

                EmployeePayment::create([
                    'employee_id' => $employee->id,
                    'amount' => $signedAmount, // Use signed amount - positive for payment, negative for receipt
                    'payment_date' => $request->payment_date,
                    'type' => $request->type,
                    'note' => $note,
                ]);

                $message = $transactionType === 'payment'
                    ? __('messages.payment_recorded_for_employee')
                    : __('messages.receipt_recorded_for_employee');
                break;

            case 'supplier':
                $supplier = Supplier::where('user_id', $ownerId)->findOrFail($entityId);

                SupplierPayment::create([
                    'supplier_id' => $supplier->id,
                    'amount' => $signedAmount,
                    'type' => $request->type,
                    'note' => $note,
                    'payment_date' => $request->payment_date,
                    'user_id' => $ownerId,
                ]);

                // Update supplier balance: subtract because:
                // - Payment (we pay supplier, positive amount): reduces what we owe = decrease balance
                // - Receipt (supplier pays us, negative amount): increases what they owe us = increase balance
                $supplier->balance = ($supplier->balance ?? 0) - $signedAmount;
                $supplier->save();

                $message = $transactionType === 'payment'
                    ? __('messages.payment_recorded_for_supplier')
                    : __('messages.receipt_recorded_for_supplier');
                break;
        }

        // Return JSON for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Calculate the signed amount based on transaction type and entity
     */
    private function calculateSignedAmount($transactionType, $entityType, $amount)
    {
        // Payment = WE pay them (money goes OUT)
        // Receipt = THEY pay us (money comes IN)

        // Customer: balance negative = they owe us, positive = we owe them
        // - Payment (we pay them): negative in DB, balance decreases (they owe us less)
        // - Receipt (they pay us): positive in DB, balance increases (they owe us more)

        // Employee: no balance field
        // - Payment (we pay them): positive in DB
        // - Receipt (they pay us): negative in DB

        // Supplier: balance positive = they owe us, negative = we owe them
        // - Payment (we pay them): positive in DB, balance decreases (we owe them less)
        // - Receipt (they pay us): negative in DB, balance increases (they owe us more)

        if ($transactionType === 'payment') {
            // Payment: WE pay them (money going OUT)
            if ($entityType === 'customer') {
                return -abs($amount); // Negative in DB, balance decreases
            } elseif ($entityType === 'employee') {
                return abs($amount); // Positive in DB
            } else {
                // Supplier
                return abs($amount); // Positive in DB, balance decreases
            }
        } else {
            // Receipt: THEY pay us (money coming IN)
            if ($entityType === 'customer') {
                return abs($amount); // Positive in DB, balance increases
            } elseif ($entityType === 'employee') {
                return -abs($amount); // Negative in DB
            } else {
                // Supplier
                return -abs($amount); // Negative in DB, balance increases
            }
        }
    }

    /**
     * API endpoint to get customer data for autocomplete
     */
    public function getCustomers(Request $request)
    {
        $user = Auth::user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $search = $request->search;

        $customers = Customer::where('user_id', $ownerId)
            ->where('name', 'like', "%{$search}%")
            ->select('id', 'name', 'phone', 'balance')
            ->limit(10)
            ->get();

        return response()->json($customers);
    }

    /**
     * API endpoint to get employees data for autocomplete
     */
    public function getEmployees(Request $request)
    {
        $user = Auth::user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $search = $request->search;

        $employees = Employee::where('shop_owner_id', $ownerId)
            ->where('name', 'like', "%{$search}%")
            ->select('id', 'name', 'job_title', 'monthly_salary')
            ->limit(10)
            ->get();

        return response()->json($employees);
    }

    /**
     * API endpoint to get suppliers data for autocomplete
     */
    public function getSuppliers(Request $request)
    {
        $user = Auth::user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $search = $request->search;

        $suppliers = Supplier::where('user_id', $ownerId)
            ->where('name', 'like', "%{$search}%")
            ->select('id', 'name', 'phone', 'balance')
            ->limit(10)
            ->get();

        return response()->json($suppliers);
    }
}
