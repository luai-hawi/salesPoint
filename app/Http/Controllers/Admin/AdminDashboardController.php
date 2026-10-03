<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Bill;
use App\Models\Product;
use App\Models\User;
use App\Services\Admin\AccountStatus;
use App\Services\Admin\CurrencyFormatter;
use App\Services\Admin\PlatformSettings;
use App\Services\Admin\ShopPerformanceService;
use App\Services\Admin\ShopStorageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AccountStatus $statusService,
        private readonly CurrencyFormatter $currencies,
        private readonly PlatformSettings $settings,
        private readonly ShopStorageService $storage,
        private readonly ShopPerformanceService $performance,
    ) {
    }

    public function index()
    {
        $fresh = request()->boolean('refresh');
        $cacheKey = 'admin.dashboard.v3';

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, 90, fn () => $this->buildDashboardData());

        return view('admin.dashboard', $data + [
            'currencies' => $this->currencies,
            'statusService' => $this->statusService,
        ]);
    }

    public function downloadBackup(): StreamedResponse
    {
        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        Artisan::call('db:backup', ['--keep' => 7]);

        $files = glob($backupDir . '/backup_*');
        if (empty($files)) {
            abort(500, __('admin.messages.backup_failed'));
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $latestFile = $files[0];
        $filename = basename($latestFile);
        $mimeType = str_ends_with($filename, '.gz') ? 'application/gzip' : 'application/octet-stream';

        Log::info("BackupDatabase: Manual download by admin [{$filename}]");

        return response()->streamDownload(function () use ($latestFile) {
            $handle = fopen($latestFile, 'rb');
            while (! feof($handle)) {
                echo fread($handle, 8192);
                ob_flush();
                flush();
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => $mimeType,
            'Content-Length' => filesize($latestFile),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function buildDashboardData(): array
    {
        $ownerIds = User::query()->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']))->pluck('id')->all();

        $statusCounts = [
            'all' => count($ownerIds),
            'active' => $this->statusService->applyFilter($this->statusService->baseQuery(), 'active')->count(),
            'has_to_pay' => $this->statusService->applyFilter($this->statusService->baseQuery(), 'has_to_pay')->count(),
            'trial' => $this->statusService->applyFilter($this->statusService->baseQuery(), 'trial')->count(),
            'trial_ended' => $this->statusService->applyFilter($this->statusService->baseQuery(), 'trial_ended')->count(),
            'disabled' => $this->statusService->applyFilter($this->statusService->baseQuery(), 'disabled')->count(),
            'needs_attention' => $this->statusService->applyFilter($this->statusService->baseQuery(), 'needs_attention')->count(),
        ];

        $summaries = $this->performance->monthSummaries();
        $salesToday = (float) $summaries->sum('sales_today');
        $salesMonth = (float) $summaries->sum('sales_month');
        $billsToday = (int) $summaries->sum('bills_today');
        $billsMonth = (int) $summaries->sum('bills_month');
        $profitToday = (float) $summaries->sum('profit_today');
        $profitMonth = (float) $summaries->sum('profit_month');

        $previous = $this->platformTotals(
            now()->subMonthNoOverflow()->startOfMonth(),
            now()->subMonthNoOverflow()->startOfMonth()->addDays(now()->day - 1)->endOfDay()->min(now()->subMonthNoOverflow()->endOfMonth())
        );
        $series = $this->performance->dailySeries(30);
        $salesChartData = collect($series['labels'])->map(fn ($label, $i) => [
            'date' => $label,
            'sales' => $series['sales'][$i],
            'profit' => $series['profit'][$i],
        ])->all();

        $owners = User::query()->whereIn('id', $ownerIds)->get()->keyBy('id');
        $shopPerformance = $owners->map(function (User $shop) use ($summaries) {
            return ['shop' => $shop] + ($summaries->get((int) $shop->id) ?? ShopPerformanceService::emptySummary());
        })->sortByDesc(fn ($row) => [$row['profit_month'], $row['sales_month']])->values();

        $topShops = $shopPerformance->filter(fn ($row) => $row['sales_month'] > 0)
            ->sortByDesc('sales_month')
            ->take(5)
            ->map(fn ($row) => ['shop' => $row['shop'], 'sales' => $row['sales_month'], 'profit' => $row['profit_month'], 'bills' => $row['bills_month']])
            ->values();
        $shareRows = $shopPerformance->filter(fn ($row) => $row['sales_month'] > 0)->sortByDesc('sales_month')->values();
        $salesShare = [
            'labels' => $shareRows->take(6)->map(fn ($row) => $row['shop']->name)
                ->when($shareRows->count() > 6, fn ($labels) => $labels->push(__('charts.others')))->values()->all(),
            'data' => $shareRows->take(6)->pluck('sales_month')
                ->when($shareRows->count() > 6, fn ($data) => $data->push(round($shareRows->slice(6)->sum('sales_month'), 2)))->values()->all(),
        ];

        $recentSignups = User::query()
            ->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']))
            ->latest()
            ->limit(6)
            ->get();

        $collectionGroups = $this->collectionGroups();

        $usageAlerts = User::query()
            ->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']))
            ->whereNotNull('entry_limit')
            ->where('entry_limit', '>', 0)
            ->select('users.*')
            ->selectSub(DB::table('products')->selectRaw('COUNT(*)')->whereColumn('user_id', 'users.id'), 'products_count')
            ->selectSub(DB::table('customers')->selectRaw('COUNT(*)')->whereColumn('user_id', 'users.id'), 'customers_count')
            ->selectSub(DB::table('bills')->selectRaw('COUNT(*)')->whereColumn('user_id', 'users.id'), 'bills_count')
            ->selectSub(DB::table('purchase_bills')->selectRaw('COUNT(*)')->whereColumn('user_id', 'users.id'), 'purchase_bills_count')
            ->get()
            ->map(function (User $shop) {
                $used = (int) ($shop->products_count + $shop->customers_count + $shop->bills_count + $shop->purchase_bills_count);
                $limit = (int) $shop->entry_limit;
                $percent = $limit > 0 ? round(($used / $limit) * 100) : 0;

                return [
                    'shop' => $shop,
                    'used' => $used,
                    'limit' => $limit,
                    'percent' => (int) $percent,
                ];
            })
            ->filter(fn (array $row) => $row['percent'] >= 90)
            ->sortByDesc('percent')
            ->take(8)
            ->values();

        $imageAlerts = collect();
        if ($ownerIds) {
            $stats = $this->storage->imageStatsForShops($ownerIds);
            $shops = User::query()->whereIn('id', array_keys($stats))->get()->keyBy('id');
            $imageAlerts = collect($stats)
                ->map(fn ($stat, $ownerId) => ['shop' => $shops[(int) $ownerId] ?? null, 'stats' => $stat])
                ->filter(fn ($row) => $row['shop'] && $row['stats']['bytes'] >= (50 * 1024 * 1024))
                ->sortByDesc(fn ($row) => $row['stats']['bytes'])
                ->take(8)
                ->values();
        }

        $systemHealth = [
            'free_disk' => $this->humanBytes(@disk_free_space(base_path()) ?: 0),
            'backup_age' => $this->backupAgeLabel(),
            'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
        ];

        return [
            'statusCounts' => $statusCounts,
            'kpis' => [
                'sales_today' => $salesToday,
                'sales_month' => $salesMonth,
                'profit_today' => $profitToday,
                'profit_month' => $profitMonth,
                'bills_today' => $billsToday,
                'bills_month' => $billsMonth,
                'margin_month' => $salesMonth > 0 ? round($profitMonth / $salesMonth * 100, 1) : null,
                'avg_bill_month' => $billsMonth > 0 ? round($salesMonth / $billsMonth, 2) : 0.0,
                'returns_month' => (float) $summaries->sum('returns_month'),
                'selling_shops' => $summaries->filter(fn ($row) => $row['bills_month'] > 0)->count(),
                'sales_growth' => $this->performance->growth($salesMonth, $previous['sales']),
                'profit_growth' => $this->performance->growth($profitMonth, $previous['profit']),
            ],
            'collectionGroups' => $collectionGroups,
            'salesChartData' => $salesChartData,
            'salesShare' => $salesShare,
            'shopPerformance' => $shopPerformance,
            'topShops' => $topShops,
            'recentSignups' => $recentSignups,
            'systemHealth' => $systemHealth,
            'usageAlerts' => $usageAlerts,
            'imageAlerts' => $imageAlerts,
        ];
    }

    private function collectionGroups(): array
    {
        $shops = User::query()
            ->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']))
            ->get();

        $groups = [];
        foreach ($shops as $shop) {
            $status = $this->statusService->describe($shop);
            $currency = $status['currency'];
            $amount = $status['amount'] ?? 0;

            if (! isset($groups[$currency])) {
                $groups[$currency] = ['overdue' => 0, 'next_30_days' => 0];
            }

            if ($status['key'] === 'payment_overdue') {
                $groups[$currency]['overdue'] += $amount;
            }

            if ($status['next_payment_date'] && Carbon::parse($status['next_payment_date'])->between(now()->startOfDay(), now()->addDays(30)->endOfDay())) {
                $groups[$currency]['next_30_days'] += $amount;
            }
        }

        return $groups;
    }

    /**
     * @return array{sales: float, profit: float}
     */
    private function platformTotals(Carbon $start, Carbon $end): array
    {
        $sales = (float) DB::table('bills')
            ->whereBetween('created_at', [$start, $end])
            ->where('is_damaged', false)
            ->where('is_returned', false)
            ->sum('total_price');
        $profit = (float) DB::table('bill_product')
            ->join('bills', 'bills.id', '=', 'bill_product.bill_id')
            ->whereBetween('bills.created_at', [$start, $end])
            ->where('bills.is_damaged', false)
            ->selectRaw('COALESCE(SUM(((bill_product.selling_price - bill_product.cost_price) * bill_product.quantity) - COALESCE(bill_product.discount, 0)), 0) as profit')
            ->value('profit');

        return ['sales' => $sales, 'profit' => $profit];
    }

    private function backupAgeLabel(): string
    {
        $files = glob(storage_path('app/backups/backup_*')) ?: [];
        if (! $files) {
            return __('admin.dashboard.no_backup');
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $age = Carbon::createFromTimestamp(filemtime($files[0]))->diffForHumans();

        return $age;
    }

    private function humanBytes(int|float $bytes): string
    {
        return ShopStorageService::humanBytes($bytes);
    }
}
