<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBill;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\SupplierLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'view_suppliers');
        $ownerId = $this->ownerId($user);

        $query = Supplier::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->withCount(['purchaseBills', 'payments']);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('balance')) {
            match ($request->string('balance')->toString()) {
                'owed' => $query->where('balance', '>', 0),
                'credit' => $query->where('balance', '<', 0),
                'settled' => $query->where('balance', 0),
                default => null,
            };
        }

        $suppliers = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        $this->ensurePermission(request()->user(), 'create_suppliers');

        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'create_suppliers');
        $ownerId = $this->ownerId($user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'initial_balance' => 'nullable|numeric|min:-999999|max:999999',
        ]);

        DB::transaction(function () use ($validated, $ownerId) {
            $supplier = Supplier::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'balance' => 0,
                'user_id' => $ownerId,
            ]);

            $opening = round((float) ($validated['initial_balance'] ?? 0), 2);
            if ($opening != 0.0) {
                SupplierLedger::recordOpeningBalance($supplier, $opening);
            }
        });

        return redirect()->route('suppliers.index')->with('success', __('payables.flash.supplier_created'));
    }

    public function show(Supplier $supplier)
    {
        $this->ensurePermission(request()->user(), 'view_suppliers');
        $this->authorizeSupplier($supplier, request()->user());

        return redirect()->route('suppliers.edit', $supplier);
    }

    public function edit(Request $request, Supplier $supplier)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'edit_suppliers');
        $this->authorizeSupplier($supplier, $user);
        $ownerId = $this->ownerId($user);

        $paymentQuery = SupplierPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('supplier_id', $supplier->id)
            ->with('purchaseBill')
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        if ($request->filled('payment_kind')) {
            $kind = $request->string('payment_kind')->toString();
            if ($kind === 'cash_movement') {
                $paymentQuery->where(function ($query) {
                    $query->where(function ($inner) {
                        $inner->whereNull('kind')
                            ->where(function ($legacy) {
                                $legacy->whereNull('note')
                                    ->orWhere('note', '!=', SupplierLedger::LEGACY_OPENING_NOTE);
                            });
                    })->orWhere('kind', '!=', SupplierLedger::KIND_OPENING);
                });
            } elseif ($kind === SupplierLedger::KIND_OPENING) {
                $paymentQuery->where(function ($query) {
                    $query->where('kind', SupplierLedger::KIND_OPENING)
                        ->orWhere(function ($legacy) {
                            $legacy->whereNull('kind')
                                ->where('note', SupplierLedger::LEGACY_OPENING_NOTE);
                        });
                });
            } elseif ($kind === SupplierLedger::KIND_REFUND) {
                $paymentQuery->where(function ($query) {
                    $query->where('kind', SupplierLedger::KIND_REFUND)
                        ->orWhere(function ($legacy) {
                            $legacy->whereNull('kind')
                                ->where('amount', '<', 0)
                                ->where(function ($note) {
                                    $note->whereNull('note')
                                        ->orWhere('note', '!=', SupplierLedger::LEGACY_OPENING_NOTE);
                                });
                        });
                });
            } else {
                $paymentQuery->where(function ($query) use ($kind) {
                    $query->where('kind', $kind)
                        ->orWhere(function ($legacy) use ($kind) {
                            $legacy->whereNull('kind')
                                ->where('amount', '>', 0)
                                ->whereNull('purchase_bill_id')
                                ->where(function ($note) {
                                    $note->whereNull('note')
                                        ->orWhere('note', '!=', SupplierLedger::LEGACY_OPENING_NOTE);
                                });
                        });
                });
            }
        }
        if ($request->filled('date_from')) {
            $paymentQuery->whereDate('payment_date', '>=', $request->string('date_from'));
        }
        if ($request->filled('date_to')) {
            $paymentQuery->whereDate('payment_date', '<=', $request->string('date_to'));
        }

        $payments = $paymentQuery->paginate(25)->withQueryString();

        $runningBalanceMap = $this->runningBalanceMap($supplier);
        $payments->getCollection()->transform(function (SupplierPayment $payment) use ($runningBalanceMap) {
            $payment->setAttribute('running_balance', $runningBalanceMap[$payment->id] ?? null);

            return $payment;
        });

        $openBills = SupplierLedger::openBills($supplier);
        if ($request->filled('bill_status')) {
            $wanted = $request->string('bill_status')->toString();
            $openBills = $openBills->filter(fn ($row) => $row['status'] === $wanted)->values();
        }

        $recentBills = PurchaseBill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('supplier_id', $supplier->id)
            ->with('payments')
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (PurchaseBill $bill) {
                $bill->setAttribute('payables_summary', SupplierLedger::billSummary($bill));

                return $bill;
            });

        $statementPreview = $this->buildStatementRows($supplier)
            ->sortByDesc(fn (array $row) => $row['sort'])
            ->values()
            ->take(30);

        return view('suppliers.edit', compact('supplier', 'payments', 'openBills', 'recentBills', 'statementPreview'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'edit_suppliers');
        $this->authorizeSupplier($supplier, $user);

        if ($supplier->system_key === SupplierLedger::WALK_IN_KEY && $request->filled('name') && $request->string('name')->toString() !== $supplier->name) {
            throw ValidationException::withMessages([
                'name' => __('payables.validation.walk_in_rename_forbidden'),
            ]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.edit', $supplier)->with('success', __('payables.flash.supplier_updated'));
    }

    public function destroy(Supplier $supplier)
    {
        $user = request()->user();
        $this->ensurePermission($user, 'delete_suppliers');
        $this->authorizeSupplier($supplier, $user);

        if ($supplier->system_key === SupplierLedger::WALK_IN_KEY) {
            return redirect()->route('suppliers.index')->with('error', __('payables.flash.walk_in_delete_blocked'));
        }
        if ($supplier->purchaseBills()->exists()) {
            return redirect()->route('suppliers.index')->with('error', __('payables.flash.delete_supplier_with_bills_blocked'));
        }
        if ($supplier->payments()->exists()) {
            return redirect()->route('suppliers.index')->with('error', __('payables.flash.delete_supplier_with_payments_blocked'));
        }
        if ((float) $supplier->balance !== 0.0) {
            return redirect()->route('suppliers.index')->with('error', __('payables.flash.delete_supplier_with_balance_blocked'));
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', __('payables.flash.supplier_deleted'));
    }

    public function storePayment(Request $request, Supplier $supplier)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'edit_suppliers');
        $this->authorizeSupplier($supplier, $user);
        $ownerId = $this->ownerId($user);

        $validated = $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'type' => 'required|string|in:cash,card,transfer,check',
            'note' => 'nullable|string|max:500',
            'payment_date' => 'required|date',
            'purchase_bill_id' => 'nullable|integer',
        ]);

        $amount = round((float) $validated['amount'], 2);
        $payment = DB::transaction(function () use ($validated, $supplier, $ownerId, $amount) {
            $lockedSupplier = Supplier::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($supplier->id)
                ->lockForUpdate()
                ->firstOrFail();

            $bill = null;
            if (! empty($validated['purchase_bill_id'])) {
                $bill = PurchaseBill::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->where('supplier_id', $lockedSupplier->id)
                    ->whereKey((int) $validated['purchase_bill_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($amount <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => __('payables.validation.bill_link_requires_positive_payment'),
                    ]);
                }

                $summary = SupplierLedger::billSummary($bill);
                if ($amount > $summary['due']) {
                    throw ValidationException::withMessages([
                        'amount' => __('payables.validation.bill_payment_exceeds_remaining'),
                    ]);
                }
            }

            return $amount > 0
                ? SupplierLedger::pay(
                    $lockedSupplier,
                    $amount,
                    $validated['type'],
                    $validated['note'] ?? null,
                    Carbon::parse($validated['payment_date']),
                    $bill,
                )
                : SupplierLedger::recordPayment(
                    $lockedSupplier,
                    $amount,
                    $validated['type'],
                    $validated['note'] ?? null,
                    Carbon::parse($validated['payment_date']),
                    null,
                    SupplierLedger::KIND_REFUND,
                );
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('payables.flash.payment_recorded'),
                'payment' => $payment->fresh('purchaseBill'),
                'new_balance' => (float) $supplier->fresh()->balance,
            ]);
        }

        return redirect()->route('suppliers.edit', $supplier)->with('success', __('payables.flash.payment_recorded'));
    }

    public function getRecentPayments(Supplier $supplier)
    {
        $user = request()->user();
        $this->ensurePermission($user, 'view_suppliers');
        $ownerId = $this->ownerId($user);
        $this->authorizeSupplier($supplier, $user);

        $lastBillData = $supplier->getLastPurchaseBillData($ownerId);

        $payments = SupplierPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('supplier_id', $supplier->id)
            ->with('purchaseBill')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (SupplierPayment $payment) => $this->paymentJsonRow($payment));

        return response()->json([
            'payments' => $payments,
            'last_bill_amount' => $lastBillData['amount'],
            'last_bill_id' => $lastBillData['bill_id'],
            'last_bill_date' => $lastBillData['date'],
        ]);
    }

    public function getMorePayments(Supplier $supplier, Request $request)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'view_suppliers');
        $ownerId = $this->ownerId($user);
        $this->authorizeSupplier($supplier, $user);

        $offset = max(0, (int) $request->get('offset', 10));
        $limit = max(1, min(25, (int) $request->get('limit', 10)));

        $payments = SupplierPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('supplier_id', $supplier->id)
            ->with('purchaseBill')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn (SupplierPayment $payment) => $this->paymentJsonRow($payment));

        return response()->json([
            'payments' => $payments,
            'has_more' => $payments->count() === $limit,
        ]);
    }

    public function search(Request $request)
    {
        $this->ensurePermission($request->user(), 'view_suppliers');
        $ownerId = $this->ownerId($request->user());
        $search = trim((string) $request->query('search', ''));
        $includeSystem = $request->boolean('include_system');

        $query = Supplier::withoutGlobalScopes()
            ->where('user_id', $ownerId);

        if (! $includeSystem) {
            $query->where(function ($inner) {
                $inner->whereNull('system_key')
                    ->orWhere('system_key', '!=', SupplierLedger::WALK_IN_KEY);
            });
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->orderBy('name')->limit(20)->get();

        return response()->json($suppliers);
    }

    public function updatePayment(Request $request, SupplierPayment $supplierPayment)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'edit_suppliers');
        $ownerId = $this->ownerId($user);

        if ((int) $supplierPayment->user_id !== $ownerId) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'type' => 'required|string|in:cash,card,transfer,check',
            'note' => 'nullable|string|max:500',
            'payment_date' => 'required|date',
        ]);

        SupplierLedger::updatePayment(
            $supplierPayment,
            round((float) $validated['amount'], 2),
            $validated['type'],
            $validated['note'] ?? null,
            Carbon::parse($validated['payment_date']),
            $supplierPayment->purchase_bill_id
                ? PurchaseBill::withoutGlobalScopes()->find($supplierPayment->purchase_bill_id)
                : null,
            $supplierPayment->kind
        );

        return response()->json(['success' => true]);
    }

    public function quickStorePayment(Request $request, Supplier $supplier)
    {
        $request->headers->set('Accept', 'application/json');

        return $this->storePayment($request, $supplier);
    }

    public function deletePayment(Request $request, SupplierPayment $supplierPayment)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'edit_suppliers');
        $ownerId = $this->ownerId($user);

        if ((int) $supplierPayment->user_id !== $ownerId) {
            abort(403);
        }

        SupplierLedger::deletePayment($supplierPayment);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', __('payables.flash.payment_deleted'));
    }

    public function printSupplierReport(Request $request, Supplier $supplier)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'view_suppliers');
        $ownerId = $this->ownerId($user);
        $this->authorizeSupplier($supplier, $user);

        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'report_type' => 'required|in:both,bills,payments',
        ]);

        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        $purchaseBills = PurchaseBill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('supplier_id', $supplier->id)
            ->whereBetween('purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->with(['creator', 'payments'])
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get();

        $payments = SupplierPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('supplier_id', $supplier->id)
            ->whereBetween('payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->with('purchaseBill')
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $statementRows = $this->buildStatementRows($supplier)
            ->filter(function (array $row) use ($dateFrom, $dateTo) {
                return $row['date']->between($dateFrom, $dateTo, true);
            })
            ->values();

        return view('suppliers.print-report', [
            'supplier' => $supplier,
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'report_type' => $validated['report_type'],
            'generated_at' => now(),
            'generated_by' => $user->name,
            'purchase_bills' => $purchaseBills,
            'payments' => $payments,
            'bills_total' => round((float) $purchaseBills->sum('total_amount'), 2),
            'payments_total' => round((float) $payments->filter(fn (SupplierPayment $payment) => SupplierLedger::kindForRow($payment) !== SupplierLedger::KIND_OPENING)->sum('amount'), 2),
            'statement_rows' => $statementRows,
        ]);
    }

    private function buildStatementRows(Supplier $supplier): Collection
    {
        $bills = PurchaseBill::withoutGlobalScopes()
            ->where('user_id', $supplier->user_id)
            ->where('supplier_id', $supplier->id)
            ->with('creator')
            ->get()
            ->toBase()
            ->map(function (PurchaseBill $bill) {
                return [
                    'date' => Carbon::parse($bill->purchase_date),
                    'sort' => Carbon::parse($bill->purchase_date)->format('Y-m-d') . '-bill-' . str_pad((string) $bill->id, 12, '0', STR_PAD_LEFT),
                    'type' => 'bill',
                    'description' => __('payables.statement.bill_entry', ['id' => $bill->id]),
                    'reference' => $bill->reference_number,
                    'increase' => round((float) $bill->total_amount, 2),
                    'decrease' => 0.0,
                    'balance_change' => round((float) $bill->total_amount, 2),
                    'bill' => $bill,
                    'payment' => null,
                ];
            });

        $payments = SupplierPayment::withoutGlobalScopes()
            ->where('user_id', $supplier->user_id)
            ->where('supplier_id', $supplier->id)
            ->with('purchaseBill')
            ->get()
            ->toBase()
            ->map(function (SupplierPayment $payment) {
                $effectiveKind = SupplierLedger::kindForRow($payment);
                $amount = round((float) $payment->amount, 2);
                $balanceChange = $effectiveKind === SupplierLedger::KIND_OPENING ? $amount : -1 * $amount;

                return [
                    'date' => Carbon::parse($payment->payment_date),
                    'sort' => Carbon::parse($payment->payment_date)->format('Y-m-d') . '-payment-' . str_pad((string) $payment->id, 12, '0', STR_PAD_LEFT),
                    'type' => 'payment',
                    'description' => __('payables.statement.payment_entry', ['kind' => __('payables.kinds.' . $this->paymentKindKey($payment))]),
                    'reference' => $payment->purchaseBill ? '#' . $payment->purchaseBill->id : null,
                    'increase' => $effectiveKind === SupplierLedger::KIND_OPENING ? max(0, $amount) : max(0, -1 * $amount),
                    'decrease' => $effectiveKind === SupplierLedger::KIND_OPENING ? max(0, -1 * $amount) : max(0, $amount),
                    'balance_change' => round($balanceChange, 2),
                    'bill' => $payment->purchaseBill,
                    'payment' => $payment,
                ];
            });

        $rows = $bills->merge($payments)->sortBy(fn (array $row) => $row['sort'])->values();

        $running = 0.0;
        return $rows->map(function (array $row) use (&$running) {
            $running = round($running + $row['balance_change'], 2);
            $row['running_balance'] = $running;

            return $row;
        });
    }

    private function runningBalanceMap(Supplier $supplier): array
    {
        $map = [];
        foreach ($this->buildStatementRows($supplier) as $row) {
            if ($row['payment']) {
                $map[$row['payment']->id] = $row['running_balance'];
            }
        }

        return $map;
    }

    private function paymentJsonRow(SupplierPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'type' => $payment->type,
            'note' => $payment->note,
            'payment_date' => optional($payment->payment_date)->format('M d, Y'),
            'created_at_human' => optional($payment->created_at)->diffForHumans(),
            'kind' => $this->paymentKindKey($payment),
            'purchase_bill_id' => $payment->purchase_bill_id,
        ];
    }

    private function paymentKindKey(SupplierPayment $payment): string
    {
        return match (SupplierLedger::kindForRow($payment)) {
            SupplierLedger::KIND_BILL_PAYMENT => 'bill_payment',
            SupplierLedger::KIND_OPENING => 'opening_balance',
            SupplierLedger::KIND_REFUND => 'refund',
            default => 'payment',
        };
    }

    private function authorizeSupplier(Supplier $supplier, $user): void
    {
        if ((int) $supplier->user_id !== $this->ownerId($user)) {
            abort(403);
        }
    }

    private function ensurePermission($user, string $permission): void
    {
        if ($user->role === 'employee' && ! $user->hasPermission($permission)) {
            abort(403);
        }
    }

    private function ownerId($user): int
    {
        return (int) ($user->role === 'employee' ? $user->shop_owner_id : $user->id);
    }
}
