<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\RestaurantSupport;
use App\Support\ShopTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        private readonly RestaurantOrderService $orderService,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        $this->authorizePosUse($user);

        $ownerId = RestaurantSupport::ownerId($user);
        $this->orderService->resolvePendingBillLinks($ownerId);

        $orders = RestaurantOrder::with(['table', 'latestTicket', 'bill'])
            ->where('user_id', $ownerId)
            ->when($request->boolean('open_only', true), fn ($query) => $query->where('status', 'open'))
            ->orderByDesc('updated_at')
            ->paginate(25);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'orders' => $orders->getCollection()->map(fn (RestaurantOrder $order) => $this->serializeOrder($order))->values(),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                ],
            ]);
        }

        return view('restaurant.orders.index', ['orders' => $orders]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        $this->authorizePosUse($user);

        $data = $this->validatedPayload($request);
        $order = $this->orderService->saveFromSnapshot($user, $data);

        return response()->json([
            'message' => __('restaurant.messages.order_saved'),
            'order' => $this->serializeOrder($order->load(['table', 'latestTicket'])),
        ]);
    }

    public function update(Request $request, RestaurantOrder $order): JsonResponse
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        $this->authorizePosUse($user);
        $this->authorizeOrder($user, $order);

        $data = $this->validatedPayload($request);
        $order = $this->orderService->saveFromSnapshot($user, $data, $order);

        return response()->json([
            'message' => __('restaurant.messages.order_saved'),
            'order' => $this->serializeOrder($order->load(['table', 'latestTicket'])),
        ]);
    }

    public function show(Request $request, RestaurantOrder $order)
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        $this->authorizePosUse($user);
        $this->authorizeOrder($user, $order);
        $this->orderService->resolvePendingBillLinks(RestaurantSupport::ownerId($user));

        $order->load(['table', 'tickets', 'latestTicket', 'bill']);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['order' => $this->serializeOrder($order)]);
        }

        return view('restaurant.orders.index', [
            'orders' => RestaurantOrder::with(['table', 'latestTicket', 'bill'])
                ->where('user_id', $order->user_id)
                ->orderByDesc('updated_at')
                ->paginate(25),
            'selectedOrder' => $order,
        ]);
    }

    public function load(RestaurantOrder $order, Request $request): JsonResponse
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        $this->authorizePosUse($user);
        $this->authorizeOrder($user, $order);
        $this->orderService->resolvePendingBillLinks(RestaurantSupport::ownerId($user));

        $order = RestaurantOrder::with(['table', 'latestTicket', 'bill'])
            ->where('user_id', RestaurantSupport::ownerId($user))
            ->findOrFail($order->id);

        return response()->json([
            'snapshot' => $this->orderService->loadIntoPos($order->load('table')),
            'order' => $this->serializeOrder($order),
        ]);
    }

    public function sendToKitchen(Request $request, RestaurantOrder $order): JsonResponse
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        $this->authorizePosUse($user);
        $this->authorizeOrder($user, $order);

        $data = $request->validate([
            'client_uuid' => 'required|string|max:64',
            'priority' => ['nullable', Rule::in(['normal', 'rush'])],
            'station' => 'nullable|string|max:40',
            'notes' => 'nullable|string|max:500',
        ]);

        $ticket = $this->orderService->sendToKitchen($user, $order, $data['client_uuid'], $data);

        return response()->json([
            'message' => __('restaurant.messages.ticket_sent'),
            'ticket' => $ticket,
        ]);
    }

    public function markPaid(Request $request, RestaurantOrder $order): JsonResponse
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        $this->authorizePosUse($user);
        $this->authorizeOrder($user, $order);

        $data = $request->validate([
            'bill_id' => 'nullable|integer',
            'bill_client_uuid' => 'nullable|string|max:64',
        ]);
        if (empty($data['bill_id']) && empty($data['bill_client_uuid'])) {
            return response()->json([
                'message' => __('restaurant.messages.bill_link_failed'),
                'errors' => ['bill_id' => [__('restaurant.messages.bill_link_failed')]],
            ], 422);
        }
        $bill = null;
        if (! empty($data['bill_id'])) {
            $bill = Bill::withoutGlobalScopes()
                ->where('user_id', RestaurantSupport::ownerId($user))
                ->findOrFail((int) $data['bill_id']);
        }

        $order = $this->orderService->markPaid($user, $order, $bill, $data['bill_client_uuid'] ?? null);

        return response()->json([
            'message' => $order->pending_bill_client_uuid
                ? __('restaurant.messages.bill_link_pending')
                : __('restaurant.messages.order_paid'),
            'order' => $this->serializeOrder($order->load(['table', 'bill', 'latestTicket'])),
        ]);
    }

    public function cancel(Request $request, RestaurantOrder $order): JsonResponse
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        $this->authorizePosUse($user);
        $this->authorizeOrder($user, $order);

        $data = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $order = $this->orderService->cancel($user, $order, $data['reason']);

        return response()->json([
            'message' => __('restaurant.messages.order_cancelled'),
            'order' => $this->serializeOrder($order->load(['table', 'latestTicket'])),
        ]);
    }

    public function move(Request $request, RestaurantOrder $order): JsonResponse
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        $this->authorizePosUse($user);
        $this->authorizeOrder($user, $order);

        $data = $request->validate([
            'table_id' => 'required|integer',
        ]);

        $table = RestaurantTable::withoutGlobalScopes()
            ->where('user_id', RestaurantSupport::ownerId($user))
            ->findOrFail((int) $data['table_id']);

        $order = $this->orderService->moveToTable($user, $order, $table);

        return response()->json([
            'message' => __('restaurant.messages.order_moved'),
            'order' => $this->serializeOrder($order->load(['table', 'latestTicket'])),
        ]);
    }

    public function merge(Request $request, RestaurantOrder $order): JsonResponse
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        $this->authorizePosUse($user);
        $this->authorizeOrder($user, $order);

        $data = $request->validate([
            'source_order_id' => 'required|integer',
        ]);

        $source = RestaurantOrder::withoutGlobalScopes()
            ->where('user_id', RestaurantSupport::ownerId($user))
            ->findOrFail((int) $data['source_order_id']);

        $order = $this->orderService->merge($user, $order, $source);

        return response()->json([
            'message' => __('restaurant.messages.order_merged'),
            'order' => $this->serializeOrder($order->load(['table', 'latestTicket'])),
        ]);
    }

    public function print(RestaurantOrder $order, Request $request)
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        $this->authorizePosUse($user);
        $this->authorizeOrder($user, $order);

        return view('kitchen.print', [
            'ticket' => $order->tickets()->latest()->firstOrFail(),
        ]);
    }

    private function authorizePosUse($user): void
    {
        if ($user->role === 'employee' && ! $user->hasPermission('create_bills')) {
            abort(403);
        }
    }

    private function authorizeOrder($user, RestaurantOrder $order): void
    {
        if ((int) $order->user_id !== RestaurantSupport::ownerId($user)) {
            abort(403);
        }
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'table_id' => 'nullable|integer',
            'order_type' => ['required', Rule::in(['dine_in', 'takeaway', 'delivery'])],
            'label' => 'nullable|string|max:80',
            'guests' => 'nullable|integer|min:1|max:99',
            'customer_id' => 'nullable|integer',
            'customer_name' => 'nullable|string|max:120',
            'customer_phone' => 'nullable|string|max:40',
            'customer_address' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'bill_discount_percent' => 'nullable|numeric|min:0|max:100',
            'paid_amount' => 'nullable|numeric|min:0|max:9999999.99',
            'payment_method' => 'nullable|string|max:20',
            'bill_date' => 'nullable|date',
            'is_damaged' => 'nullable|boolean',
            'is_returned' => 'nullable|boolean',
            'rows' => 'required|array|min:1|max:100',
            'rows.*.product_id' => 'nullable|integer',
            'rows.*.name' => 'required|string|max:255',
            'rows.*.quantity' => 'required|numeric|min:0.001|max:9999',
            'rows.*.selling_price' => 'required|numeric|min:0|max:9999999.99',
            'rows.*.cost_price' => 'nullable|numeric|min:0|max:9999999.99',
            'rows.*.discount' => 'nullable|numeric|min:0|max:9999999.99',
            'rows.*.discount_type' => ['nullable', Rule::in(['total', 'per-unit'])],
            'rows.*.tags' => 'nullable',
            'rows.*.imeis' => 'nullable|array|max:50',
            'rows.*.imeis.*' => 'string|max:100',
            'rows.*.note' => 'nullable|string|max:255',
        ]);
    }

    private function serializeOrder(RestaurantOrder $order): array
    {
        $latestTicket = $order->relationLoaded('latestTicket') ? $order->latestTicket : $order->latestTicket()->first();

        return [
            'id' => $order->id,
            'label' => $order->label,
            'order_type' => $order->order_type,
            'status' => $order->status,
            'guests' => $order->guests,
            'total' => (float) $order->total,
            'table_id' => $order->table_id,
            'table_name' => $order->table?->name,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_address' => $order->customer_address,
            'updated_at' => optional($order->updated_at)->toISOString(),
            'opened_at' => optional($order->created_at)->toISOString(),
            'elapsed_minutes' => $order->created_at ? $order->created_at->diffInMinutes(now()) : 0,
            'local_opened_at' => $order->created_at ? ShopTime::local($order->created_at, $order->user_id)->format('Y-m-d H:i') : null,
            'latest_ticket_status' => $latestTicket?->status,
            'latest_ticket_number' => $latestTicket?->number,
            'bill_id' => $order->bill_id,
            'pending_bill_client_uuid' => $order->pending_bill_client_uuid,
            'needs_bill_link' => (bool) $order->pending_bill_client_uuid,
            'rows_count' => count($order->cart['rows'] ?? []),
        ];
    }
}
