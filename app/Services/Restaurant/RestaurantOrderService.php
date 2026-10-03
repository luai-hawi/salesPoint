<?php

namespace App\Services\Restaurant;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\KitchenTicket;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ShopTime;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurantOrderService
{
    public function __construct(private readonly RestaurantCartCalculator $calculator)
    {
    }

    public function saveFromSnapshot(User $actor, array $payload, ?RestaurantOrder $order = null): RestaurantOrder
    {
        RestaurantSupport::ensureRestaurantAccount($actor);

        $ownerId = RestaurantSupport::ownerId($actor);
        $cart = $this->normalizeCart($payload['rows'] ?? []);
        $table = $this->resolveTable($ownerId, $payload['table_id'] ?? null);
        $customer = $this->resolveCustomer($ownerId, $payload['customer_id'] ?? null);
        $orderType = $payload['order_type'] ?? 'dine_in';

        if ($orderType === 'dine_in' && ! $table) {
            throw ValidationException::withMessages(['table_id' => __('restaurant.validation.table_required')]);
        }

        try {
            return DB::transaction(function () use ($actor, $ownerId, $payload, $order, $cart, $table, $customer, $orderType) {
                if ($order?->exists) {
                    $order = RestaurantOrder::withoutGlobalScopes()
                        ->where('user_id', $ownerId)
                        ->whereKey($order->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($order->status !== 'open') {
                        throw ValidationException::withMessages(['status' => __('restaurant.validation.order_closed')]);
                    }
                } else {
                    $order = new RestaurantOrder([
                        'user_id' => $ownerId,
                        'opened_by' => $actor->id,
                        'status' => 'open',
                    ]);
                }

                if ($table) {
                    $table = RestaurantTable::withoutGlobalScopes()
                        ->where('user_id', $ownerId)
                        ->whereKey($table->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $billDiscountPercent = (float) ($payload['bill_discount_percent'] ?? 0);
                $order->fill([
                    'table_id' => $orderType === 'dine_in' ? $table?->id : null,
                    'order_type' => $orderType,
                    'label' => trim((string) ($payload['label'] ?? '')) ?: $this->defaultLabel($table, $orderType),
                    'guests' => $orderType === 'dine_in' ? ($payload['guests'] ?? null) : null,
                    'customer_id' => $customer?->id,
                    'customer_name' => $payload['customer_name'] ?? $customer?->name,
                    'customer_phone' => $payload['customer_phone'] ?? $customer?->phone,
                    'customer_address' => $payload['customer_address'] ?? null,
                    'cart' => [
                        'rows' => $cart,
                        'customer' => $customer ? ['id' => $customer->id, 'name' => $customer->name] : null,
                        'note' => (string) ($payload['note'] ?? ''),
                        'bill_discount_percent' => $billDiscountPercent,
                        'is_damaged' => (bool) ($payload['is_damaged'] ?? false),
                        'is_returned' => (bool) ($payload['is_returned'] ?? false),
                        'bill_date' => $payload['bill_date'] ?? null,
                        'paid_amount' => $payload['paid_amount'] ?? null,
                        'payment_method' => $payload['payment_method'] ?? null,
                        'table_label' => $table?->name,
                    ],
                    'notes' => (string) ($payload['note'] ?? ''),
                    'total' => $this->calculator->orderTotal($cart, $billDiscountPercent),
                    'cancel_reason' => null,
                    'closed_at' => null,
                    'open_table_key' => $this->openTableKey($ownerId, $orderType === 'dine_in' ? $table?->id : null, 'open'),
                ]);
                $order->save();

                $order->forceFill([
                    'sent_snapshot' => $this->aggregateSentRows($order->fresh('tickets')),
                ])->save();

                ActivityLogger::record(
                    $order->wasRecentlyCreated ? 'created' : 'updated',
                    'kitchen_order',
                    $order,
                    ['table' => $table?->name, 'order_type' => $orderType],
                    (float) $order->total,
                    $order->label,
                    $ownerId,
                );

                return $order->fresh(['table', 'customer', 'tickets']);
            });
        } catch (QueryException $e) {
            if ($this->isUniqueConstraint($e, ['restaurant_orders.open_table_key', 'restaurant_orders_open_table_key_unique'])) {
                throw ValidationException::withMessages(['table_id' => __('restaurant.validation.table_already_open')]);
            }

            throw $e;
        }
    }

    public function sendToKitchen(User $actor, RestaurantOrder $order, string $clientUuid, array $options = []): KitchenTicket
    {
        RestaurantSupport::ensureRestaurantAccount($actor);
        $ownerId = RestaurantSupport::ownerId($actor);

        if ((int) $order->user_id !== $ownerId) {
            abort(403);
        }

        $clientUuid = trim($clientUuid);
        if ($clientUuid === '') {
            throw ValidationException::withMessages(['client_uuid' => __('restaurant.validation.client_uuid_required')]);
        }

        return DB::transaction(function () use ($actor, $ownerId, $order, $clientUuid, $options) {
            $lockedOrder = RestaurantOrder::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== 'open') {
                throw ValidationException::withMessages(['status' => __('restaurant.validation.order_closed')]);
            }

            $existingByUuid = KitchenTicket::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('client_uuid', $clientUuid)
                ->lockForUpdate()
                ->first();

            if ($existingByUuid) {
                if ((int) $existingByUuid->order_id !== (int) $lockedOrder->id) {
                    throw ValidationException::withMessages(['client_uuid' => __('restaurant.validation.kitchen_uuid_conflict')]);
                }

                return $existingByUuid->fresh(['order.table']);
            }

            $currentRows = $this->normalizeCart($lockedOrder->cart['rows'] ?? []);
            $sentRows = $this->aggregateSentRows($lockedOrder->load('tickets'));
            $items = $this->diffRows($currentRows, $sentRows);

            if ($items === []) {
                throw ValidationException::withMessages(['rows' => __('restaurant.validation.no_new_items')]);
            }

            $serviceDate = ShopTime::today($ownerId);
            $ticket = null;

            for ($attempt = 0; $attempt < 3; $attempt++) {
                $number = (int) KitchenTicket::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->where('local_service_date', $serviceDate)
                    ->lockForUpdate()
                    ->max('number') + 1;

                try {
                    $ticket = KitchenTicket::create([
                        'user_id' => $ownerId,
                        'order_id' => $lockedOrder->id,
                        'number' => $number,
                        'local_service_date' => $serviceDate,
                        'items' => $items,
                        'status' => 'new',
                        'priority' => ($options['priority'] ?? 'normal') === 'rush' ? 'rush' : 'normal',
                        'station' => $options['station'] ?? null,
                        'created_by' => $actor->id,
                        'sent_at' => now(),
                        'client_uuid' => $clientUuid,
                        'notes' => $options['notes'] ?? null,
                    ]);
                    break;
                } catch (QueryException $e) {
                    if ($this->isUniqueConstraint($e, ['kitchen_tickets.client_uuid', 'kitchen_tickets_user_id_client_uuid_unique'])) {
                        $existing = KitchenTicket::withoutGlobalScopes()
                            ->where('user_id', $ownerId)
                            ->where('client_uuid', $clientUuid)
                            ->first();

                        if ($existing && (int) $existing->order_id === (int) $lockedOrder->id) {
                            return $existing->fresh(['order.table']);
                        }

                        throw ValidationException::withMessages(['client_uuid' => __('restaurant.validation.kitchen_uuid_conflict')]);
                    }

                    if ($this->isUniqueConstraint($e, ['kitchen_tickets_user_id_local_service_date_number_unique', 'kitchen_tickets.user_id, kitchen_tickets.local_service_date, kitchen_tickets.number'])) {
                        continue;
                    }

                    throw $e;
                }
            }

            if (! $ticket) {
                throw ValidationException::withMessages(['rows' => __('restaurant.messages.send_failed')]);
            }

            $lockedOrder->forceFill([
                'sent_snapshot' => $this->aggregateSentRows($lockedOrder->fresh('tickets')),
            ])->save();

            ActivityLogger::record(
                'created',
                'kitchen_order',
                $lockedOrder,
                ['ticket_number' => $ticket->number, 'ticket_id' => $ticket->id],
                (float) $lockedOrder->total,
                $lockedOrder->label,
                $ownerId,
            );

            return $ticket->fresh(['order.table']);
        });
    }

    public function loadIntoPos(RestaurantOrder $order): array
    {
        $cart = $order->cart ?? [];

        return [
            'rows' => $cart['rows'] ?? [],
            'customer' => $cart['customer'] ?? ($order->customer_id ? [
                'id' => $order->customer_id,
                'name' => $order->customer_name,
            ] : null),
            'note' => $cart['note'] ?? $order->notes,
            'bill_discount_percent' => $cart['bill_discount_percent'] ?? 0,
            'is_damaged' => $cart['is_damaged'] ?? false,
            'is_returned' => $cart['is_returned'] ?? false,
            'bill_date' => $cart['bill_date'] ?? null,
            'paid_amount' => $cart['paid_amount'] ?? null,
            'payment_method' => $cart['payment_method'] ?? null,
            'table_label' => $order->table?->name,
            'restaurant' => [
                'orderId' => $order->id,
                'tableId' => $order->table_id,
                'orderType' => $order->order_type,
                'guests' => $order->guests,
                'delivery' => [
                    'name' => $order->customer_name,
                    'phone' => $order->customer_phone,
                    'address' => $order->customer_address,
                ],
                'notes' => $order->notes,
            ],
        ];
    }

    public function markPaid(User $actor, RestaurantOrder $order, ?Bill $bill = null, ?string $billClientUuid = null): RestaurantOrder
    {
        $ownerId = RestaurantSupport::ownerId($actor);

        if ((int) $order->user_id !== $ownerId) {
            abort(403);
        }

        return DB::transaction(function () use ($ownerId, $order, $bill, $billClientUuid) {
            $lockedOrder = RestaurantOrder::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === 'paid') {
                return $lockedOrder->fresh(['table', 'bill']);
            }

            if ($lockedOrder->status !== 'open') {
                throw ValidationException::withMessages(['status' => __('restaurant.validation.order_closed')]);
            }

            $billClientUuid = $this->normalizeUuid($billClientUuid);
            if (! $bill && $billClientUuid) {
                $bill = Bill::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->where('client_uuid', $billClientUuid)
                    ->lockForUpdate()
                    ->first();
            } elseif ($bill) {
                $bill = Bill::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->whereKey($bill->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if (! $bill) {
                $lockedOrder->forceFill([
                    'pending_bill_client_uuid' => $billClientUuid,
                ])->save();

                return $lockedOrder->fresh(['table', 'bill']);
            }

            $alreadyLinked = RestaurantOrder::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('bill_id', $bill->id)
                ->where('id', '!=', $lockedOrder->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyLinked) {
                throw ValidationException::withMessages(['bill_id' => __('restaurant.validation.bill_already_linked')]);
            }

            if (abs((float) $bill->total_price - (float) $lockedOrder->total) > 0.01) {
                throw ValidationException::withMessages(['bill_id' => __('restaurant.validation.bill_total_mismatch')]);
            }

            $lockedOrder->forceFill([
                'bill_id' => $bill->id,
                'pending_bill_client_uuid' => null,
                'status' => 'paid',
                'closed_at' => now(),
                'open_table_key' => null,
            ])->save();

            ActivityLogger::record(
                'updated',
                'kitchen_order',
                $lockedOrder,
                ['bill_id' => $bill->id, 'status' => 'paid'],
                (float) $lockedOrder->total,
                $lockedOrder->label,
                $ownerId,
            );

            return $lockedOrder->fresh(['table', 'bill']);
        });
    }

    public function cancel(User $actor, RestaurantOrder $order, string $reason): RestaurantOrder
    {
        $ownerId = RestaurantSupport::ownerId($actor);
        if ((int) $order->user_id !== $ownerId) {
            abort(403);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('restaurant.validation.cancel_reason_required')]);
        }

        return DB::transaction(function () use ($order, $reason, $ownerId) {
            $lockedOrder = RestaurantOrder::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== 'open') {
                throw ValidationException::withMessages(['status' => __('restaurant.validation.order_closed')]);
            }

            $lockedOrder->forceFill([
                'status' => 'cancelled',
                'closed_at' => now(),
                'cancel_reason' => $reason,
                'open_table_key' => null,
            ])->save();

            KitchenTicket::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('order_id', $lockedOrder->id)
                ->whereIn('status', ['new', 'preparing', 'ready'])
                ->lockForUpdate()
                ->get()
                ->each(function (KitchenTicket $ticket) use ($reason) {
                    $ticket->forceFill([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'cancel_reason' => $reason,
                    ])->save();
                });

            ActivityLogger::record(
                'updated',
                'kitchen_order',
                $lockedOrder,
                ['status' => 'cancelled', 'reason' => $reason],
                (float) $lockedOrder->total,
                $lockedOrder->label,
                $ownerId,
            );

            return $lockedOrder->fresh(['table', 'tickets']);
        });
    }

    public function moveToTable(User $actor, RestaurantOrder $order, RestaurantTable $table): RestaurantOrder
    {
        $ownerId = RestaurantSupport::ownerId($actor);

        if ((int) $order->user_id !== $ownerId || (int) $table->user_id !== $ownerId || ! $table->is_active) {
            abort(403);
        }

        try {
            return DB::transaction(function () use ($ownerId, $order, $table) {
                $lockedOrder = RestaurantOrder::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedOrder->status !== 'open') {
                    throw ValidationException::withMessages(['status' => __('restaurant.validation.order_closed')]);
                }

                RestaurantTable::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->whereKey($table->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedOrder->forceFill([
                    'table_id' => $table->id,
                    'label' => $table->name,
                    'order_type' => 'dine_in',
                    'open_table_key' => $this->openTableKey($ownerId, $table->id, 'open'),
                ])->save();

                return $lockedOrder->fresh('table');
            });
        } catch (QueryException $e) {
            if ($this->isUniqueConstraint($e, ['restaurant_orders.open_table_key', 'restaurant_orders_open_table_key_unique'])) {
                throw ValidationException::withMessages(['table_id' => __('restaurant.validation.table_already_open')]);
            }

            throw $e;
        }
    }

    public function merge(User $actor, RestaurantOrder $target, RestaurantOrder $source): RestaurantOrder
    {
        $ownerId = RestaurantSupport::ownerId($actor);
        if ((int) $target->user_id !== $ownerId || (int) $source->user_id !== $ownerId) {
            abort(403);
        }

        if ($target->id === $source->id) {
            throw ValidationException::withMessages(['source_order_id' => __('restaurant.validation.invalid_merge')]);
        }

        return DB::transaction(function () use ($ownerId, $target, $source) {
            $ids = [(int) $target->id, (int) $source->id];
            sort($ids);

            $orders = RestaurantOrder::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var RestaurantOrder $lockedTarget */
            $lockedTarget = $orders[$target->id];
            /** @var RestaurantOrder $lockedSource */
            $lockedSource = $orders[$source->id];

            if ($lockedTarget->status !== 'open' || $lockedSource->status !== 'open') {
                throw ValidationException::withMessages(['source_order_id' => __('restaurant.validation.invalid_merge')]);
            }

            $mergedRows = $this->mergeDuplicateRows($this->normalizeCart(array_merge(
                $lockedTarget->cart['rows'] ?? [],
                $lockedSource->cart['rows'] ?? [],
            )));
            $discount = (float) ($lockedTarget->cart['bill_discount_percent'] ?? 0);

            $lockedTarget->forceFill([
                'cart' => array_merge($lockedTarget->cart ?? [], ['rows' => $mergedRows]),
                'total' => $this->calculator->orderTotal($mergedRows, $discount),
            ])->save();

            KitchenTicket::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('order_id', $lockedSource->id)
                ->lockForUpdate()
                ->update(['order_id' => $lockedTarget->id, 'updated_at' => now()]);

            $lockedSource->forceFill([
                'status' => 'cancelled',
                'closed_at' => now(),
                'cancel_reason' => __('restaurant.system.merged_into', ['label' => $lockedTarget->label]),
                'open_table_key' => null,
            ])->save();

            $lockedTarget->forceFill([
                'sent_snapshot' => $this->aggregateSentRows($lockedTarget->fresh('tickets')),
            ])->save();

            ActivityLogger::record(
                'updated',
                'kitchen_order',
                $lockedTarget,
                ['merged_order_id' => $lockedSource->id],
                (float) $lockedTarget->total,
                $lockedTarget->label,
                $ownerId,
            );

            return $lockedTarget->fresh(['table', 'tickets']);
        });
    }

    public function resolvePendingBillLinks(int $ownerId): int
    {
        $resolved = 0;

        RestaurantOrder::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('status', 'open')
            ->whereNotNull('pending_bill_client_uuid')
            ->orderBy('id')
            ->chunkById(25, function (Collection $orders) use ($ownerId, &$resolved) {
                foreach ($orders as $order) {
                    $bill = Bill::withoutGlobalScopes()
                        ->where('user_id', $ownerId)
                        ->where('client_uuid', $order->pending_bill_client_uuid)
                        ->first();

                    if (! $bill) {
                        continue;
                    }

                    $systemActor = User::withoutGlobalScopes()->find($order->opened_by) ?: User::withoutGlobalScopes()->find($ownerId);
                    if (! $systemActor) {
                        continue;
                    }

                    try {
                        $this->markPaid($systemActor, $order, $bill, $order->pending_bill_client_uuid);
                        $resolved++;
                    } catch (\Throwable) {
                        continue;
                    }
                }
            });

        return $resolved;
    }

    public function ticketStatus(RestaurantOrder $order): string
    {
        $latest = $order->relationLoaded('latestTicket') ? $order->latestTicket : $order->latestTicket()->first();

        return $latest?->status ?? 'free';
    }

    public function tableStatus(?RestaurantOrder $order): array
    {
        if (! $order) {
            return ['code' => 'free', 'label' => __('restaurant.status.free'), 'tone' => 'green'];
        }

        if ($order->pending_bill_client_uuid) {
            return ['code' => 'waiting_bill', 'label' => __('restaurant.labels.pending_payment_link'), 'tone' => 'indigo'];
        }

        $latest = $order->relationLoaded('latestTicket') ? $order->latestTicket : $order->latestTicket()->first();

        return match ($latest?->status) {
            'ready' => ['code' => 'ready', 'label' => __('restaurant.status.ready'), 'tone' => 'amber'],
            'served' => ['code' => 'waiting_bill', 'label' => __('restaurant.status.waiting_bill'), 'tone' => 'indigo'],
            default => ['code' => 'occupied', 'label' => __('restaurant.status.occupied'), 'tone' => 'red'],
        };
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function normalizeCart(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $productId = Arr::get($row, 'product_id');
            $name = trim((string) Arr::get($row, 'name', ''));
            $quantity = (float) Arr::get($row, 'quantity', 0);

            if ($name === '' || $quantity <= 0) {
                continue;
            }

            $tags = Arr::get($row, 'tags', []);
            $note = trim((string) Arr::get($row, 'note', ''));

            $normalized[] = [
                'product_id' => $productId ? (int) $productId : null,
                'name' => $name,
                'quantity' => round($quantity, 3),
                'selling_price' => round((float) Arr::get($row, 'selling_price', 0), 2),
                'cost_price' => round((float) Arr::get($row, 'cost_price', 0), 2),
                'discount' => round((float) Arr::get($row, 'discount', 0), 2),
                'discount_type' => Arr::get($row, 'discount_type', 'total'),
                'tags' => $tags,
                'imeis' => array_values(array_filter((array) Arr::get($row, 'imeis', []))),
                'note' => $note,
                'signature' => $this->rowSignature([
                    'product_id' => $productId ? (int) $productId : null,
                    'name' => $name,
                    'selling_price' => round((float) Arr::get($row, 'selling_price', 0), 2),
                    'note' => $note,
                    'tags' => $tags,
                    'imeis' => array_values(array_filter((array) Arr::get($row, 'imeis', []))),
                ]),
            ];
        }

        if (count($normalized) > 100) {
            throw ValidationException::withMessages(['rows' => __('restaurant.validation.too_many_items')]);
        }

        return $normalized;
    }

    private function resolveTable(int $ownerId, mixed $tableId): ?RestaurantTable
    {
        if (! $tableId) {
            return null;
        }

        return RestaurantTable::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereKey((int) $tableId)
            ->firstOrFail();
    }

    private function resolveCustomer(int $ownerId, mixed $customerId): ?Customer
    {
        if (! $customerId) {
            return null;
        }

        return Customer::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereKey((int) $customerId)
            ->firstOrFail();
    }

    private function defaultLabel(?RestaurantTable $table, string $orderType): string
    {
        return $table?->name ?: __('restaurant.order_types.' . $orderType);
    }

    private function openTableKey(int $ownerId, ?int $tableId, string $status): ?string
    {
        return $status === 'open' && $tableId ? $ownerId . ':' . $tableId : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $currentRows
     * @param  array<int, array<string, mixed>>  $sentRows
     * @return array<int, array<string, mixed>>
     */
    private function diffRows(array $currentRows, array $sentRows): array
    {
        $sentMap = [];
        foreach ($sentRows as $row) {
            $signature = (string) ($row['signature'] ?? $this->rowSignature($row));
            $sentMap[$signature] = ($sentMap[$signature] ?? 0) + (float) $row['quantity'];
        }

        $diff = [];
        foreach ($currentRows as $row) {
            $signature = (string) ($row['signature'] ?? $this->rowSignature($row));
            $remaining = round((float) $row['quantity'] - ($sentMap[$signature] ?? 0), 3);
            if ($remaining <= 0) {
                continue;
            }

            $diff[] = [
                'product_id' => $row['product_id'],
                'name' => $row['name'],
                'quantity' => $remaining,
                'note' => $this->ticketItemNote($row),
                'signature' => $signature,
            ];
        }

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowSignature(array $row): string
    {
        return sha1(json_encode([
            'product_id' => $row['product_id'] ?? null,
            'name' => $row['name'] ?? '',
            'selling_price' => $row['selling_price'] ?? 0,
            'note' => $row['note'] ?? '',
            'tags' => $row['tags'] ?? [],
            'imeis' => $row['imeis'] ?? [],
        ]));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function ticketItemNote(array $row): ?string
    {
        $parts = [];

        if (! empty($row['note'])) {
            $parts[] = trim((string) $row['note']);
        }

        $tags = $row['tags'] ?? [];
        if (is_array($tags) && $tags !== []) {
            $parts[] = implode(', ', array_map('strval', $tags));
        } elseif (is_string($tags) && trim($tags) !== '') {
            $parts[] = trim($tags);
        }

        return $parts ? implode(' • ', $parts) : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function mergeDuplicateRows(array $rows): array
    {
        $merged = [];

        foreach ($rows as $row) {
            $key = (string) ($row['signature'] ?? $this->rowSignature($row));
            if (! isset($merged[$key])) {
                $merged[$key] = $row;
                continue;
            }

            $merged[$key]['quantity'] = round((float) $merged[$key]['quantity'] + (float) $row['quantity'], 3);
        }

        return array_values($merged);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function aggregateSentRows(RestaurantOrder $order): array
    {
        $sent = [];
        $tickets = $order->relationLoaded('tickets') ? $order->tickets : $order->tickets()->get();

        foreach ($tickets as $ticket) {
            if ($ticket->status === 'cancelled') {
                continue;
            }

            foreach ((array) $ticket->items as $item) {
                $signature = (string) ($item['signature'] ?? '');
                if ($signature === '') {
                    continue;
                }

                if (! isset($sent[$signature])) {
                    $sent[$signature] = [
                        'signature' => $signature,
                        'quantity' => 0,
                    ];
                }

                $sent[$signature]['quantity'] += (float) ($item['quantity'] ?? 0);
            }
        }

        return array_values($sent);
    }

    private function normalizeUuid(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  list<string>  $needles
     */
    private function isUniqueConstraint(QueryException $e, array $needles): bool
    {
        $message = strtolower($e->getMessage());
        foreach ($needles as $needle) {
            if (str_contains($message, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
