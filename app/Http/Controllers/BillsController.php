<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\BillService;
use App\Services\CustomerLedger;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillsController extends Controller
{
    public function getTags(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $tags = \App\Models\Tag::where('user_id', $ownerId)->get();

        return response()->json($tags);
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('view_bills')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->ownerId();
        $paymentStatus = (string) $request->input('payment_status', '');
        $dateScope = $this->resolveIndexDateScope($request, $ownerId);
        // Every query below reads the resolved range from the request.
        $request->merge([
            'date' => $dateScope['from'],
            'date_to' => $dateScope['to'],
            'all_dates' => $dateScope['all'] ? '1' : null,
        ]);

        $perPage = 50;
        $filteredPaid = 0.0;
        $filteredDue = 0.0;
        $totalSales = 0.0;
        $totalProfit = 0.0;

        if ($paymentStatus === '') {
            $bills = $this->buildBillIndexQuery($ownerId, $request)
                ->with(['products', 'customer', 'creator'])
                ->paginate($perPage)
                ->withQueryString();

            $summaries = $this->paymentSummariesForCollection($bills->getCollection(), $ownerId);
            $bills->getCollection()->transform(function (Bill $bill) use ($summaries) {
                $bill->payment_summary = $summaries[$bill->id] ?? CustomerLedger::billSummary($bill);

                return $bill;
            });

            $pageIds = $bills->getCollection()->pluck('id')->all();
            $totalSales = round((float) (($this->billTotalsBaseQuery($ownerId, $request)->sum('bills.total_price')) ?? 0), 2);
            $totalProfit = $this->filteredProfit($ownerId, $request);
            [$filteredPaid, $filteredDue] = $this->filteredLedgerTotals($ownerId, $request);
        } else {
            $baseQuery = $this->buildBillIndexQuery($ownerId, $request);
            $matchingBills = (clone $baseQuery)
                ->select(['bills.id', 'bills.user_id', 'bills.customer_id', 'bills.total_price', 'bills.created_at'])
                ->get();
            $summaries = $this->paymentSummariesForCollection($matchingBills, $ownerId);
            $filteredBills = $matchingBills->filter(function (Bill $bill) use ($paymentStatus, $summaries) {
                $summary = $summaries[$bill->id] ?? CustomerLedger::billSummary($bill);

                return match ($paymentStatus) {
                    'cash' => $summary['status'] === 'cash',
                    'paid' => $summary['status'] === 'paid',
                    'partial' => $summary['status'] === 'partial',
                    'unpaid' => $summary['status'] === 'unpaid',
                    default => true,
                };
            })->values();

            $filteredIds = $filteredBills->pluck('id')->all();
            $totalSales = round((float) $filteredBills->sum('total_price'), 2);
            $totalProfit = empty($filteredIds)
                ? 0.0
                : round((float) (DB::table('bills')
                    ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
                    ->whereIn('bills.id', $filteredIds)
                    ->selectRaw('SUM((bill_product.selling_price - bill_product.cost_price) * bill_product.quantity - bill_product.discount) as profit')
                    ->value('profit') ?? 0), 2);

            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $pageIds = $filteredBills->slice(($currentPage - 1) * $perPage, $perPage)->pluck('id')->all();
            $pageBills = Bill::withoutGlobalScopes()
                ->with(['products', 'customer', 'creator'])
                ->whereIn('id', $pageIds)
                ->get()
                ->sortBy(fn (Bill $bill) => array_search($bill->id, $pageIds, true))
                ->values();

            $bills = new LengthAwarePaginator(
                $pageBills,
                $filteredBills->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            $bills->getCollection()->transform(function (Bill $bill) use ($summaries) {
                $bill->payment_summary = $summaries[$bill->id] ?? CustomerLedger::billSummary($bill);

                return $bill;
            });

            $filteredPaid = round((float) $filteredBills->sum(fn (Bill $bill) => $summaries[$bill->id]['paid'] ?? 0), 2);
            $filteredDue = round((float) $filteredBills->sum(fn (Bill $bill) => $summaries[$bill->id]['due'] ?? 0), 2);
        }

        // Handle AJAX requests
        if ($request->ajax()) {
            return response()->json([
                'bills' => $bills->values()->all(),
                'pagination' => [
                    'current_page' => $bills->currentPage(),
                    'last_page' => $bills->lastPage(),
                    'total' => $bills->total(),
                ]
            ]);
        }

        return view('bills.index', [
            'bills' => $bills,
            'totalSales' => $totalSales,
            'totalProfit' => $totalProfit,
            'selectedDate' => $dateScope['from'],
            'selectedDateTo' => $dateScope['to'],
            'allDates' => $dateScope['all'],
            'dateScope' => $dateScope,
            'paymentStatus' => $paymentStatus,
            'filteredPaid' => $filteredPaid,
            'filteredDue' => $filteredDue,
        ]);
    }

    public function create()
    {
        return redirect()->route('dashboard');
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('create_bills')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'product_ids' => 'required|array|min:1',
            'quantities' => 'required|array',
            'discounts' => 'required|array',
            'cost_prices' => 'required|array',
            'selling_prices' => 'required|array',
            'note' => 'nullable|string|max:1000',
            'customer_id' => 'nullable|exists:customers,id,user_id,' . $user->ownerId(),
            'discount_types' => 'array',
            'bill_date' => 'nullable|date',
            'return_costs' => 'nullable|array',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|in:cash,card,transfer,check',
            'client_uuid' => 'nullable|string|max:64',
        ]);

        $result = app(BillService::class)->create($user, $request->all());
        $bill = $result['bill'];
        $summary = CustomerLedger::billSummary($bill);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $result['duplicate'] ? __('receivables.messages.bill_duplicate') : __('messages.Bill created successfully!'),
                'bill' => $bill,
                'duplicate' => $result['duplicate'],
                'paid' => $summary['paid'],
                'due' => $summary['due'],
                'customer_balance' => $bill->customer?->fresh()?->balance,
            ]);
        }

        return redirect()->route('dashboard')->with('success', $result['duplicate'] ? __('receivables.messages.bill_duplicate') : __('messages.Bill created successfully!'));
    }

    public function show(Bill $bill)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('view_bills')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        if ($bill->user_id !== $ownerId) {
            abort(403, 'Unauthorized');
        }

        $bill->load(['products', 'customer', 'creator']);
        $summary = CustomerLedger::billSummary($bill);
        $ledgerRows = $this->billLedgerRows($bill, $ownerId);

        if (request()->expectsJson()) {
            return response()->json([
                'bill' => $bill,
                'items' => $bill->products->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'quantity' => (float) $product->pivot->quantity,
                    'discount' => (float) $product->pivot->discount,
                    'cost_price' => (float) $product->pivot->cost_price,
                    'selling_price' => (float) $product->pivot->selling_price,
                    'tags' => $product->pivot->tags,
                    'imeis' => $product->pivot->imeis ? json_decode($product->pivot->imeis, true) : [],
                ])->values(),
                'ledger_summary' => $summary,
                'ledger_rows' => $ledgerRows->map(fn (CustomerPayment $row) => [
                    'id' => $row->id,
                    'amount' => (float) $row->amount,
                    'type' => $row->type,
                    'note' => $row->note,
                    'bill_id' => $row->bill_id,
                    'kind' => CustomerLedger::kindForRow($row),
                    'kind_label' => match (CustomerLedger::kindForRow($row)) {
                        CustomerLedger::KIND_BILL_CHARGE => __('receivables.bill_charge'),
                        CustomerLedger::KIND_BILL_PAYMENT => __('receivables.bill_payment'),
                        CustomerLedger::KIND_OPENING => __('receivables.opening_balance'),
                        CustomerLedger::KIND_ADJUSTMENT => __('receivables.adjustment'),
                        default => __('receivables.general_payment'),
                    },
                    'created_at' => optional($row->created_at)->toDateTimeString(),
                ])->values(),
            ]);
        }

        $products = Product::where('user_id', $ownerId)
            ->where('is_active', true)
            ->with('barcodes')
            ->get();

        $canManageBillPayments = $user->role !== 'employee'
            || $user->hasPermission('manage_payments_receipts')
            || $user->hasPermission('edit_bills');

        return view('bills.show', compact('bill', 'products', 'summary', 'ledgerRows', 'canManageBillPayments'));
    }

    /** Compact JSON used by the quick-review dialog of the bills list. */
    public function preview(Bill $bill)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('view_bills')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->ownerId();
        if ((int) $bill->user_id !== (int) $ownerId) {
            abort(403, 'Unauthorized');
        }

        $bill->load(['products', 'customer', 'creator']);
        $summary = CustomerLedger::billSummary($bill);

        $items = $bill->products->map(function ($product) {
            $tags = collect(explode('&', (string) ($product->pivot->tags ?? '')))
                ->filter(fn ($pair) => str_contains($pair, '@'))
                ->map(function ($pair) {
                    [$name, $price] = array_pad(explode('@', $pair, 2), 2, 0);

                    return ['name' => $name, 'price' => round((float) $price, 2)];
                })
                ->values();
            $quantity = (float) $product->pivot->quantity;
            $unit = (float) $product->pivot->selling_price + (float) $tags->sum('price');
            $discount = (float) ($product->pivot->discount ?? 0);
            $imeis = $product->pivot->imeis ? json_decode($product->pivot->imeis, true) : [];

            return [
                'name' => $product->name,
                'barcode' => $product->barcode,
                'quantity' => $quantity,
                'unit_price' => round($unit, 2),
                'discount' => round($discount, 2),
                'line_total' => round($unit * $quantity - $discount, 2),
                'tags' => $tags->all(),
                'imeis' => is_array($imeis) ? array_values($imeis) : [],
            ];
        })->values();

        $status = $summary['status'] ?? 'cash';

        return response()->json([
            'id' => $bill->id,
            'created_at' => ShopTime::local($bill->created_at, $ownerId)->format('Y-m-d H:i'),
            'customer' => $bill->customer?->name,
            'customer_phone' => $bill->customer?->phone,
            'creator' => $bill->creator?->name,
            'payment_method' => __('messages.' . ucfirst($bill->payment_method ?: 'cash')),
            'note' => $bill->note,
            'is_returned' => (bool) $bill->is_returned,
            'is_damaged' => (bool) $bill->is_damaged,
            'items' => $items,
            'items_count' => $items->count(),
            'quantity_total' => round((float) $items->sum('quantity'), 3),
            'subtotal' => round((float) $items->sum(fn ($item) => $item['unit_price'] * $item['quantity']), 2),
            'discount_total' => round((float) $items->sum('discount'), 2),
            'total' => round((float) $bill->total_price, 2),
            'paid' => round((float) ($summary['paid'] ?? 0), 2),
            'due' => round((float) ($summary['due'] ?? 0), 2),
            'status' => $status,
            'status_label' => match ($status) {
                'paid' => __('receivables.paid'),
                'partial' => __('receivables.partial'),
                'unpaid' => __('receivables.unpaid'),
                default => __('receivables.cash_sale'),
            },
            'show_url' => route('bills.show', $bill),
            'edit_url' => $bill->is_returned ? null : route('bills.edit', $bill),
        ]);
    }

    public function edit(Bill $bill)
    {
        // Block editing of returned bills
        if ($bill->is_returned) {
            abort(403, __('messages.Returned bills cannot be edited. You can only view or delete them.'));
        }
        return $this->show($bill);
    }

    private function calculateTagsTotal($tagsString)
    {
        if (!$tagsString) return 0;

        $total = 0;
        $tagPairs = explode('&', $tagsString);

        foreach ($tagPairs as $pair) {
            if (strpos($pair, '@') !== false) {
                $parts = explode('@', $pair);
                if (count($parts) == 2) {
                    $total += floatval($parts[1]);
                }
            }
        }

        return $total;
    }

    public function update(Request $request, Bill $bill)
    {
        if ($bill->is_returned) {
            abort(403, __('messages.Returned bills cannot be edited. You can only view or delete them.'));
        }

        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('edit_bills')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->ownerId();
        if ($bill->user_id !== $ownerId) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'note' => 'nullable|string|max:1000',
            'remove_products' => 'array',
            'new_product_id' => 'nullable|exists:products,id,user_id,' . $ownerId,
            'new_quantity' => 'nullable|numeric|min:0.01',
            'dynamic_product_ids' => 'array',
            'dynamic_quantities' => 'array',
            'dynamic_discounts' => 'array',
            'dynamic_product_tags' => 'array',
        ]);

        $this->validateBillUpdateLines($request);
        $isRestaurant = $user->isRestaurantAccount();

        $bill = DB::transaction(function () use ($request, $bill, $ownerId, $isRestaurant) {
            $bill = Bill::withoutGlobalScopes()->whereKey($bill->id)->lockForUpdate()->firstOrFail();
            DB::table('bill_product')->where('bill_id', $bill->id)->orderBy('product_id')->lockForUpdate()->get();

            $lockedProducts = $this->lockProductsByIds($ownerId, array_merge(
                $bill->products()->pluck('products.id')->all(),
                array_values(array_filter($request->input('dynamic_product_ids', []))),
                array_values(array_filter([$request->input('new_product_id')]))
            ));

            $bill->update([
                'note' => app(BillService::class)->composeNote(
                    $request->input('note'),
                    (bool) $bill->is_damaged,
                    (bool) $bill->is_returned,
                ),
            ]);

            $toRemove = $request->input('remove_products', []);

            $resolvePivot = function (string $uniqueKey) use ($bill) {
                [$productId, $tags] = array_pad(explode('_', $uniqueKey, 2), 2, '');
                $query = DB::table('bill_product')->where('bill_id', $bill->id)->where('product_id', $productId);

                if ($tags === '') {
                    $query->where(function ($q) {
                        $q->whereNull('tags')->orWhere('tags', '');
                    });
                } else {
                    $query->where('tags', $tags);
                }

                return [$query, $query->first(), (int) $productId];
            };

            foreach ($toRemove as $uniqueKey) {
                [$pivotQuery, $pivotRecord, $productId] = $resolvePivot($uniqueKey);
                if (! $pivotRecord) {
                    continue;
                }

                if (! $isRestaurant) {
                    $product = $lockedProducts[$productId] ?? null;
                    if ($product) {
                        $product->quantity += $pivotRecord->quantity;
                        $product->save();
                        $this->returnToBatch($product, $pivotRecord->quantity, $pivotRecord->cost_price, $ownerId);
                    }
                }

                $pivotQuery->delete();
            }

            foreach ($request->input('quantities', []) as $uniqueKey => $quantity) {
                if (in_array($uniqueKey, $toRemove, true)) {
                    continue;
                }

                [$pivotQuery, $pivotRecord, $productId] = $resolvePivot($uniqueKey);
                if (! $pivotRecord) {
                    continue;
                }

                $newQuantity = (float) $quantity;
                $newDiscount = isset($request->input('discounts', [])[$uniqueKey]) ? (float) $request->input('discounts', [])[$uniqueKey] : (float) $pivotRecord->discount;
                $quantityDiff = $newQuantity - $pivotRecord->quantity;

                if ($quantityDiff != 0.0 && ! $isRestaurant) {
                    $product = $lockedProducts[$productId] ?? null;
                    if ($product) {
                        $product->quantity -= $quantityDiff;
                        $product->save();

                        if ($quantityDiff > 0) {
                            $this->consumeProductStockAllowNegative($product, $quantityDiff);
                        } else {
                            $this->returnToBatch($product, abs($quantityDiff), $pivotRecord->cost_price, $ownerId);
                        }
                    }
                }

                $pivotQuery->update([
                    'quantity' => $newQuantity,
                    'discount' => $newDiscount,
                    'updated_at' => now(),
                ]);
            }

            foreach ($request->input('dynamic_product_ids', []) as $uniqueKey => $productId) {
                if (in_array($uniqueKey, $toRemove, true)) {
                    continue;
                }

                [$existsQuery] = $resolvePivot($uniqueKey);
                if ($existsQuery->exists()) {
                    continue;
                }

                $quantity = (float) ($request->input('dynamic_quantities', [])[$uniqueKey] ?? 1);
                $discount = (float) ($request->input('dynamic_discounts', [])[$uniqueKey] ?? 0);
                $tags = ($request->input('dynamic_product_tags', [])[$uniqueKey] ?? '') ?: null;
                $product = $lockedProducts[(int) $productId] ?? null;

                if (! $product) {
                    continue;
                }

                if (! $isRestaurant) {
                    $product->quantity -= $quantity;
                    $product->save();
                    $this->consumeProductStockAllowNegative($product, $quantity);
                }

                DB::table('bill_product')->insert([
                    'bill_id' => $bill->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'discount' => $discount,
                    'cost_price' => $product->cost_price,
                    'selling_price' => $product->selling_price,
                    'tags' => $tags,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $newProductId = $request->input('new_product_id');
            $newQty = (float) $request->input('new_quantity');
            if ($newProductId && $newQty > 0) {
                $product = $lockedProducts[(int) $newProductId] ?? null;
                if ($product) {
                    if (! $isRestaurant) {
                        $product->quantity -= $newQty;
                        $product->save();
                        $this->consumeProductStockAllowNegative($product, $newQty);
                    }

                    DB::table('bill_product')->insert([
                        'bill_id' => $bill->id,
                        'product_id' => $newProductId,
                        'quantity' => $newQty,
                        'discount' => 0,
                        'cost_price' => $product->cost_price,
                        'selling_price' => $product->selling_price,
                        'tags' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->recalculateBillTotal($bill);
            CustomerLedger::syncBillCharge($bill->fresh());

            return $bill->fresh(['products', 'customer', 'creator']);
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Bill updated successfully!',
                'bill' => $bill,
            ]);
        }

        return redirect()->route('bills.show', $bill->id)->with('success', 'Bill updated successfully!');
    }
    /**
     * Helper method to consume product stock using FIFO (ALLOWS NEGATIVE STOCK)
     */
    private function consumeProductStockAllowNegative($product, $quantity)
    {
        // Product stock is already updated in the main method
        // Just handle batch consumption, allowing negative batches

        $remaining = $quantity;
        $batches = $product->batches()->where('quantity', '>', 0)->orderBy('id')->lockForUpdate()->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            $consume = min($remaining, $batch->quantity);
            $batch->quantity -= $consume;
            $remaining -= $consume;
            $batch->save();
        }

        // If still remaining, consume from the latest batch (can go negative)
        if ($remaining > 0) {
            $lastBatch = $product->batches()->orderByDesc('id')->lockForUpdate()->first();
            if ($lastBatch) {
                $lastBatch->quantity -= $remaining;
                $lastBatch->save();
            } else {
                // If no batches exist, create a negative batch
                $product->batches()->create([
                    'quantity' => -$remaining,
                    'cost_price' => $product->cost_price,
                    'user_id' => auth()->user()->role === 'employee' ? auth()->user()->shop_owner_id : auth()->user()->id,
                ]);
            }
        }
    }
    /**
     * Helper method to return stock to appropriate batch
     */
    private function returnToBatch($product, $quantity, $costPrice, $ownerId)
    {
        $previousQuantity = $product->quantity - $quantity; // Quantity before return
        //recalculate average cost price
        if ($product->quantity <= 0) {
            $product->cost_price = $costPrice;
        } else {
            $product->cost_price =
                ($product->cost_price * $previousQuantity + $costPrice * $quantity)
                / max(1, ($previousQuantity + $quantity));
        }
        $product->cost_price = round($product->cost_price, 2);
        $product->save();


        $batch = $product->batches()->where('cost_price', $costPrice)->lockForUpdate()->first();

        if ($batch) {
            $batch->quantity += $quantity;
            $batch->save();
        } else {
            $product->batches()->create([
                'quantity' => $quantity,
                'cost_price' => $costPrice,
                'user_id' => $ownerId,
            ]);
        }
    }

    /**
     * Helper method to recalculate bill total (positive quantities only)
     */
    private function recalculateBillTotal($bill)
    {
        $total = 0;
        $bill->load('products');

        foreach ($bill->products as $product) {
            $qty = $product->pivot->quantity;
            $unitPrice = $product->pivot->selling_price;
            $discount = $product->pivot->discount ?? 0;

            // Calculate tags total if exists
            $tagsTotal = 0;
            if ($product->pivot->tags) {
                $tagsTotal = $this->calculateTagsTotal($product->pivot->tags);
            }

            $subtotal = $bill->is_damaged ? 0 : max(0, (($unitPrice + $tagsTotal) * $qty) - $discount);
            $total += $subtotal;
        }

        $bill->total_price = $total;
        $bill->save();
    }

    /**
     * Helper method to update customer balance
     */
    private function updateCustomerBalance($bill)
    {
        CustomerLedger::syncBillCharge($bill->fresh());
    }

    public function destroy(Bill $bill)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('delete_bills')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        if ($bill->user_id !== $ownerId) {
            abort(403, 'Unauthorized action.');
        }

        $isRestaurant = $user->isRestaurantAccount();

        DB::transaction(function () use ($bill, $ownerId, $isRestaurant) {
            $bill = Bill::withoutGlobalScopes()->whereKey($bill->id)->lockForUpdate()->firstOrFail();
            DB::table('bill_product')->where('bill_id', $bill->id)->orderBy('product_id')->lockForUpdate()->get();
            $lockedProducts = $this->lockProductsByIds($ownerId, $bill->products()->pluck('products.id')->all());

            foreach ($bill->products as $product) {
                if ($isRestaurant) {
                    continue;
                }

                $lockedProduct = $lockedProducts[$product->id] ?? $product;
                $restoredQty = $product->pivot->quantity;
                $costPrice = $product->pivot->cost_price;
                $oldQty = $lockedProduct->quantity;
                $oldAvg = $lockedProduct->cost_price;

                $lockedProduct->quantity += $restoredQty;

                $batch = $lockedProduct->batches()->where('cost_price', $costPrice)->lockForUpdate()->first();

                if ($batch) {
                    $batch->quantity += $restoredQty;
                    $batch->save();
                } else {
                    $lockedProduct->batches()->create([
                        'quantity' => $restoredQty,
                        'cost_price' => $costPrice,
                        'user_id' => $ownerId,
                    ]);
                }

                if ($bill->is_returned) {
                    $newQty = $lockedProduct->quantity;
                    if ($newQty > 0) {
                        $returnedAbsQty = abs($restoredQty);
                        $newCostPrice = ($oldQty * $oldAvg - $returnedAbsQty * $costPrice) / $newQty;
                        $lockedProduct->cost_price = round($newCostPrice, 2);
                    } else {
                        $lockedProduct->cost_price = $oldAvg;
                    }
                } elseif (!$bill->is_damaged) {
                    if ($oldQty <= 0) {
                        $lockedProduct->cost_price = $costPrice;
                    } else {
                        $lockedProduct->cost_price =
                            ($oldAvg * $oldQty + $costPrice * $restoredQty)
                            / max(1, ($oldQty + $restoredQty));
                    }
                    $lockedProduct->cost_price = round($lockedProduct->cost_price, 2);
                }

                $lockedProduct->save();
            }

            $bill->products()->detach();
            CustomerLedger::removeBillRows($bill);
            $bill->delete();
        });

        return redirect()->route('bills.index')->with('success', 'Bill deleted successfully! Product quantities and customer balance have been restored.');
    }

    public function storePayment(Request $request, Bill $bill)
    {
        $user = auth()->user();
        $this->authorizeBillPayment($bill, $user);

        if (! $bill->customer_id || (float) $bill->total_price <= 0) {
            throw ValidationException::withMessages([
                'bill' => __('receivables.validation.bill_payment_requires_customer'),
            ]);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|string|in:cash,card,transfer,check',
            'payment_date' => 'nullable|date',
            'note' => 'nullable|string|max:255',
        ]);

        $amount = round((float) $data['amount'], 2);
        CustomerLedger::receiveForBill(
                $bill,
                $amount,
                $data['type'],
                $data['note'] ?? null,
                ! empty($data['payment_date']) ? Carbon::parse($data['payment_date'])->setTime(now()->hour, now()->minute, now()->second) : null,
            );

        $bill = $bill->fresh(['customer', 'creator', 'products']);
        $summary = CustomerLedger::billSummary($bill);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('receivables.messages.bill_payment_added'),
                'summary' => $summary,
                'customer_balance' => $bill->customer?->fresh()?->balance,
            ]);
        }

        return redirect()->route('bills.show', $bill)->with('success', __('receivables.messages.bill_payment_added'));
    }

    public function destroyPayment(Bill $bill, CustomerPayment $payment)
    {
        $user = auth()->user();
        $this->authorizeBillPayment($bill, $user);

        if ($payment->user_id !== $bill->user_id || (int) $payment->bill_id !== (int) $bill->id) {
            abort(404);
        }

        if (CustomerLedger::kindForRow($payment) === CustomerLedger::KIND_BILL_CHARGE) {
            throw ValidationException::withMessages([
                'payment' => __('receivables.validation.bill_charge_cannot_be_deleted'),
            ]);
        }

        CustomerLedger::deleteRow($payment);
        $summary = CustomerLedger::billSummary($bill->fresh());

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('receivables.messages.bill_payment_deleted'),
                'summary' => $summary,
                'customer_balance' => $bill->customer?->fresh()?->balance,
            ]);
        }

        return redirect()->route('bills.show', $bill)->with('success', __('receivables.messages.bill_payment_deleted'));
    }

    private function authorizeBillPayment(Bill $bill, $user): void
    {
        $ownerId = $user->ownerId();
        if ($bill->user_id !== $ownerId) {
            abort(403, 'Unauthorized');
        }

        if ($user->role === 'employee' && ! ($user->hasPermission('manage_payments_receipts') || $user->hasPermission('edit_bills'))) {
            abort(403, 'Unauthorized');
        }
    }

    private function buildBillIndexQuery(int $ownerId, Request $request)
    {
        $query = Bill::withoutGlobalScopes()
            ->where('bills.user_id', $ownerId)
            ->orderByDesc('bills.created_at');

        $from = $this->validDate($request->input('date'));
        if ($from !== null && ! $request->boolean('all_dates')) {
            $to = $this->validDate($request->input('date_to')) ?? $from;
            if ($to < $from) {
                [$from, $to] = [$to, $from];
            }
            $query->whereBetween('bills.created_at', ShopTime::utcRange($from, $to, $ownerId));
        }

        $searchTerm = trim((string) $request->query('search', ''));
        if ($searchTerm !== '') {
            foreach (preg_split('/\s+/', mb_strtolower($searchTerm)) ?: [] as $term) {
                $term = trim($term);
                if ($term === '') {
                    continue;
                }

                $query->where(function ($q) use ($term) {
                    $q->where('bills.id', 'like', "%{$term}%")
                        ->orWhere('bills.note', 'like', "%{$term}%")
                        ->orWhere('bills.total_price', 'like', "%{$term}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($term) {
                            $customerQuery->where('name', 'like', "%{$term}%");
                        })
                        ->orWhereHas('creator', function ($creatorQuery) use ($term) {
                            $creatorQuery->where('name', 'like', "%{$term}%");
                        })
                        ->orWhereHas('products', function ($productQuery) use ($term) {
                            $productQuery->where('name', 'like', "%{$term}%")
                                ->orWhere('barcode', 'like', "%{$term}%");
                        });
                });
            }
        }

        return $query;
    }

    /**
     * Date range of the bills list. With no date chosen the list (and its totals) covers the shop's today;
     * "all_dates=1" lifts the filter, and a search without a chosen date looks through every date.
     *
     * @return array{from: ?string, to: ?string, all: bool, today: string, preset: string}
     */
    private function resolveIndexDateScope(Request $request, int $ownerId): array
    {
        $today = ShopTime::today($ownerId);
        $from = $this->validDate($request->input('date'));
        $to = $this->validDate($request->input('date_to'));
        $searching = trim((string) $request->query('search', '')) !== '';

        if (($request->boolean('all_dates') && $from === null && $to === null) || ($from === null && $to === null && $searching)) {
            return ['from' => null, 'to' => null, 'all' => true, 'today' => $today, 'preset' => 'all'];
        }

        $from ??= $to ?? $today;
        $to ??= $from;
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $yesterday = Carbon::parse($today)->subDay()->toDateString();
        $weekStart = Carbon::parse($today)->subDays(6)->toDateString();
        $monthStart = Carbon::parse($today)->startOfMonth()->toDateString();
        $preset = match (true) {
            $from === $today && $to === $today => 'today',
            $from === $yesterday && $to === $yesterday => 'yesterday',
            $from === $weekStart && $to === $today => 'week',
            $from === $monthStart && $to === $today => 'month',
            default => 'custom',
        };

        return ['from' => $from, 'to' => $to, 'all' => false, 'today' => $today, 'preset' => $preset];
    }

    private function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function billTotalsBaseQuery(int $ownerId, Request $request)
    {
        return $this->buildBillIndexQuery($ownerId, $request)
            ->reorder()
            ->select([
                'bills.id',
                'bills.total_price',
                'bills.customer_id',
                'bills.user_id',
            ]);
    }

    /** Profit of every bill matching the current filters (not only the visible page). */
    private function filteredProfit(int $ownerId, Request $request): float
    {
        $matchingIds = $this->billTotalsBaseQuery($ownerId, $request)->select('bills.id');

        return round((float) (DB::table('bill_product')
            ->whereIn('bill_product.bill_id', $matchingIds)
            ->selectRaw('SUM((bill_product.selling_price - bill_product.cost_price) * bill_product.quantity - bill_product.discount) as profit')
            ->value('profit') ?? 0), 2);
    }

    /**
     * Paid and due amounts of every bill matching the current filters. Walk-in bills are fully paid by definition,
     * so only bills of customers need their ledger read (in chunks, with bulk queries).
     *
     * @return array{0: float, 1: float}
     */
    private function filteredLedgerTotals(int $ownerId, Request $request): array
    {
        $paid = (float) $this->billTotalsBaseQuery($ownerId, $request)
            ->whereNull('bills.customer_id')
            ->sum('bills.total_price');
        $due = 0.0;

        $this->billTotalsBaseQuery($ownerId, $request)
            ->whereNotNull('bills.customer_id')
            ->select(['bills.id', 'bills.user_id', 'bills.customer_id', 'bills.total_price', 'bills.created_at'])
            ->chunkById(500, function ($chunk) use (&$paid, &$due, $ownerId) {
                $summaries = $this->paymentSummariesForCollection($chunk, $ownerId);

                foreach ($chunk as $bill) {
                    $summary = $summaries[$bill->id] ?? CustomerLedger::billSummary($bill);
                    $paid += (float) ($summary['paid'] ?? 0);
                    $due += (float) ($summary['due'] ?? 0);
                }
            }, 'bills.id', 'id');

        return [round($paid, 2), round($due, 2)];
    }

    private function paymentSummariesForCollection($bills, int $ownerId): array
    {
        $billCollection = collect($bills);
        if ($billCollection->isEmpty()) {
            return [];
        }

        return CustomerLedger::summariesForBills($billCollection);
    }

    private function billLedgerRows(Bill $bill, int $ownerId)
    {
        return CustomerPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where(function ($query) use ($bill) {
                $query->where('bill_id', $bill->id);

                if ($bill->customer_id) {
                    $query->orWhere(function ($legacy) use ($bill) {
                        $legacy->whereNull('bill_id')
                            ->where('customer_id', $bill->customer_id);
                    });
                }
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (CustomerPayment $row) => (int) $row->bill_id === (int) $bill->id
                || CustomerLedger::legacyBillIdFromNote($row->note) === (int) $bill->id)
            ->values();
    }

    private function validateBillUpdateLines(Request $request): void
    {
        foreach (($request->input('quantities', [])) as $quantity) {
            if ((float) $quantity <= 0) {
                throw ValidationException::withMessages(['quantities' => __('receivables.validation.bill_lines_invalid')]);
            }
        }

        foreach (($request->input('discounts', [])) as $discount) {
            if ((float) $discount < 0) {
                throw ValidationException::withMessages(['discounts' => __('receivables.validation.bill_lines_invalid')]);
            }
        }

        foreach (($request->input('dynamic_quantities', [])) as $quantity) {
            if ((float) $quantity <= 0) {
                throw ValidationException::withMessages(['dynamic_quantities' => __('receivables.validation.bill_lines_invalid')]);
            }
        }

        foreach (($request->input('dynamic_discounts', [])) as $discount) {
            if ((float) $discount < 0) {
                throw ValidationException::withMessages(['dynamic_discounts' => __('receivables.validation.bill_lines_invalid')]);
            }
        }
    }

    /**
     * @return array<int, Product>
     */
    private function lockProductsByIds(int $ownerId, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if ($ids === []) {
            return [];
        }

        return Product::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * Quick actions for bill management
     */
    public function quickStats(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $today = now()->toDateString();
        $thisMonth = now()->format('Y-m');

        $todayBills = Bill::where('user_id', $ownerId)
            ->whereDate('created_at', $today)
            ->with('products');

        $monthlyBills = Bill::where('user_id', $ownerId)
            ->where('created_at', 'like', $thisMonth . '%')
            ->with('products');

        $stats = [
            'today' => [
                'sales' => $todayBills->sum('total_price'),
                'bills_count' => $todayBills->count(),
                'profit' => $todayBills->get()->sum(function ($bill) {
                    return $bill->products->sum(function ($product) {
                        return ($product->pivot->selling_price - $product->pivot->cost_price) * $product->pivot->quantity;
                    });
                }),
            ],
            'monthly' => [
                'sales' => $monthlyBills->sum('total_price'),
                'bills_count' => $monthlyBills->count(),
                'profit' => $monthlyBills->get()->sum(function ($bill) {
                    return $bill->products->sum(function ($product) {
                        return ($product->pivot->selling_price - $product->pivot->cost_price) * $product->pivot->quantity;
                    });
                }),
            ]
        ];

        return response()->json($stats);
    }


    /**
     * Duplicate a bill (useful for similar orders)
     */
    public function duplicate(Bill $bill)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        if ($bill->user_id !== $ownerId) {
            abort(403, 'Unauthorized');
        }

        $products = Product::where('user_id', $ownerId)
            ->where('is_active', true)
            ->get();
        $customers = Customer::where('user_id', $ownerId)->get();

        // Prepare products data for JavaScript
        $productsForJS = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->selling_price,
                'cost_price' => $p->cost_price,
                'barcode' => $p->barcode,
                'quantity' => $p->quantity,
            ];
        })->toArray();

        // Pre-populate with bill data
        $billData = [
            'note' => $bill->note,
            'customer_id' => $bill->customer_id,
            'products' => $bill->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $product->pivot->quantity,
                    'price' => $product->pivot->selling_price,
                    'cost_price' => $product->pivot->cost_price,
                    'discount' => $product->pivot->discount,
                ];
            }),
        ];

        return view('bills.create', compact('productsForJS', 'products', 'customers', 'billData'));
    }
}
