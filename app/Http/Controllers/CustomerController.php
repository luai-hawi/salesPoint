<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Services\CustomerLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeCustomerView();

        $ownerId = auth()->user()->ownerId();
        $query = Customer::withoutGlobalScopes()->where('user_id', $ownerId);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($request->input('balance') === 'debt') {
            $query->where('balance', '<', 0);
        } elseif ($request->input('balance') === 'credit') {
            $query->where('balance', '>', 0);
        } elseif ($request->input('balance') === 'settled') {
            $query->where('balance', 0);
        }

        $customers = $query->orderBy('name')->paginate(20)->withQueryString();
        $customers->getCollection()->transform(function (Customer $customer) use ($ownerId) {
            $lastBill = $customer->bills()->withoutGlobalScopes()->where('user_id', $ownerId)->latest('created_at')->first();
            $customer->last_bill = $lastBill;
            $customer->open_bills = CustomerLedger::openBills($customer->fresh())->values();
            $customer->open_bills_count = $customer->open_bills->count();

            return $customer;
        });

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        $this->authorizeCustomerCreate();

        return view('customers.create');
    }

    public function store(Request $request)
    {
        $this->authorizeCustomerCreate();

        $ownerId = auth()->user()->ownerId();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'initial_balance' => 'nullable|numeric|min:-999999|max:999999',
        ]);

        $customer = DB::transaction(function () use ($validated, $ownerId) {
            $customer = Customer::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'balance' => 0,
                'user_id' => $ownerId,
            ]);

            if (! empty($validated['initial_balance']) && (float) $validated['initial_balance'] !== 0.0) {
                CustomerLedger::adjust(
                    $customer,
                    (float) $validated['initial_balance'],
                    'cash',
                    'Initial balance',
                    null,
                    CustomerLedger::KIND_OPENING,
                );
            }

            return $customer;
        });

        return redirect()->route('customers.index')->with('success', __('messages.Customer created successfully!'));
    }

    public function show(Customer $customer)
    {
        $this->authorizeCustomer($customer);

        return redirect()->route('customers.edit', $customer);
    }

    public function edit(Customer $customer)
    {
        $this->authorizeCustomerWrite($customer);

        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeCustomerWrite($customer);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $customer->update($validated);

        return redirect()->route('customers.index')->with('success', __('messages.Customer updated successfully!'));
    }

    public function destroy(Customer $customer)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('delete_customers')) {
            abort(403, 'Unauthorized');
        }

        $this->authorizeCustomer($customer);

        if ($customer->payments()->count() > 0 || (float) $customer->balance !== 0.0 || $customer->bills()->exists()) {
            return redirect()->route('customers.index')
                ->with('error', __('receivables.messages.customer_has_history'));
        }

        $name = $customer->name;
        $customer->delete();

        return redirect()->route('customers.index')->with('success', __('receivables.messages.customer_deleted', ['name' => $name]));
    }

    public function showPayments(Request $request, Customer $customer)
    {
        $this->authorizeCustomerPaymentsView($customer);

        [$payments, $openBills, $runningBalance] = $this->statementData($request, $customer);

        return view('customers.payments', [
            'customer' => $customer->fresh(),
            'payments' => $payments,
            'openBills' => $openBills,
            'runningBalance' => $runningBalance,
            'statementMode' => false,
        ]);
    }

    public function statement(Request $request, Customer $customer)
    {
        $this->authorizeCustomerPaymentsView($customer);

        [$payments, $openBills, $runningBalance] = $this->statementData($request, $customer);

        return view('customers.statement', [
            'customer' => $customer->fresh(),
            'payments' => $payments,
            'openBills' => $openBills,
            'runningBalance' => $runningBalance,
        ]);
    }

    public function storePayment(Request $request, Customer $customer)
    {
        $this->authorizeCustomerPaymentsWrite($customer);
        $ownerId = auth()->user()->ownerId();

        $data = $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'type' => 'required|string|in:cash,card,transfer,check',
            'note' => 'nullable|string|max:255',
            'payment_date' => 'nullable|date',
            'bill_id' => 'nullable|integer',
        ]);

        $payment = DB::transaction(function () use ($data, $customer, $ownerId) {
            $bill = $this->customerBill($customer, $data['bill_id'] ?? null, $ownerId);
            $amount = round((float) $data['amount'], 2);
            $at = $this->paymentDate($data['payment_date'] ?? null);

            if ($bill && $amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => __('receivables.validation.bill_link_positive_only'),
                ]);
            }

            if ($bill) {
                return CustomerLedger::receiveForBill($bill, $amount, $data['type'], $data['note'] ?? null, $at);
            }

            if ($amount > 0) {
                return CustomerLedger::receive($customer, $amount, $data['type'], $data['note'] ?? null, $at);
            }

            return CustomerLedger::adjust($customer, $amount, $data['type'], $data['note'] ?? null, $at);
        });

        if ($request->expectsJson()) {
            $lastBillData = $customer->fresh()->getLastBillData($ownerId);

            return response()->json([
                'success' => true,
                'message' => __('messages.Payment added successfully'),
                'payment' => $payment,
                'new_balance' => $customer->fresh()->balance,
                'last_bill_amount' => $lastBillData['amount'],
                'last_bill_id' => $lastBillData['bill_id'],
                'customer' => $customer->fresh(),
            ]);
        }

        return redirect()->back()->with('success', __('messages.Payment added successfully'));
    }

    public function updatePayment(Request $request, CustomerPayment $customer_payment)
    {
        $user = auth()->user();
        if ($customer_payment->user_id !== $user->ownerId()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'type' => 'required|string|in:cash,card,transfer,check',
            'note' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($customer_payment, $validated) {
            $lockedRow = CustomerPayment::withoutGlobalScopes()->whereKey($customer_payment->id)->lockForUpdate()->firstOrFail();
            $customer = Customer::withoutGlobalScopes()->whereKey($lockedRow->customer_id)->lockForUpdate()->firstOrFail();
            $this->authorizeCustomerPaymentsWrite($customer);

            $signedAmount = round((float) $validated['amount'], 2);
            $kind = CustomerLedger::kindForRow($lockedRow);
            $bill = $lockedRow->bill_id
                ? Bill::withoutGlobalScopes()->whereKey($lockedRow->bill_id)->lockForUpdate()->first()
                : null;

            if ($kind === CustomerLedger::KIND_BILL_CHARGE) {
                throw ValidationException::withMessages([
                    'amount' => __('receivables.validation.bill_charge_cannot_be_edited'),
                ]);
            }

            if ($bill && $signedAmount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => __('receivables.validation.bill_link_positive_only'),
                ]);
            }

            if ($bill) {
                $summary = CustomerLedger::billSummary($bill, true);
                $newPaid = round($summary['paid'] - max(0, (float) $lockedRow->amount) + $signedAmount, 2);
                if ($newPaid > round((float) $summary['total'], 2)) {
                    throw ValidationException::withMessages([
                        'amount' => __('receivables.validation.bill_payment_exceeds_due'),
                    ]);
                }
            }

            CustomerLedger::updateRow($lockedRow, $signedAmount, $validated['type'], $validated['note'] ?? null);
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', __('receivables.messages.payment_updated'));
    }

    public function deletePayment(Request $request, CustomerPayment $payment)
    {
        $user = auth()->user();
        if ($payment->user_id !== $user->ownerId()) {
            abort(403, 'Unauthorized');
        }

        $customer = Customer::withoutGlobalScopes()->findOrFail($payment->customer_id);
        $this->authorizeCustomerPaymentsWrite($customer);

        try {
            CustomerLedger::deleteRow($payment);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? __('receivables.validation.bill_charge_cannot_be_deleted');

            return response()->json(['success' => false, 'message' => $message], 422);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', __('receivables.messages.payment_deleted'));
    }

    public function quickStorePayment(Request $request, Customer $customer)
    {
        $this->authorizeCustomerPaymentsWrite($customer);

        $validated = $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'type' => 'required|string|in:cash,card,transfer,check',
            'note' => 'nullable|string|max:255',
            'bill_id' => 'nullable|integer',
        ]);

        DB::transaction(function () use ($validated, $customer) {
            $bill = $this->customerBill($customer, $validated['bill_id'] ?? null, auth()->user()->ownerId());
            $amount = round((float) $validated['amount'], 2);

            if ($bill && $amount > 0) {
                CustomerLedger::receiveForBill($bill, $amount, $validated['type'], $validated['note'] ?? null);

                return;
            }

            if ($bill) {
                throw ValidationException::withMessages([
                    'amount' => __('receivables.validation.bill_link_positive_only'),
                ]);
            }

            if ($amount > 0) {
                CustomerLedger::receive($customer, $amount, $validated['type'], $validated['note'] ?? null);

                return;
            }

            CustomerLedger::adjust($customer, $amount, $validated['type'], $validated['note'] ?? null);
        });

        return response()->json([
            'success' => true,
            'message' => __('messages.Payment added successfully!'),
            'new_balance' => $customer->fresh()->balance,
        ]);
    }

    public function getRecentPayments(Customer $customer)
    {
        $this->authorizeCustomerPaymentsView($customer);

        $ownerId = auth()->user()->ownerId();
        $lastBillData = $customer->getLastBillData($ownerId);

        $payments = CustomerPayment::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('user_id', $ownerId)
            ->latest()
            ->take(10)
            ->get()
            ->map(function (CustomerPayment $payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'type' => $payment->type,
                    'note' => $payment->note,
                    'kind' => CustomerLedger::kindForRow($payment),
                    'created_at' => $payment->created_at->format('M d, Y H:i'),
                    'created_at_human' => $payment->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'payments' => $payments,
            'last_bill_amount' => $lastBillData['amount'],
            'last_bill_id' => $lastBillData['bill_id'],
            'last_bill_date' => $lastBillData['date'],
        ]);
    }

    private function statementData(Request $request, Customer $customer): array
    {
        $ownerId = auth()->user()->ownerId();
        $query = CustomerPayment::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('user_id', $ownerId)
            ->with('bill')
            ->orderBy('created_at')
            ->orderBy('id');

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $filterType = $request->input('type');
        if ($filterType) {
            $query->where(function ($rowQuery) use ($filterType) {
                $rowQuery->where('kind', $filterType);

                if ($filterType === CustomerLedger::KIND_BILL_CHARGE) {
                    $rowQuery->orWhere(function ($legacy) {
                        $legacy->whereNull('kind')->where('note', 'like', 'Bill #% created as debt');
                    });
                } elseif ($filterType === CustomerLedger::KIND_OPENING) {
                    $rowQuery->orWhere(function ($legacy) {
                        $legacy->whereNull('kind')->where('note', 'Initial balance');
                    });
                } elseif ($filterType === CustomerLedger::KIND_PAYMENT) {
                    $rowQuery->orWhere(function ($legacy) {
                        $legacy->whereNull('kind')->where('amount', '>', 0)->whereNull('bill_id');
                    });
                }
            });
        }

        $payments = $query->get();
        $running = 0.0;
        $runningBalance = [];

        foreach ($payments as $payment) {
            $running = round($running + (float) $payment->amount, 2);
            $runningBalance[$payment->id] = $running;
        }

        return [$payments, CustomerLedger::openBills($customer->fresh()), $runningBalance];
    }

    private function paymentDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->setTime(now()->hour, now()->minute, now()->second);
    }

    private function customerBill(Customer $customer, mixed $billId, int $ownerId): ?Bill
    {
        if (! $billId) {
            return null;
        }

        return Bill::withoutGlobalScopes()
            ->where('id', $billId)
            ->where('user_id', $ownerId)
            ->where('customer_id', $customer->id)
            ->firstOrFail();
    }

    private function authorizeCustomer(Customer $customer): void
    {
        $ownerId = auth()->user()->ownerId();
        if ($customer->user_id !== $ownerId) {
            abort(403, 'Unauthorized access to customer.');
        }
    }

    private function authorizeCustomerView(): void
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('view_customers')) {
            abort(403, 'Unauthorized');
        }
    }

    private function authorizeCustomerCreate(): void
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('create_customers')) {
            abort(403, 'Unauthorized');
        }
    }

    private function authorizeCustomerWrite(Customer $customer): void
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('edit_customers')) {
            abort(403, 'Unauthorized');
        }

        $this->authorizeCustomer($customer);
    }

    private function authorizeCustomerPaymentsView(Customer $customer): void
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! ($user->hasPermission('view_customers') || $user->hasPermission('manage_payments_receipts'))) {
            abort(403, 'Unauthorized');
        }

        $this->authorizeCustomer($customer);
    }

    private function authorizeCustomerPaymentsWrite(Customer $customer): void
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! ($user->hasPermission('manage_payments_receipts') || $user->hasPermission('edit_customers'))) {
            abort(403, 'Unauthorized');
        }

        $this->authorizeCustomer($customer);
    }
}
