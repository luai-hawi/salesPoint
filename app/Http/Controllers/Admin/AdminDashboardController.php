<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDashboardController extends Controller
{
    public function index()
    {
        try {
            // Cache expensive queries for 5 minutes
            $stats = Cache::remember('admin_dashboard_stats', 300, function () {
                $today = Carbon::today();
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();

                // Get today's and month's bills
                $todayBills = Bill::withoutGlobalScopes()->whereDate('created_at', $today);
                $monthBills = Bill::withoutGlobalScopes()->whereBetween('created_at', [$startOfMonth, $endOfMonth]);

                // Calculate total sales today and this month
                $totalSalesToday = $todayBills->sum('total_price') ?? 0;
                $totalSalesMonth = $monthBills->sum('total_price') ?? 0;

                // Calculate profit (difference between selling and cost price)
                $profitToday = $this->calculateProfit($todayBills);
                $profitMonth = $this->calculateProfit($monthBills);

                // Bill counts
                $billsCountToday = $todayBills->count() ?? 0;
                $billsCountMonth = $monthBills->count() ?? 0;

                // Products sold today and this month
                $productsSoldToday = $this->getProductsSoldCount($todayBills);
                $productsSoldMonth = $this->getProductsSoldCount($monthBills);

                // Average bill value
                $avgBillValueToday = $billsCountToday > 0 ? $totalSalesToday / $billsCountToday : 0;
                $avgBillValueMonth = $billsCountMonth > 0 ? $totalSalesMonth / $billsCountMonth : 0;

                // Get total expenses this month
                $totalExpensesMonth = Expense::withoutGlobalScopes()->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->sum('amount') ?? 0;

                return [
                    'total_shop_owners' => User::whereIn('role', ['shop_owner', 'disabled', 'restaurant', 'merchant'])->count(),
                    'disabled_shop_owners' => User::where('role', 'disabled')->count(),
                    'total_employees' => User::where('role', 'employee')->count(),
                    'total_admins' => User::where('role', 'admin')->count(),
                    'full_accounts_count' => User::where('account_type', 'full')->whereIn('role', ['shop_owner', 'disabled', 'restaurant', 'merchant'])->count(),
                    'active_full_accounts_count' => User::where('account_type', 'full')->where('role', 'shop_owner')->count(),
                    'temp_accounts_count' => User::where('account_type', 'temp')->whereIn('role', ['shop_owner', 'disabled', 'restaurant', 'merchant'])->count(),
                    'expired_temp_accounts_count' => User::expiredTempAccounts()->whereIn('role', ['shop_owner', 'restaurant', 'merchant'])->count(),
                    'disabled_expired_accounts_count' => User::expiredTempAccounts()->where('role', 'disabled')->count(),
                    'total_sales_today' => $totalSalesToday,
                    'total_sales_month' => $totalSalesMonth,
                    'profit_today' => $profitToday,
                    'profit_month' => $profitMonth,
                    'bills_count_today' => $billsCountToday,
                    'bills_count_month' => $billsCountMonth,
                    'products_sold_today' => $productsSoldToday,
                    'products_sold_month' => $productsSoldMonth,
                    'avg_bill_value_today' => $avgBillValueToday,
                    'avg_bill_value_month' => $avgBillValueMonth,
                    'total_expenses_month' => $totalExpensesMonth,
                    'total_products' => Product::withoutGlobalScopes()->count(),
                    'total_customers' => Customer::withoutGlobalScopes()->count(),
                ];
            });

            // Get shop owners with performance metrics (no limit)
            $shopOwners = $this->getShopOwnersWithMetrics();

            // Get sales chart data for the last 7 days
            $salesChartData = $this->getSalesChartData();

            // Get top performing shop owners (by monthly sales)
            $topShopOwners = $shopOwners->sortByDesc('sales_this_month')->take(5);

            // Get expired temp accounts for deletion confirmation (only shop owners, not employees)
            $expiredTempAccounts = User::expiredTempAccounts()
                ->whereIn('role', ['shop_owner', 'restaurant', 'merchant'])
                ->get(['id', 'name', 'email']);

            // Get disabled expired accounts for deletion confirmation
            $disabledExpiredAccounts = User::where('account_type', 'temp')
                ->whereNotNull('temp_expires_at')
                ->where('temp_expires_at', '<', now())
                ->where('role', 'disabled')
                ->get(['id', 'name', 'email']);

            return view('admin.dashboard', compact(
                'stats',
                'shopOwners',
                'salesChartData',
                'topShopOwners',
                'expiredTempAccounts',
                'disabledExpiredAccounts'
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
                'profit_today' => 0,
                'profit_month' => 0,
                'bills_count_today' => 0,
                'bills_count_month' => 0,
                'products_sold_today' => 0,
                'products_sold_month' => 0,
                'avg_bill_value_today' => 0,
                'avg_bill_value_month' => 0,
                'total_expenses_month' => 0,
                'total_products' => 0,
                'total_customers' => 0,
            ];

            $shopOwners = collect();
            $salesChartData = [];
            $topShopOwners = collect();
            $expiredTempAccounts = collect();
            $disabledExpiredAccounts = collect();

            return view('admin.dashboard', compact(
                'stats',
                'shopOwners',
                'salesChartData',
                'topShopOwners',
                'expiredTempAccounts',
                'disabledExpiredAccounts'
            ))->with('error', 'Some dashboard data could not be loaded.');
        }
    }

    /**
     * Calculate total profit from bills
     */
    private function calculateProfit($bills)
    {
        try {
            $billIds = $bills->pluck('id');
            if ($billIds->isEmpty()) {
                return 0;
            }

            $profit = DB::table('bill_product')
                ->whereIn('bill_id', $billIds)
                ->selectRaw('SUM((selling_price - cost_price) * quantity) as total_profit')
                ->value('total_profit');

            return $profit ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get total products sold count
     */
    private function getProductsSoldCount($bills)
    {
        try {
            $billIds = $bills->pluck('id');
            if ($billIds->isEmpty()) {
                return 0;
            }

            return DB::table('bill_product')
                ->whereIn('bill_id', $billIds)
                ->sum('quantity') ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get shop owners with their performance metrics (no limit)
     */
    private function getShopOwnersWithMetrics()
    {
        return User::whereIn('role', ['shop_owner', 'disabled', 'restaurant', 'merchant'])
            ->withCount(['employees'])
            ->with(['employees' => function ($query) {
                $query->select('id', 'name', 'email', 'shop_owner_id', 'created_at')
                    ->latest()
                    ->take(10); // Limit for performance
            }])
            ->latest()
            ->get()
            ->map(function ($shopOwner) {
                try {
                    // Get all user IDs for this shop (owner + employees)
                    $userIds = collect([$shopOwner->id])
                        ->merge($shopOwner->employees->pluck('id'))
                        ->filter()
                        ->unique();

                    if ($userIds->isEmpty()) {
                        $userIds = collect([$shopOwner->id]);
                    }

                    // Get today's and month's date ranges
                    $today = Carbon::today();
                    $startOfMonth = Carbon::now()->startOfMonth();
                    $endOfMonth = Carbon::now()->endOfMonth();

                    // Get all bills for this shop
                    $allBills   = Bill::withoutGlobalScopes()->whereIn('user_id', $userIds);
                    $todayBills = Bill::withoutGlobalScopes()->whereIn('user_id', $userIds)->whereDate('created_at', $today);
                    $monthBills = Bill::withoutGlobalScopes()->whereIn('user_id', $userIds)->whereBetween('created_at', [$startOfMonth, $endOfMonth]);

                    // Get shop owner's metrics with error handling
                    $shopOwner->total_sales = $this->getSafeSum(
                        $allBills,
                        'total_price'
                    );

                    $shopOwner->sales_this_month = $this->getSafeSum(
                        $monthBills,
                        'total_price'
                    );

                    $shopOwner->sales_today = $this->getSafeSum(
                        $todayBills,
                        'total_price'
                    );

                    // Profit calculations
                    $shopOwner->profit_this_month = $this->calculateProfit($monthBills);
                    $shopOwner->profit_today = $this->calculateProfit($todayBills);

                    // Bill counts
                    $shopOwner->bills_count_this_month = $monthBills->count() ?? 0;
                    $shopOwner->bills_count_today = $todayBills->count() ?? 0;

                    // Products sold
                    $shopOwner->products_sold_this_month = $this->getProductsSoldCount($monthBills);
                    $shopOwner->products_sold_today = $this->getProductsSoldCount($todayBills);

                    // Average bill value
                    $shopOwner->avg_bill_value_this_month = $shopOwner->bills_count_this_month > 0
                        ? $shopOwner->sales_this_month / $shopOwner->bills_count_this_month
                        : 0;

                    // Get counts with error handling
                    $shopOwner->products_count = Product::withoutGlobalScopes()->where('user_id', $shopOwner->id)->count() ?? 0;
                    $shopOwner->customers_count = Customer::withoutGlobalScopes()->where('user_id', $shopOwner->id)->count() ?? 0;

                    // Get last activity
                    $shopOwner->last_activity = Bill::withoutGlobalScopes()->whereIn('user_id', $userIds)
                        ->latest()
                        ->value('created_at');
                } catch (\Exception $e) {
                    // Set default values if any query fails
                    $shopOwner->total_sales = 0;
                    $shopOwner->sales_this_month = 0;
                    $shopOwner->sales_today = 0;
                    $shopOwner->profit_this_month = 0;
                    $shopOwner->profit_today = 0;
                    $shopOwner->bills_count_this_month = 0;
                    $shopOwner->bills_count_today = 0;
                    $shopOwner->products_sold_this_month = 0;
                    $shopOwner->products_sold_today = 0;
                    $shopOwner->avg_bill_value_this_month = 0;
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
                $sales = Bill::withoutGlobalScopes()->whereDate('created_at', $date->toDateString())
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

    /**
     * Generate a fresh database backup and stream it as a download.
     */
    public function downloadBackup(): StreamedResponse
    {
        $backupDir = storage_path('app/backups');

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Run the backup command (saves file to storage/app/backups/ and rotates)
        Artisan::call('db:backup', ['--keep' => 7]);

        // Find the most recently created backup file
        $files = glob($backupDir . '/backup_*');

        if (empty($files)) {
            abort(500, 'Backup generation failed. Check server logs.');
        }

        // Sort descending by modification time, pick the newest
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $latestFile = $files[0];
        $filename   = basename($latestFile);
        $mimeType   = str_ends_with($filename, '.gz') ? 'application/gzip' : 'application/octet-stream';

        Log::info("BackupDatabase: Manual download by admin [{$filename}]");

        return response()->streamDownload(function () use ($latestFile) {
            $handle = fopen($latestFile, 'rb');
            while (!feof($handle)) {
                echo fread($handle, 8192);
                ob_flush();
                flush();
            }
            fclose($handle);
        }, $filename, [
            'Content-Type'        => $mimeType,
            'Content-Length'      => filesize($latestFile),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
