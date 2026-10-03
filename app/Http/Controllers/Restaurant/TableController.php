<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\RestaurantSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class TableController extends Controller
{
    public function __construct(private readonly RestaurantOrderService $orderService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        RestaurantSupport::ensurePermission($user, 'manage_tables');

        $ownerId = RestaurantSupport::ownerId($user);
        $tables = RestaurantTable::with(['openOrder.latestTicket'])
            ->where('user_id', $ownerId)
            ->orderBy('zone')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $zones = $tables->groupBy(fn (RestaurantTable $table) => $table->zone ?: __('restaurant.labels.unzoned'));

        return view('restaurant.tables.index', [
            'tables' => $tables,
            'zones' => $zones,
            'statusMap' => $tables->mapWithKeys(function (RestaurantTable $table) {
                return [$table->id => $this->orderService->tableStatus($table->openOrder)];
            }),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);

        if ($user->role === 'employee' && ! $user->hasPermission('create_bills') && ! $user->hasPermission('manage_tables')) {
            abort(403);
        }

        $ownerId = RestaurantSupport::ownerId($user);
        $tables = RestaurantTable::with(['openOrder.latestTicket'])
            ->where('user_id', $ownerId)
            ->where('is_active', true)
            ->orderBy('zone')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (RestaurantTable $table) {
                $status = $this->orderService->tableStatus($table->openOrder);

                return [
                    'id' => $table->id,
                    'name' => $table->name,
                    'zone' => $table->zone,
                    'seats' => $table->seats,
                    'sort_order' => $table->sort_order,
                    'is_active' => $table->is_active,
                    'status' => $status,
                    'open_order_id' => $table->openOrder?->id,
                ];
            })
            ->values();

        return response()->json(['tables' => $tables]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        RestaurantSupport::ensurePermission($user, 'manage_tables');
        $ownerId = RestaurantSupport::ownerId($user);

        $data = $request->validate([
            'name' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('restaurant_tables', 'name')->where(fn ($query) => $query->where('user_id', $ownerId)),
            ],
            'zone' => 'nullable|string|max:40',
            'seats' => 'nullable|integer|min:1|max:99',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'bulk_prefix' => 'nullable|string|max:20',
            'bulk_from' => 'nullable|integer|min:1|max:500',
            'bulk_to' => 'nullable|integer|min:1|max:500|gte:bulk_from',
        ]);

        if (($data['bulk_from'] ?? null) !== null && ($data['bulk_to'] ?? null) !== null) {
            $prefix = trim((string) ($data['bulk_prefix'] ?? __('restaurant.defaults.table_prefix')));

            for ($i = (int) $data['bulk_from']; $i <= (int) $data['bulk_to']; $i++) {
                RestaurantTable::firstOrCreate(
                    ['user_id' => $ownerId, 'name' => trim($prefix . ' ' . $i)],
                    [
                        'zone' => $data['zone'] ?? null,
                        'seats' => $data['seats'] ?? null,
                        'sort_order' => $i,
                        'is_active' => true,
                    ],
                );
            }
        } else {
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:40',
                    Rule::unique('restaurant_tables', 'name')->where(fn ($query) => $query->where('user_id', $ownerId)),
                ],
            ]);

            RestaurantTable::create([
                'user_id' => $ownerId,
                'name' => trim((string) $data['name']),
                'zone' => $data['zone'] ?? null,
                'seats' => $data['seats'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => true,
            ]);
        }

        return redirect()->route('restaurant.tables.index')->with('success', __('restaurant.messages.table_saved'));
    }

    public function update(Request $request, RestaurantTable $table): RedirectResponse
    {
        $user = $request->user();
        RestaurantSupport::ensurePermission($user, 'manage_tables');
        $this->authorizeTable($user, $table);

        $ownerId = RestaurantSupport::ownerId($user);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:40',
                Rule::unique('restaurant_tables', 'name')
                    ->where(fn ($query) => $query->where('user_id', $ownerId))
                    ->ignore($table->id),
            ],
            'zone' => 'nullable|string|max:40',
            'seats' => 'nullable|integer|min:1|max:99',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
        ]);

        $table->update([
            'name' => trim($data['name']),
            'zone' => $data['zone'] ?? null,
            'seats' => $data['seats'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', false),
        ]);

        return redirect()->route('restaurant.tables.index')->with('success', __('restaurant.messages.table_saved'));
    }

    public function reorder(Request $request): JsonResponse
    {
        $user = $request->user();
        RestaurantSupport::ensurePermission($user, 'manage_tables');
        $ownerId = RestaurantSupport::ownerId($user);

        $data = $request->validate([
            'tables' => 'required|array|min:1',
            'tables.*.id' => 'required|integer',
            'tables.*.sort_order' => 'required|integer|min:0|max:9999',
        ]);

        foreach ($data['tables'] as $row) {
            RestaurantTable::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey((int) $row['id'])
                ->update(['sort_order' => (int) $row['sort_order']]);
        }

        return response()->json(['message' => __('restaurant.messages.tables_reordered')]);
    }

    public function destroy(Request $request, RestaurantTable $table): RedirectResponse
    {
        $user = $request->user();
        RestaurantSupport::ensurePermission($user, 'manage_tables');
        $this->authorizeTable($user, $table);

        $openOrderExists = RestaurantOrder::withoutGlobalScopes()
            ->where('user_id', $table->user_id)
            ->where('table_id', $table->id)
            ->where('status', 'open')
            ->exists();

        if ($openOrderExists) {
            return back()->withErrors(['table' => __('restaurant.validation.table_in_use')]);
        }

        $table->delete();

        return redirect()->route('restaurant.tables.index')->with('success', __('restaurant.messages.table_deleted'));
    }

    private function authorizeTable($user, RestaurantTable $table): void
    {
        if ((int) $table->user_id !== RestaurantSupport::ownerId($user)) {
            abort(403);
        }
    }
}
