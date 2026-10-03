<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductImei;
use App\Models\PurchaseBill;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\SupplierLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseBillController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'view_purchase_bills');
        $ownerId = $this->ownerId($user);

        $query = PurchaseBill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->with(['supplier', 'creator', 'payments'])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id');

        if ($request->filled('date')) {
            $query->whereDate('purchase_date', $request->string('date'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('purchase_date', '>=', $request->string('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('purchase_date', '<=', $request->string('date_to'));
        }
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($inner) use ($search) {
                $inner->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                        $supplierQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $paymentStatus = $request->string('payment_status')->toString();
        if ($paymentStatus !== '') {
            $matchingBills = (clone $query)
                ->select(['purchase_bills.id', 'purchase_bills.user_id', 'purchase_bills.supplier_id', 'purchase_bills.total_amount', 'purchase_bills.purchase_date'])
                ->get();
            $summaries = SupplierLedger::summariesForBills($matchingBills);
            $filteredBills = $matchingBills->filter(function (PurchaseBill $bill) use ($paymentStatus, $summaries) {
                return ($summaries[$bill->id]['status'] ?? null) === $paymentStatus;
            })->values();

            $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
            $perPage = 25;
            $pageIds = $filteredBills->slice(($currentPage - 1) * $perPage, $perPage)->pluck('id')->all();
            $pageItems = PurchaseBill::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->with(['supplier', 'creator', 'payments'])
                ->whereIn('id', $pageIds)
                ->get()
                ->sortBy(fn (PurchaseBill $bill) => array_search($bill->id, $pageIds, true))
                ->values();

            $bills = new \Illuminate\Pagination\LengthAwarePaginator(
                $pageItems,
                $filteredBills->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            $bills->getCollection()->transform(function (PurchaseBill $bill) use ($summaries) {
                $bill->setAttribute('payables_summary', $summaries[$bill->id] ?? $this->summarizeBill($bill));

                return $bill;
            });
        } else {
            $bills = $query->paginate(25)->withQueryString();

            $pageSummaries = SupplierLedger::summariesForBills($bills->getCollection());
            $bills->getCollection()->transform(function (PurchaseBill $bill) use ($pageSummaries) {
                $bill->setAttribute('payables_summary', $pageSummaries[$bill->id] ?? $this->summarizeBill($bill));

                return $bill;
            });
        }

        $suppliers = Supplier::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->orderBy('name')
            ->get();

        $totalAmount = $bills->getCollection()->sum(fn (PurchaseBill $bill) => (float) $bill->total_amount);
        $dueAmount = $bills->getCollection()->sum(fn (PurchaseBill $bill) => (float) ($bill->payables_summary['due'] ?? 0));

        return view('purchase-bills.index', compact('bills', 'suppliers', 'totalAmount', 'dueAmount'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'create_purchase_bills');
        $ownerId = $this->ownerId($user);

        $suppliers = Supplier::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where(function ($query) {
                $query->whereNull('system_key')
                    ->orWhere('system_key', '!=', SupplierLedger::WALK_IN_KEY);
            })
            ->orderBy('name')
            ->get();

        $products = Product::withoutGlobalScopes()->where('user_id', $ownerId)->orderBy('name')->get();

        $duplicatedBill = null;
        if ($request->filled('duplicate')) {
            $duplicatedBill = PurchaseBill::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->with(['supplier', 'products'])
                ->find($request->integer('duplicate'));
        }

        return view('purchase-bills.create', compact('suppliers', 'products', 'duplicatedBill'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'create_purchase_bills');
        $ownerId = $this->ownerId($user);

        $validated = $this->validateBillRequest($request, $ownerId);

        try {
            $purchaseBill = DB::transaction(function () use ($validated, $request, $user, $ownerId) {
                $supplier = Supplier::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->findOrFail((int) $validated['supplier_id']);

                $purchaseBill = PurchaseBill::create([
                    'supplier_id' => $supplier->id,
                    'user_id' => $ownerId,
                    'purchase_date' => $validated['purchase_date'],
                    'reference_number' => $validated['reference_number'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'total_amount' => 0,
                    'created_by' => $user->id,
                ]);

                $totalAmount = $this->syncBillProducts($purchaseBill, $request, $ownerId, false);
                $purchaseBill->update(['total_amount' => $totalAmount]);

                SupplierLedger::charge($supplier, $totalAmount);

                $this->recordInitialPayment($purchaseBill, $supplier, $validated, $totalAmount);

                return $purchaseBill;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['error' => __('payables.flash.purchase_bill_create_failed')])
                ->withInput();
        }

        return redirect()
            ->route('purchase-bills.show', $purchaseBill)
            ->with('success', __('payables.flash.purchase_bill_created'));
    }

    public function show(PurchaseBill $purchaseBill)
    {
        $user = request()->user();
        $this->ensurePermission($user, 'view_purchase_bills');
        $this->authorizePurchaseBill($purchaseBill, $user);

        $purchaseBill->load(['supplier', 'products', 'creator', 'payments']);
        $summary = $this->summarizeBill($purchaseBill);

        return view('purchase-bills.show', [
            'purchaseBill' => $purchaseBill,
            'summary' => $summary,
            'printMode' => false,
        ]);
    }

    public function edit(PurchaseBill $purchaseBill)
    {
        $user = request()->user();
        $this->ensurePermission($user, 'edit_purchase_bills');
        $this->authorizePurchaseBill($purchaseBill, $user);
        $ownerId = $this->ownerId($user);

        $suppliers = Supplier::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where(function ($query) use ($purchaseBill) {
                $query->whereNull('system_key')
                    ->orWhere('system_key', '!=', SupplierLedger::WALK_IN_KEY)
                    ->orWhere('id', $purchaseBill->supplier_id);
            })
            ->orderBy('name')
            ->get();

        $products = Product::withoutGlobalScopes()->where('user_id', $ownerId)->orderBy('name')->get();

        $purchaseBill->load(['supplier', 'products', 'payments']);
        $summary = $this->summarizeBill($purchaseBill);

        return view('purchase-bills.edit', compact('purchaseBill', 'suppliers', 'products', 'summary'));
    }

    public function update(Request $request, PurchaseBill $purchaseBill)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'edit_purchase_bills');
        $this->authorizePurchaseBill($purchaseBill, $user);
        $ownerId = $this->ownerId($user);

        $validated = $this->validateBillRequest($request, $ownerId);
        $purchaseBill->load(['products', 'payments', 'supplier']);

        if ($purchaseBill->payments->isNotEmpty() && (int) $validated['supplier_id'] !== (int) $purchaseBill->supplier_id) {
            throw ValidationException::withMessages([
                'supplier_id' => __('payables.validation.cannot_change_bill_supplier_with_payments'),
            ]);
        }

        $oldTotalAmount = round((float) $purchaseBill->total_amount, 2);
        $newSupplierId = (int) $validated['supplier_id'];

        try {
            DB::transaction(function () use ($purchaseBill, $request, $validated, $ownerId, $oldTotalAmount, $newSupplierId) {
                foreach ($purchaseBill->products as $product) {
                    $this->removeFromStorage(
                        $product,
                        (float) $product->pivot->quantity,
                        (float) $product->pivot->unit_cost,
                        $ownerId
                    );
                }

                $oldSupplier = Supplier::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->findOrFail($purchaseBill->supplier_id);
                SupplierLedger::adjustBalance($oldSupplier, -1 * $oldTotalAmount);

                $purchaseBill->products()->detach();

                $purchaseBill->fill([
                    'supplier_id' => $newSupplierId,
                    'purchase_date' => $validated['purchase_date'],
                    'reference_number' => $validated['reference_number'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);
                $purchaseBill->save();

                $totalAmount = $this->syncBillProducts($purchaseBill, $request, $ownerId, true);
                $purchaseBill->update(['total_amount' => $totalAmount]);

                $newSupplier = Supplier::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->findOrFail($newSupplierId);
                SupplierLedger::charge($newSupplier, $totalAmount);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['error' => __('payables.flash.purchase_bill_update_failed')])
                ->withInput();
        }

        $purchaseBill->refresh()->load('payments');
        $summary = SupplierLedger::billSummary($purchaseBill);
        $message = $summary['overpaid'] > 0
            ? __('payables.flash.purchase_bill_updated_with_credit', ['amount' => number_format($summary['overpaid'], 2)])
            : __('payables.flash.purchase_bill_updated');

        return redirect()
            ->route('purchase-bills.show', $purchaseBill)
            ->with('success', $message);
    }

    public function destroy(PurchaseBill $purchaseBill)
    {
        $user = request()->user();
        $this->ensurePermission($user, 'delete_purchase_bills');
        $this->authorizePurchaseBill($purchaseBill, $user);
        $ownerId = $this->ownerId($user);

        if ($purchaseBill->payments()->exists()) {
            return back()->with('error', __('payables.flash.purchase_bill_delete_blocked_with_payments'));
        }

        try {
            DB::transaction(function () use ($purchaseBill, $ownerId) {
                $purchaseBill->load('products');

                foreach ($purchaseBill->products as $product) {
                    $this->removeFromStorage(
                        $product,
                        (float) $product->pivot->quantity,
                        (float) $product->pivot->unit_cost,
                        $ownerId
                    );
                }

                $supplier = Supplier::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->findOrFail($purchaseBill->supplier_id);
                SupplierLedger::adjustBalance($supplier, -1 * round((float) $purchaseBill->total_amount, 2));

                $purchaseBill->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', __('payables.flash.purchase_bill_delete_failed'));
        }

        return redirect()
            ->route('purchase-bills.index')
            ->with('success', __('payables.flash.purchase_bill_deleted'));
    }

    public function print(PurchaseBill $purchaseBill)
    {
        $user = request()->user();
        $this->ensurePermission($user, 'view_purchase_bills');
        $this->authorizePurchaseBill($purchaseBill, $user);

        $purchaseBill->load(['supplier', 'products', 'creator', 'payments']);
        $summary = $this->summarizeBill($purchaseBill);

        return view('purchase-bills.print', compact('purchaseBill', 'summary'));
    }

    public function duplicate(PurchaseBill $purchaseBill)
    {
        $user = request()->user();
        $this->ensurePermission($user, 'create_purchase_bills');
        $this->authorizePurchaseBill($purchaseBill, $user);

        return redirect()->route('purchase-bills.create', ['duplicate' => $purchaseBill->id]);
    }

    public function search(Request $request)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'view_purchase_bills');
        $ownerId = $this->ownerId($user);
        $search = trim((string) $request->query('search', ''));

        $query = PurchaseBill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->with('supplier')
            ->orderByDesc('purchase_date')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                        $supplierQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $bills = $query->limit(20)->get()->map(function (PurchaseBill $bill) {
            return [
                'id' => $bill->id,
                'reference_number' => $bill->reference_number,
                'purchase_date' => optional($bill->purchase_date)->format('Y-m-d'),
                'total_amount' => (float) $bill->total_amount,
                'supplier_name' => $bill->supplier?->name,
                'label' => '#' . $bill->id . ' - ' . ($bill->supplier?->name ?? ''),
            ];
        });

        return response()->json($bills);
    }

    public function storePayment(Request $request, PurchaseBill $purchaseBill)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'edit_purchase_bills');
        $this->authorizePurchaseBill($purchaseBill, $user);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'type' => ['required', 'in:cash,card,transfer,check'],
            'payment_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $amount = round((float) $validated['amount'], 2);

        DB::transaction(function () use ($purchaseBill, $validated, $amount, $user) {
            $lockedBill = PurchaseBill::withoutGlobalScopes()
                ->where('user_id', $this->ownerId($user))
                ->whereKey($purchaseBill->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSupplier = Supplier::withoutGlobalScopes()
                ->where('user_id', $lockedBill->user_id)
                ->whereKey($lockedBill->supplier_id)
                ->lockForUpdate()
                ->firstOrFail();

            $summary = SupplierLedger::billSummary($lockedBill);
            if ($amount > $summary['due']) {
                throw ValidationException::withMessages([
                    'amount' => __('payables.validation.bill_payment_exceeds_remaining'),
                ]);
            }

            SupplierLedger::pay(
                $lockedSupplier,
                $amount,
                $validated['type'],
                $validated['note'] ?? null,
                Carbon::parse($validated['payment_date']),
                $lockedBill,
            );
        });

        return redirect()
            ->route('purchase-bills.show', $purchaseBill)
            ->with('success', __('payables.flash.bill_payment_recorded'));
    }

    public function destroyPayment(PurchaseBill $purchaseBill, SupplierPayment $supplierPayment)
    {
        $user = request()->user();
        $this->ensurePermission($user, 'edit_purchase_bills');
        $this->authorizePurchaseBill($purchaseBill, $user);

        if ((int) $supplierPayment->purchase_bill_id !== (int) $purchaseBill->id || (int) $supplierPayment->user_id !== $this->ownerId($user)) {
            abort(403);
        }

        SupplierLedger::deletePayment($supplierPayment);

        return redirect()
            ->route('purchase-bills.show', $purchaseBill)
            ->with('success', __('payables.flash.bill_payment_deleted'));
    }

    private function validateBillRequest(Request $request, int $ownerId): array
    {
        return $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id,user_id,' . $ownerId,
            'purchase_date' => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|integer|exists:products,id,user_id,' . $ownerId,
            'quantities' => 'required|array',
            'quantities.*' => 'required|numeric|min:0.01',
            'unit_costs' => 'required|array',
            'unit_costs.*' => 'required|numeric|min:0',
            'paid_now' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,card,transfer,check',
            'payment_date' => 'nullable|date',
            'payment_note' => 'nullable|string|max:500',
        ]);
    }

    private function recordInitialPayment(PurchaseBill $purchaseBill, Supplier $supplier, array $validated, float $totalAmount): void
    {
        $paidNow = round((float) ($validated['paid_now'] ?? 0), 2);
        if ($paidNow <= 0) {
            return;
        }

        if ($paidNow > $totalAmount) {
            throw ValidationException::withMessages([
                'paid_now' => __('payables.validation.initial_payment_exceeds_total'),
            ]);
        }

        SupplierLedger::pay(
            $supplier,
            $paidNow,
            $validated['payment_method'] ?? 'cash',
            $validated['payment_note'] ?? null,
            Carbon::parse($validated['payment_date'] ?? $validated['purchase_date']),
            $purchaseBill,
        );
    }

    private function summarizeBill(PurchaseBill $bill): array
    {
        return SupplierLedger::billSummary($bill);
    }

    private function syncBillProducts(PurchaseBill $purchaseBill, Request $request, int $ownerId, bool $isUpdate): float
    {
        $totalAmount = 0.0;

        foreach ($request->input('product_ids', []) as $index => $productId) {
            $quantity = round((float) $request->input("quantities.{$index}"), 2);
            $unitCost = round((float) $request->input("unit_costs.{$index}"), 2);
            $barcodes = array_values(array_filter($request->input("barcodes_{$productId}", []), fn ($value) => trim((string) $value) !== ''));
            $totalCost = round($quantity * $unitCost, 2);
            $totalAmount += $totalCost;

            $purchaseBill->products()->attach($productId, [
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'barcodes' => json_encode($barcodes),
            ]);

            foreach ($barcodes as $barcode) {
                $barcode = trim((string) $barcode);
                if ($barcode === '') {
                    continue;
                }

                $exists = ProductBarcode::withoutGlobalScopes()
                    ->where('product_id', $productId)
                    ->where('barcode', $barcode)
                    ->exists();

                if (! $exists) {
                    ProductBarcode::create([
                        'product_id' => $productId,
                        'barcode' => $barcode,
                    ]);
                }
            }

            if (! $isUpdate) {
                $imeiCodes = $request->input("imeis_{$productId}", []);
                if (is_array($imeiCodes)) {
                    foreach ($imeiCodes as $imeiCode) {
                        $imeiCode = trim((string) $imeiCode);
                        if ($imeiCode === '') {
                            continue;
                        }

                        $existingImei = ProductImei::withoutGlobalScopes()
                            ->where('user_id', $ownerId)
                            ->where('imei', $imeiCode)
                            ->first();

                        if (! $existingImei) {
                            ProductImei::create([
                                'user_id' => $ownerId,
                                'product_id' => $productId,
                                'imei' => $imeiCode,
                                'supplier_id' => $purchaseBill->supplier_id,
                                'purchase_bill_id' => $purchaseBill->id,
                                'unit_cost' => $unitCost,
                                'purchased_at' => $purchaseBill->purchase_date,
                            ]);
                        }
                    }
                }
            }

            $product = Product::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->findOrFail($productId);

            $this->addToStorage($product, $quantity, $unitCost, $ownerId);
        }

        return round($totalAmount, 2);
    }

    private function addToStorage($product, $quantity, $unitCost, $ownerId): void
    {
        $oldQty = (float) $product->quantity;
        $oldAvgCost = (float) $product->cost_price;

        $product->quantity += $quantity;
        if ($oldQty <= 0) {
            $product->cost_price = $unitCost;
        } else {
            $product->cost_price = ($oldAvgCost * $oldQty + $unitCost * $quantity) / $product->quantity;
        }

        $product->cost_price = round($product->cost_price, 2);
        $product->save();

        $existingBatch = Batch::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('cost_price', $unitCost)
            ->where('user_id', $ownerId)
            ->orderBy('created_at')
            ->first();

        if ($existingBatch) {
            $existingBatch->quantity += $quantity;
            $existingBatch->save();

            return;
        }

        Batch::create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'cost_price' => $unitCost,
            'user_id' => $ownerId,
        ]);
    }

    private function removeFromStorage($product, $quantity, $unitCost, $ownerId): void
    {
        $previousQuantity = (float) $product->quantity;
        $previousAvgCost = (float) $product->cost_price;

        $product->quantity -= $quantity;

        if ($product->quantity <= 0) {
            $product->cost_price = 0;
        } else {
            $totalCostBefore = $previousAvgCost * $previousQuantity;
            $removedTotalCost = $unitCost * $quantity;
            $remainingTotalCost = $totalCostBefore - $removedTotalCost;

            if ($remainingTotalCost > 0) {
                $product->cost_price = $remainingTotalCost / $product->quantity;
            } else {
                $batches = $product->batches()->where('quantity', '>', 0)->get();
                if ($batches->count() > 0) {
                    $totalCost = 0;
                    $totalQty = 0;
                    foreach ($batches as $batch) {
                        $totalCost += $batch->cost_price * $batch->quantity;
                        $totalQty += $batch->quantity;
                    }
                    $product->cost_price = $totalQty > 0 ? $totalCost / $totalQty : 0;
                } else {
                    $product->cost_price = 0;
                }
            }
        }

        $product->cost_price = round($product->cost_price, 2);
        $product->save();

        $batch = $product->batches()
            ->where('cost_price', $unitCost)
            ->where('user_id', $ownerId)
            ->first();

        if (! $batch) {
            return;
        }

        if ($batch->quantity <= $quantity) {
            $batch->delete();

            return;
        }

        $batch->quantity -= $quantity;
        $batch->save();
    }

    private function authorizePurchaseBill(PurchaseBill $purchaseBill, $user): void
    {
        if ((int) $purchaseBill->user_id !== $this->ownerId($user)) {
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
