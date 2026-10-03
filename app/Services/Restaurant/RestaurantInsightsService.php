<?php

namespace App\Services\Restaurant;

use App\Models\User;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RestaurantInsightsService
{
    public function build(User $user, string $from, string $to): array
    {
        $ownerId = RestaurantSupport::ownerId($user);
        [$utcFrom, $utcTo] = ShopTime::utcRange($from, $to, $ownerId);

        $baseOrders = DB::table('restaurant_orders')
            ->where('user_id', $ownerId)
            ->where('status', 'paid')
            ->whereNotNull('bill_id')
            ->whereBetween('closed_at', [$utcFrom, $utcTo]);

        $salesByType = (clone $baseOrders)
            ->select('order_type', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total) as total_sales'))
            ->groupBy('order_type')
            ->orderByDesc('total_sales')
            ->get();

        $salesByTable = DB::table('restaurant_orders')
            ->leftJoin('restaurant_tables', 'restaurant_tables.id', '=', 'restaurant_orders.table_id')
            ->where('restaurant_orders.user_id', $ownerId)
            ->where('restaurant_orders.status', 'paid')
            ->whereNotNull('restaurant_orders.bill_id')
            ->whereBetween('restaurant_orders.closed_at', [$utcFrom, $utcTo])
            ->selectRaw("COALESCE(restaurant_tables.name, restaurant_orders.label, '—') as label")
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(restaurant_orders.total) as total_sales')
            ->groupByRaw("COALESCE(restaurant_tables.name, restaurant_orders.label, '—')")
            ->orderByDesc('total_sales')
            ->get();

        $timedOrders = DB::table('restaurant_orders')
            ->join('bills', 'bills.id', '=', 'restaurant_orders.bill_id')
            ->select('restaurant_orders.id', 'bills.total_price', 'bills.created_at')
            ->where('restaurant_orders.user_id', $ownerId)
            ->whereBetween('restaurant_orders.closed_at', [$utcFrom, $utcTo])
            ->get()
            ->map(function ($row) use ($ownerId) {
                $local = ShopTime::local($row->created_at, $ownerId);
                $row->local_hour = $local->format('H:00');
                $row->local_weekday = (string) $local->dayOfWeek;

                return $row;
            });

        $salesByHour = $this->groupTimedOrders($timedOrders, 'local_hour');
        $salesByWeekday = $this->groupTimedOrders($timedOrders, 'local_weekday')
            ->map(function ($row) {
                $row->label = __('restaurant.weekdays.' . $row->label);

                return $row;
            });

        $items = DB::table('restaurant_orders')
            ->join('bills', 'bills.id', '=', 'restaurant_orders.bill_id')
            ->join('bill_product', 'bill_product.bill_id', '=', 'bills.id')
            ->join('products', 'products.id', '=', 'bill_product.product_id')
            ->where('restaurant_orders.user_id', $ownerId)
            ->whereBetween('restaurant_orders.closed_at', [$utcFrom, $utcTo])
            ->select(
                'products.name',
                DB::raw('SUM(ABS(bill_product.quantity)) as quantity_sold'),
                DB::raw('SUM((bill_product.selling_price * ABS(bill_product.quantity)) - bill_product.discount) as gross_sales')
            )
            ->groupBy('products.name')
            ->orderByDesc('quantity_sold')
            ->get();

        $topItems = $items->take(5)->values();
        $bottomItems = $items->sortBy('quantity_sold')->take(5)->values();

        $durations = DB::table('kitchen_tickets')
            ->where('user_id', $ownerId)
            ->whereBetween('sent_at', [$utcFrom, $utcTo])
            ->get(['sent_at', 'ready_at', 'served_at']);

        $prepMinutes = [];
        $serviceMinutes = [];
        foreach ($durations as $row) {
            if ($row->sent_at && $row->ready_at) {
                $prepMinutes[] = Carbon::parse($row->sent_at)->diffInMinutes(Carbon::parse($row->ready_at));
            }
            if ($row->ready_at && $row->served_at) {
                $serviceMinutes[] = Carbon::parse($row->ready_at)->diffInMinutes(Carbon::parse($row->served_at));
            }
        }

        $cancelledOrders = DB::table('restaurant_orders')
            ->where('user_id', $ownerId)
            ->where('status', 'cancelled')
            ->whereBetween('updated_at', [$utcFrom, $utcTo])
            ->select('label', 'order_type', 'cancel_reason', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get();

        return [
            'from' => $from,
            'to' => $to,
            'salesByType' => $salesByType,
            'salesByTable' => $salesByTable,
            'salesByHour' => $salesByHour,
            'salesByWeekday' => $salesByWeekday,
            'topItems' => $topItems,
            'bottomItems' => $bottomItems,
            'cancelledOrders' => $cancelledOrders,
            'avgPreparationMinutes' => round(collect($prepMinutes)->avg() ?? 0, 1),
            'avgServiceMinutes' => round(collect($serviceMinutes)->avg() ?? 0, 1),
            'busiestHour' => $salesByHour->sortByDesc('orders_count')->first(),
            'busiestWeekday' => $salesByWeekday->sortByDesc('orders_count')->first(),
        ];
    }

    /**
     * @param  Collection<int, object>  $orders
     */
    private function groupTimedOrders(Collection $orders, string $field): Collection
    {
        return $orders
            ->groupBy($field)
            ->map(function (Collection $group, string $label) {
                return (object) [
                    'label' => $label,
                    'orders_count' => $group->count(),
                    'total_sales' => round((float) $group->sum('total_price'), 2),
                ];
            })
            ->sortBy('label')
            ->values();
    }
}
