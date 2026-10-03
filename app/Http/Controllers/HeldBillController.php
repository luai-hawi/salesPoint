<?php

namespace App\Http\Controllers;

use App\Models\HeldBill;
use App\Models\Product;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class HeldBillController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $this->authorizePos($user);

        $heldBills = HeldBill::query()
            ->with(['creator:id,name', 'customer:id,name'])
            ->latest()
            ->get()
            ->map(fn (HeldBill $heldBill) => $this->transformHeldBill($heldBill));

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'data' => $heldBills,
                'count' => $heldBills->count(),
            ]);
        }

        return view('pos.held.index', [
            'heldBills' => $heldBills,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizePos($user);

        $data = $this->validateHeldBillRequest($request);

        $existing = null;
        if (! empty($data['client_uuid'])) {
            $existing = HeldBill::query()->where('client_uuid', $data['client_uuid'])->first();
        }

        if (! $existing && HeldBill::query()->count() >= 50) {
            throw ValidationException::withMessages([
                'held_bills' => __('pos.held.limit_reached'),
            ]);
        }

        $record = $existing ?? new HeldBill();
        $record->fill([
            'created_by' => $user->id,
            'label' => $data['label'],
            'customer_id' => $data['customer_id'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'table_label' => $data['table_label'] ?? null,
            'items_count' => $data['items_count'],
            'total' => $data['total'],
            'payload' => $data['payload'],
            'client_uuid' => $data['client_uuid'] ?? null,
        ]);
        $record->save();

        ActivityLogger::record('held', 'held_bill', $record, [
            'label' => $record->label,
            'items_count' => $record->items_count,
        ], (float) $record->total, $record->label);

        return response()->json([
            'success' => true,
            'held_bill' => $this->transformHeldBill($record->fresh(['creator:id,name', 'customer:id,name'])),
        ]);
    }

    public function update(Request $request, HeldBill $heldBill): JsonResponse
    {
        $this->authorizePos($request->user());

        $validator = Validator::make($request->all(), [
            'label' => ['nullable', 'string', 'max:120'],
            'payload' => ['nullable', 'array'],
            'customer_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'table_label' => ['nullable', 'string', 'max:60'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);
        $validated = $validator->validate();

        if (array_key_exists('payload', $validated)) {
            $snapshot = $this->sanitizeSnapshot($validated['payload']);
            $heldBill->payload = $snapshot['payload'];
            $heldBill->items_count = $snapshot['items_count'];
            $heldBill->total = $snapshot['total'];
        }

        foreach (['label', 'customer_id', 'customer_name', 'table_label', 'client_uuid'] as $field) {
            if (array_key_exists($field, $validated)) {
                $heldBill->{$field} = $validated[$field];
            }
        }

        $heldBill->save();

        return response()->json([
            'success' => true,
            'held_bill' => $this->transformHeldBill($heldBill->fresh(['creator:id,name', 'customer:id,name'])),
        ]);
    }

    public function resume(Request $request, HeldBill $heldBill)
    {
        $this->authorizePos($request->user());

        $payload = $heldBill->payload ?? [];

        if (! ($request->expectsJson() || $request->ajax())) {
            $request->session()->put('pos_held_recovery', $payload);
            $request->session()->put('pos_held_recovery_id', $heldBill->id);

            return redirect()->route('dashboard');
        }

        return response()->json([
            'success' => true,
            'snapshot' => $payload,
            'held_bill_id' => $heldBill->id,
        ]);
    }

    public function acknowledgeResume(Request $request, HeldBill $heldBill): JsonResponse
    {
        $this->authorizePos($request->user());

        DB::transaction(function () use ($heldBill, $request) {
            ActivityLogger::record('resumed', 'held_bill', $heldBill, [
                'label' => $heldBill->label,
                'items_count' => $heldBill->items_count,
            ], (float) $heldBill->total, $heldBill->label);

            $heldBill->delete();
            $request->session()->forget(['pos_held_recovery', 'pos_held_recovery_id']);
        });

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, HeldBill $heldBill): JsonResponse
    {
        $this->authorizePos($request->user());

        ActivityLogger::record('deleted', 'held_bill', $heldBill, [
            'label' => $heldBill->label,
            'items_count' => $heldBill->items_count,
        ], (float) $heldBill->total, $heldBill->label);

        $heldBill->delete();

        return response()->json(['success' => true]);
    }

    private function authorizePos($user): void
    {
        if ($user->role === 'employee' && ! $user->hasPermission('create_bills')) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateHeldBillRequest(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'label' => ['nullable', 'string', 'max:120'],
            'customer_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'table_label' => ['nullable', 'string', 'max:60'],
            'payload' => ['required', 'array'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);
        $validated = $validator->validate();

        return array_merge($validated, $this->sanitizeSnapshot($validated['payload']));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload: array<string, mixed>, items_count: int, total: float}
     */
    private function sanitizeSnapshot(array $payload): array
    {
        $rows = $payload['rows'] ?? [];
        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages([
                'payload.rows' => __('pos.held.validation_rows'),
            ]);
        }

        $cleanRows = [];
        $total = 0.0;
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    "payload.rows.{$index}" => __('pos.held.validation_row_shape'),
                ]);
            }

            $validator = Validator::make($row, [
                'product_id' => ['required', 'integer'],
                'name' => ['required', 'string', 'max:255'],
                'quantity' => ['required', 'numeric', 'min:0.01'],
                'selling_price' => ['required', 'numeric', 'min:0'],
                'cost_price' => ['nullable', 'numeric', 'min:0'],
                'discount' => ['nullable', 'numeric', 'min:0'],
                'discount_type' => ['nullable', 'in:total,per-unit'],
                'return_cost' => ['nullable', 'numeric', 'min:0'],
                'tags' => ['nullable', 'array'],
                'tags.*' => ['string', 'max:120'],
                'imeis' => ['nullable', 'array'],
                'imeis.*' => ['string', 'max:120'],
                'note' => ['nullable', 'string', 'max:500'],
            ]);
            $validatedRow = $validator->validate();

            foreach (['name', 'note'] as $field) {
                if (isset($validatedRow[$field]) && strip_tags((string) $validatedRow[$field]) !== (string) $validatedRow[$field]) {
                    throw ValidationException::withMessages([
                        "payload.rows.{$index}.{$field}" => __('pos.held.validation_no_html'),
                    ]);
                }
            }

            $validatedRow['discount_type'] = $validatedRow['discount_type'] ?? 'total';
            $validatedRow['discount'] = (float) ($validatedRow['discount'] ?? 0);
            $validatedRow['cost_price'] = (float) ($validatedRow['cost_price'] ?? 0);
            $validatedRow['return_cost'] = isset($validatedRow['return_cost']) ? (float) $validatedRow['return_cost'] : null;
            $validatedRow['tags'] = array_values($validatedRow['tags'] ?? []);
            $validatedRow['imeis'] = array_values($validatedRow['imeis'] ?? []);
            $validatedRow['quantity'] = (float) $validatedRow['quantity'];
            $validatedRow['selling_price'] = (float) $validatedRow['selling_price'];

            $lineTotal = $validatedRow['selling_price'] * $validatedRow['quantity'];
            $discount = $validatedRow['discount_type'] === 'per-unit'
                ? $validatedRow['discount'] * $validatedRow['quantity']
                : $validatedRow['discount'];

            foreach ($validatedRow['tags'] as $tag) {
                if (strip_tags($tag) !== $tag) {
                    throw ValidationException::withMessages([
                        "payload.rows.{$index}.tags" => __('pos.held.validation_no_html'),
                    ]);
                }
                [, $price] = array_pad(explode('@', $tag, 2), 2, '0');
                $lineTotal += ((float) $price) * $validatedRow['quantity'];
            }

            foreach ($validatedRow['imeis'] as $imei) {
                if (strip_tags($imei) !== $imei) {
                    throw ValidationException::withMessages([
                        "payload.rows.{$index}.imeis" => __('pos.held.validation_no_html'),
                    ]);
                }
            }

            $total += max(0, $lineTotal - $discount);
            $cleanRows[] = $validatedRow;
        }

        $topValidator = Validator::make($payload, [
            'customer' => ['nullable', 'array'],
            'customer.id' => ['nullable', 'integer'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'bill_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_damaged' => ['nullable', 'boolean'],
            'is_returned' => ['nullable', 'boolean'],
            'bill_date' => ['nullable', 'date'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:cash,card,transfer,check'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
            'restaurant' => ['nullable', 'array'],
            'restaurant.orderId' => ['nullable', 'integer'],
            'restaurant.tableId' => ['nullable', 'integer'],
            'restaurant.orderType' => ['nullable', 'string', 'max:30'],
            'restaurant.guests' => ['nullable', 'integer', 'min:1'],
            'restaurant.notes' => ['nullable', 'string', 'max:1000'],
            'restaurant.delivery' => ['nullable', 'array'],
        ]);
        $validatedPayload = $topValidator->validate();

        foreach (['note', 'customer.name'] as $field) {
            $value = data_get($validatedPayload, $field);
            if (is_string($value) && strip_tags($value) !== $value) {
                throw ValidationException::withMessages([
                    str_replace('.', '_', $field) => __('pos.held.validation_no_html'),
                ]);
            }
        }

        $sanitized = [
            'rows' => $cleanRows,
            'customer' => data_get($validatedPayload, 'customer')
                ? [
                    'id' => data_get($validatedPayload, 'customer.id'),
                    'name' => data_get($validatedPayload, 'customer.name'),
                ]
                : null,
            'note' => $validatedPayload['note'] ?? '',
            'bill_discount_percent' => (float) ($validatedPayload['bill_discount_percent'] ?? 0),
            'is_damaged' => (bool) ($validatedPayload['is_damaged'] ?? false),
            'is_returned' => (bool) ($validatedPayload['is_returned'] ?? false),
            'bill_date' => $validatedPayload['bill_date'] ?? now()->toDateString(),
            'paid_amount' => (float) ($validatedPayload['paid_amount'] ?? 0),
            'payment_method' => $validatedPayload['payment_method'] ?? 'cash',
            'client_uuid' => $validatedPayload['client_uuid'] ?? null,
            'restaurant' => data_get($validatedPayload, 'restaurant'),
        ];

        $encoded = json_encode($sanitized, JSON_UNESCAPED_UNICODE);
        if ($encoded === false || strlen($encoded) > 262144) {
            throw ValidationException::withMessages([
                'payload' => __('pos.held.validation_too_large'),
            ]);
        }

        return [
            'payload' => $sanitized,
            'items_count' => count($cleanRows),
            'total' => round($total, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformHeldBill(HeldBill $heldBill): array
    {
        $rowIds = collect($heldBill->payload['rows'] ?? [])
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $activeProductIds = $rowIds
            ? Product::withoutGlobalScopes()
                ->where('user_id', $heldBill->user_id)
                ->whereIn('id', $rowIds)
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        return [
            'id' => $heldBill->id,
            'label' => $heldBill->label,
            'customer_id' => $heldBill->customer_id,
            'customer_name' => $heldBill->customer_name ?: data_get($heldBill->payload, 'customer.name'),
            'items_count' => $heldBill->items_count,
            'total' => (float) $heldBill->total,
            'created_at' => optional($heldBill->created_at)->toIso8601String(),
            'created_at_human' => optional($heldBill->created_at)->diffForHumans(),
            'is_stale' => optional($heldBill->created_at)?->lt(now()->subDays(7)) ?? false,
            'held_by' => $heldBill->creator?->name,
            'table_label' => $heldBill->table_label,
            'client_uuid' => $heldBill->client_uuid,
            'inactive_product_ids' => array_values(array_diff($rowIds, $activeProductIds)),
        ];
    }
}
