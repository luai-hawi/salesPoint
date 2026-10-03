<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use App\Models\User;
use App\Services\StockIntake;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BatchController extends Controller
{
    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('edit_products')) {
            return response()->json([
                'success' => false,
                'message' => __('products_ui.flash.unauthorized'),
            ], 403);
        }

        $ownerId = (int) $user->ownerId();

        $request->validate(array_merge([
            'product_id' => 'required|integer',
            'quantity' => 'required|numeric|min:0.01',
            'cost_price' => 'required|numeric|min:0',
            'intake_client_uuid' => 'nullable|string|max:64',
        ], $this->fundingRules($ownerId)));

        $quantity = round((float) $request->input('quantity'), 2);
        $costPrice = round((float) $request->input('cost_price'), 2);
        $funding = $this->resolveFunding($request);
        $clientUuid = $request->filled('intake_client_uuid') ? trim((string) $request->input('intake_client_uuid')) : null;

        $batch = null;

        DB::transaction(function () use ($request, $ownerId, $quantity, $costPrice, $user, $funding, $clientUuid, &$batch) {
            $product = Product::where('id', $request->integer('product_id'))
                ->where('user_id', $ownerId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateFundingAgainstLine($funding, $product, $quantity, $costPrice);

            if ($clientUuid) {
                $existingBatch = Batch::where('product_id', $product->id)
                    ->where('user_id', $ownerId)
                    ->where('intake_client_uuid', $clientUuid)
                    ->lockForUpdate()
                    ->first();

                if ($existingBatch) {
                    $batch = $existingBatch;

                    return;
                }
            }

            $mergeableBatch = (($funding['mode'] ?? 'none') === 'none' && ! $clientUuid)
                ? Batch::where('product_id', $product->id)
                    ->where('cost_price', $costPrice)
                    ->whereNull('purchase_bill_id')
                    ->lockForUpdate()
                    ->orderBy('created_at')
                    ->first()
                : null;

            if ($mergeableBatch) {
                $batch = $mergeableBatch;
                $batch->quantity = round((float) $batch->quantity + $quantity, 2);
                $batch->save();
            } else {
                $batch = Batch::create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'cost_price' => $costPrice,
                    'user_id' => $ownerId,
                    'intake_client_uuid' => $clientUuid,
                ]);
            }

            $this->applyStockIncrease($product, $quantity, $costPrice);

            $bill = app(StockIntake::class)->record($user, [[
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => $costPrice,
            ]], array_merge($funding, ['client_uuid' => $clientUuid]));

            if ($bill) {
                $batch->purchase_bill_id = $bill->id;
                $batch->save();
            }
        });

        return response()->json([
            'success' => true,
            'batch' => $batch,
            'updated_quantity' => $batch->product()->withoutGlobalScopes()->first()?->quantity,
            'message' => __('products_ui.flash.batch_added'),
        ]);
    }

    public function update(Request $request, Batch $batch)
    {
        $user = auth()->user();
        $ownerId = (int) $user->ownerId();

        if ($user->role === 'employee' && ! $user->hasPermission('edit_products')) {
            return response()->json([
                'success' => false,
                'message' => __('products_ui.flash.unauthorized'),
            ], 403);
        }

        if ($batch->product->user_id !== $ownerId) {
            return response()->json([
                'success' => false,
                'message' => __('products_ui.flash.unauthorized'),
            ], 403);
        }

        if ($batch->purchase_bill_id) {
            throw ValidationException::withMessages([
                'batch' => [__('products_ui.validation.funded_batch_locked')],
            ]);
        }

        $data = $request->validate([
            'quantity' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($batch, $data) {
            $product = Product::whereKey($batch->product_id)->lockForUpdate()->firstOrFail();
            $batch = Batch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $oldQty = (float) $batch->quantity;
            $oldBatchCost = (float) $batch->cost_price;
            $oldProductQty = (float) $product->quantity;
            $oldProductAvg = (float) $product->cost_price;

            $batch->update([
                'quantity' => round((float) $data['quantity'], 2),
                'cost_price' => round((float) $data['cost_price'], 2),
            ]);

            $newQty = (float) $batch->quantity;
            $product->quantity = round($oldProductQty - $oldQty + $newQty, 2);

            if ($product->quantity <= 0) {
                $product->cost_price = 0;
            } else {
                $totalCost = ($oldProductAvg * $oldProductQty) - ($oldQty * $oldBatchCost) + ($newQty * (float) $batch->cost_price);
                $product->cost_price = round($totalCost / $product->quantity, 2);
            }

            $product->save();
        });

        return response()->json([
            'success' => true,
            'message' => __('products_ui.flash.batch_updated'),
        ]);
    }

    public function destroy(Batch $batch)
    {
        $user = auth()->user();
        $ownerId = (int) $user->ownerId();

        if ($user->role === 'employee' && ! $user->hasPermission('edit_products')) {
            return response()->json([
                'success' => false,
                'message' => __('products_ui.flash.unauthorized'),
            ], 403);
        }

        if ($batch->product->user_id !== $ownerId) {
            return response()->json([
                'success' => false,
                'message' => __('products_ui.flash.unauthorized'),
            ], 403);
        }

        if ($batch->purchase_bill_id) {
            throw ValidationException::withMessages([
                'batch' => [__('products_ui.validation.funded_batch_locked')],
            ]);
        }

        DB::transaction(function () use ($batch) {
            $product = Product::whereKey($batch->product_id)->lockForUpdate()->firstOrFail();
            $batch = Batch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $remainingQty = round((float) $product->quantity - (float) $batch->quantity, 2);

            if ($remainingQty > 0) {
                $remainingCost = ((float) $product->cost_price * (float) $product->quantity) - ((float) $batch->cost_price * (float) $batch->quantity);
                $product->cost_price = round($remainingCost / $remainingQty, 2);
            } else {
                $product->cost_price = 0;
            }

            $product->quantity = max(0, $remainingQty);
            $product->save();

            $batch->delete();
        });

        return response()->json([
            'success' => true,
            'message' => __('products_ui.flash.batch_deleted'),
        ]);
    }

    private function fundingRules(int $ownerId): array
    {
        return [
            'funding_mode' => ['nullable', Rule::in(StockIntake::MODES)],
            'funding_supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('user_id', $ownerId)),
            ],
            'funding_paid_amount' => 'nullable|numeric|min:0',
            'funding_payment_method' => ['nullable', 'string', Rule::in(StockIntake::PAYMENT_METHODS)],
            'funding_date' => 'nullable|date',
            'funding_note' => 'nullable|string|max:1000',
        ];
    }

    private function resolveFunding(Request $request): array
    {
        return [
            'mode' => $request->input('funding_mode', 'none') ?: 'none',
            'supplier_id' => $request->filled('funding_supplier_id') ? $request->integer('funding_supplier_id') : null,
            'paid_amount' => $request->input('funding_paid_amount'),
            'payment_method' => $request->input('funding_payment_method'),
            'date' => $request->input('funding_date'),
            'note' => $request->input('funding_note'),
            'client_uuid' => $request->input('intake_client_uuid'),
        ];
    }

    private function validateFundingAgainstLine(array $funding, Product $product, float $quantity, float $costPrice): void
    {
        $mode = $funding['mode'] ?? 'none';
        if ($mode === 'none' || $quantity <= 0) {
            return;
        }

        $total = round($quantity * $costPrice, 2);

        if (in_array($mode, ['credit', 'partial'], true) && empty($funding['supplier_id'])) {
            throw ValidationException::withMessages([
                'funding_supplier_id' => [__('products_ui.validation.supplier_required')],
            ]);
        }

        if (in_array($mode, ['paid', 'partial'], true) && empty($funding['payment_method'])) {
            throw ValidationException::withMessages([
                'funding_payment_method' => [__('products_ui.validation.payment_method_required')],
            ]);
        }

        if ($mode === 'partial') {
            $paidAmount = round((float) ($funding['paid_amount'] ?? 0), 2);

            if ($paidAmount <= 0) {
                throw ValidationException::withMessages([
                    'funding_paid_amount' => [__('products_ui.validation.paid_amount_required')],
                ]);
            }

            if ($paidAmount > $total) {
                throw ValidationException::withMessages([
                    'funding_paid_amount' => [__('products_ui.validation.partial_payment_too_large')],
                ]);
            }
        }
    }

    private function applyStockIncrease(Product $product, float $quantity, float $costPrice): void
    {
        $oldQty = (float) $product->quantity;
        $oldAvg = (float) $product->cost_price;

        $product->quantity = round($oldQty + $quantity, 2);
        $product->cost_price = $oldQty <= 0
            ? $costPrice
            : round((($oldAvg * $oldQty) + ($costPrice * $quantity)) / max(0.01, $oldQty + $quantity), 2);

        $product->save();
    }
}
