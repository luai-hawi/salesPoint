<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Bill;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AdminDashboardController extends Controller
{
    public function index()
    {
        try {
            // Cache expensive queries for 5 minutes
            $stats = Cache::remember('admin_dashboard_stats', 300, function () {
                return [
                    'total_shop_owners' => User::whereIn('role', ['shop_owner','disabled','restaurant','merchant'])->count(),
                    'disabled_shop_owners' => User::where('role', 'disabled')->count(),
                    'total_employees' => User::where('role', 'employee')->count(),
                    'total_admins' => User::where('role', 'admin')->count(),
                    'total_sales_today' => Bill::whereDate('created_at', Carbon::today())->sum('total_price') ?? 0,
                    'total_sales_month' => Bill::whereMonth('created_at', Carbon::now()->month)
                                             ->whereYear('created_at', Carbon::now()->year)
                                             ->sum('total_price') ?? 0,
                    'total_products' => Product::count(),
                    'total_customers' => Customer::count(),
                ];
            });

            // Get shop owners with performance metrics
            $shopOwners = $this->getShopOwnersWithMetrics();

            // Get sales chart data for the last 7 days
            $salesChartData = $this->getSalesChartData();

            // Get top performing shop owners (by monthly sales)
            $topShopOwners = $shopOwners->sortByDesc('sales_this_month')->take(5);

            return view('admin.dashboard', compact(
                'stats', 
                'shopOwners', 
                'salesChartData',
                'topShopOwners'
            ));
            
        } catch (\Exception $e) {
            // Log error and return with empty data
            \Log::error('Dashboard error: ' . $e->getMessage());
            
            $stats = [
                'total_shop_owners' => 0,
                'disabled_shop_owners' => 0,
                'total_employees' => 0,
                'total_admins' => 0,
                'total_sales_today' => 0,
                'total_sales_month' => 0,
                'total_products' => 0,
                'total_customers' => 0,
            ];
            
            $shopOwners = collect();
            $salesChartData = [];
            $topShopOwners = collect();
            
            return view('admin.dashboard', compact(
                'stats', 
                'shopOwners', 
                'salesChartData',
                'topShopOwners'
            ))->with('error', 'Some dashboard data could not be loaded.');
        }
    }

    /**
     * Get shop owners with their performance metrics
     */
    private function getShopOwnersWithMetrics()
    {
        return User::where('role', 'shop_owner,disabled,restaurant,merchant')
            ->withCount(['employees'])
            ->with(['employees' => function($query) {
                $query->select('id', 'name', 'email', 'shop_owner_id', 'created_at')
                      ->latest()
                      ->take(10); // Limit for performance
            }])
            ->latest()
            ->take(20) // Limit for dashboard performance
            ->get()
            ->map(function($shopOwner) {
                try {
                    // Get all user IDs for this shop (owner + employees)
                    $userIds = collect([$shopOwner->id])
                        ->merge($shopOwner->employees->pluck('id'))
                        ->filter()
                        ->unique();
                    
                    if ($userIds->isEmpty()) {
                        $userIds = collect([$shopOwner->id]);
                    }
                    
                    // Get shop owner's metrics with error handling
                    $shopOwner->total_sales = $this->getSafeSum(
                        Bill::whereIn('user_id', $userIds), 'total_price'
                    );
                    
                    $shopOwner->sales_this_month = $this->getSafeSum(
                        Bill::whereIn('user_id', $userIds)
                            ->whereMonth('created_at', Carbon::now()->month)
                            ->whereYear('created_at', Carbon::now()->year),
                        'total_price'
                    );

                    $shopOwner->sales_today = $this->getSafeSum(
                        Bill::whereIn('user_id', $userIds)
                            ->whereDate('created_at', Carbon::today()),
                        'total_price'
                    );

                    // Get counts with error handling
                    $shopOwner->products_count = Product::where('user_id', $shopOwner->id)->count() ?? 0;
                    $shopOwner->customers_count = Customer::where('user_id', $shopOwner->id)->count() ?? 0;

                    // Get last activity
                    $shopOwner->last_activity = Bill::whereIn('user_id', $userIds)
                        ->latest()
                        ->value('created_at');

                } catch (\Exception $e) {
                    // Set default values if any query fails
                    $shopOwner->total_sales = 0;
                    $shopOwner->sales_this_month = 0;
                    $shopOwner->sales_today = 0;
                    $shopOwner->products_count = 0;
                    $shopOwner->customers_count = 0;
                    $shopOwner->last_activity = null;
                }

                return $shopOwner;
            });
    }

    /**
     * Get sales chart data for the last 7 days
     */
    private function getSalesChartData()
    {
        $salesChartData = [];
        
        try {
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $sales = Bill::whereDate('created_at', $date->toDateString())
                    ->sum('total_price') ?? 0;
                    
                $salesChartData[] = [
                    'date' => $date->format('M d'),
                    'sales' => (float) $sales
                ];
            }
        } catch (\Exception $e) {
            // Return empty chart data if query fails
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $salesChartData[] = [
                    'date' => $date->format('M d'),
                    'sales' => 0
                ];
            }
        }

        return $salesChartData;
    }

    /**
     * Safely get sum with error handling
     */
    private function getSafeSum($query, $column)
    {
        try {
            return $query->sum($column) ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}