<?php

namespace App\Http\Controllers;

use App\Services\Finance\CashFlowService;
use App\Services\Finance\FinanceInsightsService;
use App\Support\ExportSanitizer;
use App\Support\ShopTime;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialDashboardController extends Controller
{
    public function __construct(
        private readonly FinanceInsightsService $insights,
        private readonly CashFlowService $cashFlow,
    ) {}

    public function index(Request $request)
    {
        $ownerId = $this->ownerId();
        [$startDate, $endDate] = $this->dates($request, $ownerId);
        $cashPeriod = $this->cashPeriod($request, $ownerId);
        $summary = $this->insights->dashboardSummary($ownerId, $startDate, $endDate);
        $profitLoss = $this->insights->profitLoss($ownerId, $startDate, $endDate);
        $details = $this->insights->dashboardDetails($ownerId, $startDate, $endDate);

        return view('dashboard.financial', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'cashPeriod' => $cashPeriod,
            'summary' => $summary,
            'cashDrawer' => $this->cashFlow->cashDrawerData(
                $ownerId,
                $cashPeriod['from_date'],
                $cashPeriod['to_date'],
                $request->string('cash_method', 'cash')->toString() ?: 'cash',
            ),
            'profitLoss' => $profitLoss,
            'balances' => $this->insights->balancesSummary($ownerId, $endDate),
            'details' => $details,
            'charts' => $this->chartData($startDate, $endDate, $summary, $profitLoss, $details),
            'recentClosings' => \App\Models\DayClosing::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->latest('closing_date')
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * Chart series and KPI deltas for the on-screen dashboard (the printed report keeps its tables).
     */
    private function chartData(string $startDate, string $endDate, array $summary, array $profitLoss, array $details): array
    {
        $growth = fn (float $current, float $previous): ?float => abs($previous) < 0.00001 ? null : round(($current - $previous) / abs($previous) * 100, 1);
        $compare = $profitLoss['compare'];
        $purchasesGrowth = collect($details['growth'])->last();

        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        $byMonth = $start->diffInDays($end) > 92;
        $trends = collect($details['trends'])->groupBy(fn ($row) => $byMonth ? substr($row['date'], 0, 7) : $row['date']);
        $buckets = [];
        for ($cursor = $start->copy(); $cursor->lte($end) && count($buckets) < 800; $byMonth ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay()) {
            $buckets[] = $byMonth ? $cursor->format('Y-m') : $cursor->toDateString();
        }
        $buckets = array_values(array_unique($buckets));
        $series = fn (string $metric) => array_map(fn ($key) => round((float) $trends->get($key, collect())->sum($metric), 2), $buckets);
        $labels = array_map(fn ($key) => $byMonth ? $key : \Carbon\Carbon::parse($key)->format('m/d'), $buckets);

        $expenseRows = collect($details['tables']['expenses_breakdown']['rows'])
            ->map(fn ($row) => ['name' => (string) (data_get($row, 'name') ?: __('charts.finance.uncategorized')), 'amount' => (float) data_get($row, 'amount')])
            ->filter(fn ($row) => $row['amount'] > 0)->sortByDesc('amount')->values();
        $costs = $expenseRows->take(6)->pluck('amount', 'name');
        if ($expenseRows->count() > 6) {
            $costs[__('charts.others')] = $expenseRows->slice(6)->sum('amount');
        }
        if ($summary['staff_payments'] > 0) {
            $costs[__('finance.dashboard.staff_payments')] = $summary['staff_payments'];
        }
        if ($summary['damaged_loss'] > 0) {
            $costs[__('finance.dashboard.damaged_loss')] = $summary['damaged_loss'];
        }

        $topProducts = collect($details['tables']['top_products']['rows'])->take(8);
        $team = collect($details['team']['rows'] ?? [])->filter(fn ($row) => (float) ($row['sales_total'] ?? 0) > 0)->values();
        $returnRate = $profitLoss['revenue'] > 0 ? round($profitLoss['returns'] / $profitLoss['revenue'] * 100, 1) : null;

        return [
            'by_month' => $byMonth,
            'deltas' => [
                'revenue' => $growth($profitLoss['revenue'], $compare['revenue']),
                'profit' => $growth($profitLoss['gross_profit'], $compare['gross_profit']),
                'net' => $growth($profitLoss['net_profit'], $compare['net_profit']),
                'expenses' => $growth($profitLoss['expenses_total'], $compare['expenses_total']),
                'staff' => $growth($profitLoss['staff_payments'], $compare['staff_payments']),
                'discounts' => $growth($profitLoss['discounts'], $compare['discounts']),
                'returns' => $growth($profitLoss['returns'], $compare['returns']),
                'damaged' => $growth($profitLoss['damaged_loss'], $compare['damaged_loss']),
                'purchases' => $purchasesGrowth['growth'] ?? null,
                'average_bill' => $growth($profitLoss['average_bill'], $compare['average_bill']),
            ],
            'return_rate' => $returnRate,
            'compare_period' => $compare['from_date'].' → '.$compare['to_date'],
            'trend' => [
                'labels' => $labels,
                'revenue' => $series('revenue'),
                'profit' => $series('profit'),
                'returns' => $series('returns'),
                'money_in' => $series('money_in'),
                'money_out' => $series('money_out'),
                'purchases' => $series('purchases'),
            ],
            'costs' => ['labels' => $costs->keys()->all(), 'data' => $costs->values()->all()],
            'top_products' => [
                'labels' => $topProducts->map(fn ($row) => (string) data_get($row, 'name'))->all(),
                'profit' => $topProducts->map(fn ($row) => (float) data_get($row, 'profit'))->all(),
                'revenue' => $topProducts->map(fn ($row) => (float) data_get($row, 'revenue'))->all(),
            ],
            'team' => [
                'labels' => $team->map(fn ($row) => (string) ($row['user']->name ?? '—'))->all(),
                'data' => $team->map(fn ($row) => (float) $row['sales_total'])->all(),
            ],
            'waterfall' => [
                'labels' => [
                    __('charts.finance.sales_value'), __('charts.finance.cogs'), __('finance.day_close.discounts'),
                    __('charts.gross_profit'), __('finance.dashboard.expenses'), __('finance.dashboard.staff_payments'),
                    __('finance.dashboard.damaged_loss'), __('charts.net_profit'),
                ],
                'data' => [
                    $profitLoss['signed_revenue'], $profitLoss['cogs'], $profitLoss['discounts'], $profitLoss['gross_profit'],
                    $profitLoss['expenses_total'], $profitLoss['staff_payments'], $profitLoss['damaged_loss'], $profitLoss['net_profit'],
                ],
            ],
        ];
    }

    public function printComprehensiveReport(Request $request)
    {
        $ownerId = $this->ownerId();
        [$startDate, $endDate] = $this->dates($request, $ownerId);
        $cashPeriod = $this->cashPeriod($request, $ownerId);

        return view('dashboard.comprehensive-report', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'summary' => $this->insights->dashboardSummary($ownerId, $startDate, $endDate),
            'profitLoss' => $this->insights->profitLoss($ownerId, $startDate, $endDate),
            'balances' => $this->insights->balancesSummary($ownerId, $endDate),
            'cashPeriod' => $cashPeriod,
            'cashDrawer' => $this->cashFlow->cashDrawerData($ownerId, $cashPeriod['from_date'], $cashPeriod['to_date'], $request->string('cash_method', 'cash')->toString() ?: 'cash'),
            'generatedAt' => now(),
            'generatedBy' => auth()->user(),
            'details' => $this->insights->dashboardDetails($ownerId, $startDate, $endDate),
        ]);
    }

    public function exportData(Request $request)
    {
        $ownerId = $this->ownerId();
        [$startDate, $endDate] = $this->dates($request, $ownerId);
        $summary = $this->insights->dashboardSummary($ownerId, $startDate, $endDate);
        $profitLoss = $this->insights->profitLoss($ownerId, $startDate, $endDate);
        $cashPeriod = $this->cashPeriod($request, $ownerId);
        $cashDrawer = $this->cashFlow->cashDrawerData($ownerId, $cashPeriod['from_date'], $cashPeriod['to_date'], $request->string('cash_method', 'cash')->toString() ?: 'cash');
        $details = $this->insights->dashboardDetails($ownerId, $startDate, $endDate);

        $spreadsheet = new Spreadsheet;
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Summary');
        $summarySheet->fromArray([[__('finance.common.item'), __('finance.common.amount')]]);
        foreach ([
            __('finance.dashboard.revenue') => $summary['revenue'],
            __('finance.dashboard.profit') => $summary['profit'],
            __('finance.day_close.discounts') => $summary['discounts'],
            __('finance.dashboard.expenses') => $summary['expenses'],
            __('finance.dashboard.staff_payments') => $summary['staff_payments'],
            __('finance.dashboard.damaged_loss') => $summary['damaged_loss'],
            __('finance.dashboard.net_income') => $summary['net_income'],
            __('finance.dashboard.settlement_in') => $summary['cash_flow']['settlement']['cash_in'],
            __('finance.dashboard.settlement_out') => $summary['cash_flow']['settlement']['cash_out'],
            __('finance.dashboard.cash_drawer_balance') => $summary['cash_flow']['cash_drawer']['closing_balance'],
        ] as $label => $value) {
            $summarySheet->fromArray([[ExportSanitizer::csvValue($label), $value]], null, 'A'.($summarySheet->getHighestRow() + 1));
        }

        $pnlSheet = $spreadsheet->createSheet();
        $pnlSheet->setTitle('Profit Loss');
        $pnlSheet->fromArray(\App\Support\ReportCatalog::profitLossRows($profitLoss));

        $cashSheet = $spreadsheet->createSheet();
        $cashSheet->setTitle('Cash Drawer');
        $cashSheet->fromArray([[__('finance.common.date'), __('finance.cash_drawer.category'), __('finance.cash_drawer.method'), __('finance.cash_drawer.document'), __('finance.cash_drawer.amount_in'), __('finance.cash_drawer.amount_out')]]);
        $rowIndex = 2;
        foreach ($cashDrawer['rows'] as $row) {
            ExportSanitizer::writeString($cashSheet, 'A'.$rowIndex, ShopTime::local($row['occurred_at'], $ownerId)->format('Y-m-d H:i'));
            ExportSanitizer::writeString($cashSheet, 'B'.$rowIndex, __('finance.cash_drawer.categories.'.$row['category']));
            ExportSanitizer::writeString($cashSheet, 'C'.$rowIndex, __('finance.methods.'.$row['method']));
            ExportSanitizer::writeString($cashSheet, 'D'.$rowIndex, $row['document_label']);
            $cashSheet->setCellValue('E'.$rowIndex, $row['amount_in']);
            $cashSheet->setCellValue('F'.$rowIndex, $row['amount_out']);
            $rowIndex++;
        }

        $exportTables = $details['tables'];
        if (! auth()->user()->canAccessFeature('hr')) {
            unset($exportTables['staff_breakdown']);
        }
        $exportTables['inventory'] = ['columns' => ['cost', 'selling', 'units', 'products'], 'rows' => collect([$details['inventory']])];
        $exportTables['capital'] = ['columns' => ['entry_date', 'amount', 'note'], 'rows' => $details['capital']];
        $exportTables['account_totals'] = ['columns' => ['name', 'amount'], 'rows' => collect($details['balances'])
            ->map(fn ($group, $key) => ['name' => __('finance.restored.'.$key), 'amount' => $group['total']])->values()];
        foreach ($details['balances'] as $key => $group) {
            $exportTables[$key] = ['columns' => ['name', 'phone', 'balance'], 'rows' => $group['rows']];
        }
        if (auth()->user()->canAccessFeature('team_activity')) {
            $exportTables['team'] = ['columns' => ['user.name', 'bills_count', 'sales_total', 'returns_total', 'discounts', 'collections'], 'rows' => $details['team']['rows']];
        }
        foreach ($exportTables as $key => $table) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle(substr($key, 0, 31));
            $sheet->fromArray([array_map(fn ($column) => __([
                'entry_date' => 'finance.common.date', 'note' => 'finance.common.notes', 'phone' => 'messages.Phone',
                'balance' => 'messages.Balance', 'user.name' => 'finance.common.user', 'bills_count' => 'finance.team_summary.bills_count',
                'sales_total' => 'finance.team_summary.sales_total', 'returns_total' => 'finance.team_summary.returns',
                'discounts' => 'finance.team_summary.discounts', 'collections' => 'finance.team_summary.collections',
            ][$column] ?? 'finance.restored.'.$column), $table['columns'])]);
            $rowIndex = 2;
            foreach ($table['rows'] as $row) {
                foreach ($table['columns'] as $columnIndex => $column) {
                    ExportSanitizer::writeString($sheet, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1).$rowIndex, (string) data_get($row, $column, ''));
                }
                $rowIndex++;
            }
        }

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="financial-dashboard.xlsx"',
        ]);
    }

    private function ownerId(): int
    {
        return (int) auth()->user()->ownerId();
    }

    private function dates(Request $request, int $ownerId): array
    {
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'cash_from' => 'nullable|date_format:Y-m-d',
            'cash_to' => 'nullable|date_format:Y-m-d',
            'cash_method' => 'nullable|in:cash,all,card,transfer,check',
            'cash_preset' => 'nullable|in:today,yesterday,this_week,this_month,custom',
        ]);
        $today = ShopTime::today($ownerId);
        $from = $request->input('start_date') ?: \Carbon\Carbon::parse($today)->subDays(29)->toDateString();
        $to = $request->input('end_date') ?: $today;
        abort_if($from > $to, 422, __('finance.reports.invalid_date_range'));
        if ($request->input('cash_preset') === 'custom' && $request->filled(['cash_from', 'cash_to'])) {
            abort_if($request->input('cash_from') > $request->input('cash_to'), 422, __('finance.reports.invalid_date_range'));
        }

        return [$from, $to];
    }

    private function cashPeriod(Request $request, int $ownerId): array
    {
        return $this->cashFlow->resolvePeriod(
            $ownerId,
            $request->input('cash_preset') ?: 'today',
            $request->input('cash_from') ?: $request->input('start_date'),
            $request->input('cash_to') ?: $request->input('end_date'),
        );
    }
}
