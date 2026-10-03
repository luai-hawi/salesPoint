<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\CustomerLedger;
use App\Services\SupplierLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentReceiptController extends Controller
{
    public function index()
    {
        $this->authorizePaymentsReceipts();

        $user = Auth::user();
        $ownerId = $user->ownerId();
        if (! $ownerId) {
            abort(403);
        }

        $customers = Customer::withoutGlobalScopes()->where('user_id', $ownerId)->orderBy('name')->get();
        $customers->each(function (Customer $customer) {
            $customer->open_bills = CustomerLedger::openBills($customer);
        });

        $employees = Employee::where('shop_owner_id', $ownerId)->orderBy('name')->get();
        $suppliers = Supplier::withoutGlobalScopes()->where('user_id', $ownerId)->orderBy('name')->get();

        return view('payments_receipts', compact('customers', 'employees', 'suppliers'));
    }

    public function store(Request $request)
    {
        $this->authorizePaymentsReceipts();

        $data = $request->validate([
            'transaction_type' => 'required|in:payment,receipt',
            'entity_type' => 'required|in:customer,employee,supplier',
            'entity_id' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'type' => 'required|in:cash,card,transfer,check',
            'note' => 'nullable|string|max:255',
            'bill_id' => 'nullable|integer',
        ]);

        $user = Auth::user();
        $ownerId = $user->ownerId();
        if (! $ownerId) {
            abort(403);
        }
        $signedAmount = $this->calculateSignedAmount($data['transaction_type'], $data['entity_type'], $data['amount']);
        $at = Carbon::parse($data['payment_date'])->setTime(now()->hour, now()->minute, now()->second);

        DB::transaction(function () use ($data, $ownerId, $signedAmount, $at) {
            switch ($data['entity_type']) {
                case 'customer':
                    $customer = Customer::withoutGlobalScopes()->where('user_id', $ownerId)->findOrFail($data['entity_id']);
                    $bill = null;
                    if (! empty($data['bill_id'])) {
                        $bill = $customer->bills()->withoutGlobalScopes()
                            ->where('user_id', $ownerId)
                            ->whereKey($data['bill_id'])
                            ->firstOrFail();
                    }

                    if ($bill && $signedAmount <= 0) {
                        throw ValidationException::withMessages([
                            'amount' => __('receivables.validation.bill_link_positive_only'),
                        ]);
                    }

                    if ($bill) {
                        CustomerLedger::receiveForBill($bill, $signedAmount, $data['type'], $data['note'] ?? null, $at);

                        break;
                    }

                    if ($signedAmount > 0) {
                        CustomerLedger::receive($customer, $signedAmount, $data['type'], $data['note'] ?? null, $at);
                    } else {
                        CustomerLedger::adjust($customer, $signedAmount, $data['type'], $data['note'] ?? null, $at);
                    }
                    break;

                case 'employee':
                    $employee = Employee::where('shop_owner_id', $ownerId)->findOrFail($data['entity_id']);

                    EmployeePayment::create([
                        'employee_id' => $employee->id,
                        'amount' => $signedAmount,
                        'payment_date' => $data['payment_date'],
                        'type' => $data['type'],
                        'note' => $data['note'] ?? null,
                    ]);
                    break;

                case 'supplier':
                    $supplier = Supplier::withoutGlobalScopes()->where('user_id', $ownerId)->findOrFail($data['entity_id']);

                    if ($signedAmount > 0) {
                        SupplierLedger::pay($supplier, $signedAmount, $data['type'], $data['note'] ?? null, $at);
                    } else {
                        SupplierLedger::recordPayment($supplier, $signedAmount, $data['type'], $data['note'] ?? null, $at);
                    }
                    break;
            }
        });

        $message = match ([$data['entity_type'], $data['transaction_type']]) {
            ['customer', 'payment'] => __('messages.payment_recorded_for_customer'),
            ['customer', 'receipt'] => __('messages.receipt_recorded_for_customer'),
            ['employee', 'payment'] => __('messages.payment_recorded_for_employee'),
            ['employee', 'receipt'] => __('messages.receipt_recorded_for_employee'),
            ['supplier', 'payment'] => __('messages.payment_recorded_for_supplier'),
            default => __('messages.receipt_recorded_for_supplier'),
        };

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->back()->with('success', $message);
    }

    private function calculateSignedAmount($transactionType, $entityType, $amount)
    {
        if ($transactionType === 'payment') {
            if ($entityType === 'customer') {
                return -abs($amount);
            }
            if ($entityType === 'employee') {
                return abs($amount);
            }

            return abs($amount);
        }

        if ($entityType === 'customer') {
            return abs($amount);
        }
        if ($entityType === 'employee') {
            return -abs($amount);
        }

        return -abs($amount);
    }

    public function getCustomers(Request $request)
    {
        $this->authorizePaymentsReceipts();

        $ownerId = Auth::user()->ownerId();
        if (! $ownerId) {
            return response()->json([]);
        }
        $search = trim((string) $request->search);

        $customers = Customer::withoutGlobalScopes()->where('user_id', $ownerId)
            ->where('name', 'like', "%{$search}%")
            ->select('id', 'name', 'phone', 'balance')
            ->limit(10)
            ->get();

        $customers->each(function (Customer $customer) {
            $customer->open_bills = CustomerLedger::openBills($customer)->map(fn ($row) => [
                'bill_id' => $row['bill']->id,
                'due' => $row['due'],
            ])->values();
        });

        return response()->json($customers);
    }

    public function getEmployees(Request $request)
    {
        $this->authorizePaymentsReceipts();

        $ownerId = Auth::user()->ownerId();
        if (! $ownerId) {
            return response()->json([]);
        }
        $search = $request->search;

        $employees = Employee::where('shop_owner_id', $ownerId)
            ->where('name', 'like', "%{$search}%")
            ->select('id', 'name', 'job_title', 'monthly_salary')
            ->limit(10)
            ->get();

        return response()->json($employees);
    }

    public function getSuppliers(Request $request)
    {
        $this->authorizePaymentsReceipts();

        $ownerId = Auth::user()->ownerId();
        if (! $ownerId) {
            return response()->json([]);
        }
        $search = $request->search;

        $suppliers = Supplier::withoutGlobalScopes()->where('user_id', $ownerId)
            ->where('name', 'like', "%{$search}%")
            ->select('id', 'name', 'phone', 'balance')
            ->limit(10)
            ->get();

        return response()->json($suppliers);
    }

    private function authorizePaymentsReceipts(): void
    {
        $user = Auth::user();
        if ($user->role === 'employee' && ! $user->hasPermission('manage_payments_receipts')) {
            abort(403, 'Unauthorized');
        }
    }
}
