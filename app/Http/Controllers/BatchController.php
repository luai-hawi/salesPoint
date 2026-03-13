<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchController extends Controller
{
    /**
     * Store a new batch for a product
     */
    public function store(Request $request)
    {
        try {
            Log::info('BatchController store request:', $request->all());

            $user = auth()->user();
            $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

            // Check permissions for employees
            if ($user->role === 'employee' && !$user->hasPermission('edit_products')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have permission to edit products'
                ], 403);
            }

            $data = $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|numeric|min:0.01',
                'cost_price' => 'required|numeric|min:0',
            ]);

            $data['user_id'] = $ownerId;

            // Ensure the product belongs to the logged-in user
            $product = Product::where('id', $data['product_id'])
                ->where('user_id', $ownerId)
                ->firstOrFail();

            DB::beginTransaction();

            $oldQty = $product->quantity;
            $oldAvg = $product->cost_price;

            // Check if a batch with the same cost price exists for this product and user
            $existingBatch = Batch::where('product_id', $data['product_id'])
                ->whereHas('product', function ($q) use ($ownerId) {
                    $q->where('user_id', $ownerId);
                })
                ->where('cost_price', $data['cost_price'])
                ->orderBy('created_at', 'asc')
                ->first();

            if ($existingBatch) {
                $existingBatch->quantity += $data['quantity'];
                $existingBatch->save();
                $batch = $existingBatch;
                Log::info('Updated existing batch:', ['batch_id' => $batch->id, 'new_quantity' => $batch->quantity]);
            } else {
                $batch = Batch::create($data);
                Log::info('Created new batch:', ['batch_id' => $batch->id]);
            }

            // Update product quantity and average cost
            $product->quantity = $oldQty + $data['quantity'];

            if ($oldQty <= 0) {
                $product->cost_price = $data['cost_price'];
            } else {
                $product->cost_price = ($oldAvg * $oldQty + $data['cost_price'] * $data['quantity']) / ($oldQty + $data['quantity']);
            }

            $product->cost_price = round($product->cost_price, 2);
            $product->save();

            DB::commit();

            Log::info('Batch created successfully:', [
                'batch_id' => $batch->id,
                'product_id' => $product->id,
                'new_product_quantity' => $product->quantity,
                'new_product_cost_price' => $product->cost_price
            ]);

            return response()->json([
                'success' => true,
                'batch' => $batch,
                'updated_quantity' => $product->quantity,
                'message' => 'Batch added successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation error in batch store:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating batch:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create batch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing batch
     */
    public function update(Request $request, Batch $batch)
    {
        try {
            Log::info('BatchController update request:', ['batch_id' => $batch->id, 'data' => $request->all()]);

            $user = auth()->user();
            $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

            // Check permissions for employees
            if ($user->role === 'employee' && !$user->hasPermission('edit_products')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have permission to edit products'
                ], 403);
            }

            // Ensure batch belongs to a product of the logged-in user
            if ($batch->product->user_id !== $ownerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action'
                ], 403);
            }

            $data = $request->validate([
                'quantity' => 'required|numeric|min:0',
                'cost_price' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();

            $product = $batch->product;
            $oldQty = $batch->quantity;
            $oldAvg = $batch->cost_price;
            $oldProductQty = $product->quantity;
            $oldProductAvg = $product->cost_price;

            $batch->update($data);
            $diff = $batch->quantity - $oldQty;

            // Update product quantity
            $product->quantity += $diff;

            // Recalculate average cost price
            if ($oldProductQty <= 0 && $diff > 0) {
                $product->cost_price = $batch->cost_price;
            } else if ($oldProductQty > 0 && $diff == 0) {
                // Just price change, no quantity change
                $product->cost_price = (($oldProductAvg * $oldProductQty - ($oldAvg * $batch->quantity)) + ($batch->cost_price * $batch->quantity)) / max(1, $oldProductQty);
            } else {
                // Quantity and/or price change
                $product->cost_price = ($oldProductAvg * $oldProductQty + $batch->cost_price * $diff) / max(1, ($oldProductQty + $diff));
            }

            $product->cost_price = round($product->cost_price, 2);
            $product->save();

            DB::commit();

            Log::info('Batch updated successfully:', [
                'batch_id' => $batch->id,
                'old_quantity' => $oldQty,
                'new_quantity' => $batch->quantity,
                'diff' => $diff,
                'new_product_quantity' => $product->quantity,
                'new_product_cost_price' => $product->cost_price
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batch updated successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation error in batch update:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating batch:', [
                'batch_id' => $batch->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update batch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a batch
     */
    public function destroy(Batch $batch)
    {

        try {
            Log::info('BatchController destroy request:', ['batch_id' => $batch->id]);

            $user = auth()->user();
            $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

            // Check permissions for employees
            if ($user->role === 'employee' && !$user->hasPermission('edit_products')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have permission to edit products'
                ], 403);
            }

            if ($batch->product->user_id !== $ownerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action'
                ], 403);
            }

            DB::beginTransaction();
            $product = $batch->product;
            $oldProductQty = $product->quantity;
            $oldProductAvg = $product->cost_price;

            // Update product quantity
            $product->quantity -= $batch->quantity;
            // Recalculate average cost price
            if ($oldProductQty > 0) {
                $newQty = $oldProductQty - $batch->quantity;
                if ($newQty > 0) {
                    $product->cost_price = ($oldProductAvg * $oldProductQty - $batch->cost_price * $batch->quantity) / $newQty;
                } else {
                    $product->cost_price = 0; // No stock left
                }
            }


            $product->cost_price = round($product->cost_price, 2);
            $product->save();

            $batchId = $batch->id;
            $batch->delete();

            DB::commit();

            Log::info('Batch deleted successfully:', [
                'batch_id' => $batchId,
                'new_product_quantity' => $product->quantity,
                'new_product_cost_price' => $product->cost_price
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batch deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting batch:', [
                'batch_id' => $batch->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete batch: ' . $e->getMessage()
            ], 500);
        }
    }
}
