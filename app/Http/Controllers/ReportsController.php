<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\PurchaseBill;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Finance\FinanceInsightsService;
use App\Services\Reports\ProductReportService;
use App\Support\ExportSanitizer;
use App\Support\FeatureCatalog;
use App\Support\ReportCatalog;
use App\Support\ShopTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(
        private readonly FinanceInsightsService $insights,
        private readonly ProductReportService $productReports,
    ) {}

    public function index()
    {
        $ownerId = $this->ownerId();

        return view('reports.index', [
            'customers' => Customer::withoutGlobalScopes()->where('user_id', $ownerId)->orderBy('name')->get(['id', 'name', 'balance']),
            'suppliers' => Supplier::withoutGlobalScopes()->where('user_id', $ownerId)->orderBy('name')->get(['id', 'name', 'balance']),
            'employees' => auth()->user()->canAccessFeature('hr') ? Employee::query()->where('shop_owner_id', $ownerId)->orderBy('name')->get(['id', 'name', 'job_title']) : collect(),
            'employeeUsers' => auth()->user()->canAccessFeature('team_activity') ? User::withoutGlobalScopes()->where('role', 'employee')->where('shop_owner_id', $ownerId)->orderBy('name')->get(['id', 'name']) : collect(),
            'categories' => $this->productReports->categories($ownerId),
            'today' => ShopTime::today($ownerId),
        ]);
    }

    public function generate(Request $request)
    {
        $this->validateDates($request);
        $ownerId = $this->ownerId();
        $type = $request->string('type')->toString();
        $from = $this->sanitizeDate($request->get('from')) ?: ShopTime::today($ownerId);
        $to = $this->sanitizeDate($request->get('to')) ?: $from;

        if ($from > $to) {
            return response()->json(['success' => false, 'message' => __('finance.reports.invalid_date_range')], 422);
        }

        if (! $request->expectsJson()) {
            return $this->print($request);
        }

        if (isset(ReportCatalog::rows()[$type])) {
            $rows = $this->rowReport($type, $ownerId, $request, $from, $to);
            $columns = ReportCatalog::rows()[$type]['columns'];
            $amountColumn = collect(['total_price', 'total_amount', 'amount', 'balance', 'revenue', 'line_total', 'purchase_cost', 'stock_cost', 'damaged_loss', 'returned_value'])->first(fn ($key) => isset($columns[$key]));
            $summary = ['count' => $rows->count(), 'total' => $amountColumn ? round((float) $rows->sum(fn ($row) => data_get($row, $amountColumn, 0)), 2) : 0.0];
            if (isset($columns['profit'])) {
                $summary['profit'] = round((float) $rows->sum('profit'), 2);
            }
            if (in_array($type, ['customer_balances', 'supplier_balances'], true)) {
                $negativeDebt = $type === 'customer_balances';
                $summary['total_owing'] = abs((float) $rows->where('balance', $negativeDebt ? '<' : '>', 0)->sum('balance'));
                $summary['total_credit'] = abs((float) $rows->where('balance', $negativeDebt ? '>' : '<', 0)->sum('balance'));
            }
            $meta = $type === 'customer_statement'
                ? Customer::withoutGlobalScopes()->where('user_id', $ownerId)->find($request->integer('customer_id'), ['name', 'phone', 'balance'])
                : null;

            return response()->json(['success' => true, 'rows' => $rows, 'summary' => $summary, 'meta' => $meta]);
        }

        return match ($type) {
            'profit_loss' => response()->json(['success' => true, 'report' => $this->insights->profitLoss($ownerId, $from, $to)]),
            'receivables_aging' => response()->json(['success' => true, 'report' => $this->insights->receivablesAging($ownerId, $to)]),
            'payables_aging' => response()->json(['success' => true, 'report' => $this->insights->payablesAging($ownerId, $to)]),
            'inventory_valuation' => response()->json(['success' => true, 'report' => $this->insights->inventoryValuation($ownerId, $from, $to)]),
            'balances_summary' => response()->json(['success' => true, 'report' => $this->insights->balancesSummary($ownerId, $to)]),
            default => response()->json(['success' => false, 'message' => __('finance.reports.invalid_report_type')], 422),
        };
    }

    public function print(Request $request)
    {
        $this->validateDates($request);
        $ownerId = $this->ownerId();
        $type = $request->string('type')->toString();
        $from = $this->sanitizeDate($request->get('from')) ?: ShopTime::today($ownerId);
        $to = $this->sanitizeDate($request->get('to')) ?: $from;
        abort_if($from > $to, 422, __('finance.reports.invalid_date_range'));

        if (isset(ReportCatalog::rows()[$type])) {
            $rows = $this->rowReport($type, $ownerId, $request, $from, $to);
            $definition = ReportCatalog::rows()[$type];
            $customer = $type === 'customer_statement'
                ? Customer::withoutGlobalScopes()->where('user_id', $ownerId)->find($request->integer('customer_id'))
                : null;

            return view('reports.rows', compact('type', 'rows', 'definition', 'from', 'to', 'customer'));
        }

        $report = match ($type) {
            'profit_loss' => $this->insights->profitLoss($ownerId, $from, $to),
            'receivables_aging' => $this->insights->receivablesAging($ownerId, $to),
            'payables_aging' => $this->insights->payablesAging($ownerId, $to),
            'inventory_valuation' => $this->insights->inventoryValuation($ownerId, $from, $to),
            'balances_summary' => $this->insights->balancesSummary($ownerId, $to),
            default => abort(404),
        };

        return view('reports.print', compact('type', 'report', 'from', 'to'));
    }

    public function export(Request $request)
    {
        $this->validateDates($request);
        $ownerId = $this->ownerId();
        $type = $request->string('type')->toString();
        $from = $this->sanitizeDate($request->get('from')) ?: ShopTime::today($ownerId);
        $to = $this->sanitizeDate($request->get('to')) ?: $from;
        abort_if($from > $to, 422, __('finance.reports.invalid_date_range'));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        if (isset(ReportCatalog::rows()[$type])) {
            $columns = ReportCatalog::rows()[$type]['columns'];
            $sheet->fromArray([array_map(fn ($label) => ReportCatalog::label($label), array_values($columns))]);
            $index = 2;
            foreach ($this->rowReport($type, $ownerId, $request, $from, $to) as $row) {
                foreach (array_keys($columns) as $columnIndex => $key) {
                    ExportSanitizer::writeString($sheet, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1).$index, ReportCatalog::value($row, $key));
                }
                $index++;
            }
        } else {
            match ($type) {
                'profit_loss' => $this->fillProfitLossSheet($sheet, $this->insights->profitLoss($ownerId, $from, $to)),
                'receivables_aging' => $this->fillAgingSheet($sheet, $this->insights->receivablesAging($ownerId, $to), __('finance.reports.receivables_aging')),
                'payables_aging' => $this->fillAgingSheet($sheet, $this->insights->payablesAging($ownerId, $to), __('finance.reports.payables_aging')),
                'inventory_valuation' => $this->fillInventorySheet($sheet, $this->insights->inventoryValuation($ownerId, $from, $to)),
                'balances_summary' => $this->fillBalancesSheet($sheet, $this->insights->balancesSummary($ownerId, $to)),
                default => abort(404),
            };
        }

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Csv($spreadsheet);
            $writer->setUseBOM(true);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$type.'.csv"',
        ]);
    }

    public function customerBillDetailsPage()
    {
        return view('reports.customer-bill-details');
    }

    public function customerBillDetails(Request $request)
    {
        $this->validateDates($request);
        $ownerId = $this->ownerId();
        $from = $this->sanitizeDate($request->get('from')) ?: now()->subMonth()->toDateString();
        $to = $this->sanitizeDate($request->get('to')) ?: now()->toDateString();
        abort_if($from > $to, 422, __('finance.reports.invalid_date_range'));
        [$startUtc, $endUtc] = ShopTime::utcRange($from, $to, $ownerId);

        $bills = Bill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->with(['customer:id,name,phone', 'creator:id,name', 'products:id,name,barcode'])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get(['id', 'total_price', 'note', 'customer_id', 'created_by', 'created_at', 'is_damaged', 'is_returned']);

        return response()->json([
            'success' => true,
            'bills' => $bills->map(function (Bill $bill) {
                return [
                    'id' => $bill->id,
                    'total_price' => (float) $bill->total_price,
                    'note' => $bill->note,
                    'created_at' => $bill->created_at->format('Y-m-d H:i:s'),
                    'customer_name' => $bill->customer->name ?? __('bills.Walk-in Customer'),
                    'customer_phone' => $bill->customer->phone ?? '',
                    'creator_name' => $bill->creator->name ?? '',
                    'is_damaged' => (bool) $bill->is_damaged,
                    'is_returned' => (bool) $bill->is_returned,
                    'products' => $bill->products->map(fn ($product) => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'barcode' => $product->barcode,
                        'quantity' => (float) ($product->pivot->quantity ?? 0),
                        'selling_price' => (float) ($product->pivot->selling_price ?? 0),
                        'discount' => (float) ($product->pivot->discount ?? 0),
                        'tags' => $product->pivot->tags ?? '',
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    private function customerPaymentsReport(int $ownerId, Request $request, string $from, string $to)
    {
        return CustomerPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->when($request->integer('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
            ->whereBetween('created_at', ShopTime::utcRange($from, $to, $ownerId))
            ->with('customer:id,name,phone')
            ->latest()
            ->limit(1000)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'created_at' => $row->created_at,
                'customer_name' => $row->customer?->name,
                'phone' => $row->customer?->phone,
                'amount' => (float) $row->amount,
                'type' => $row->type,
                'note' => $row->note,
            ]);
    }

    private function customerBillsReport(int $ownerId, Request $request, string $from, string $to)
    {
        return $this->billReport($ownerId, $request, $from, $to, 'customer');
    }

    private function customerStatementReport(int $ownerId, Request $request, string $from, string $to)
    {
        return $this->billReport($ownerId, $request, $from, $to, 'statement');
    }

    private function rowReport(string $type, int $ownerId, Request $request, string $from, string $to)
    {
        $feature = FeatureCatalog::reportFeature($type);
        abort_if($feature && ! auth()->user()->canAccessFeature($feature), 403, __('messages.tier_feature_blocked'));

        if (ReportCatalog::isProductReport($type)) {
            return $this->productReports->rows($type, $ownerId, $request, $from, $to);
        }

        return match ($type) {
            'customer_payments' => $this->customerPaymentsReport($ownerId, $request, $from, $to),
            'customer_bills' => $this->customerBillsReport($ownerId, $request, $from, $to),
            'customer_statement' => $this->customerStatementReport($ownerId, $request, $from, $to),
            'supplier_payments' => $this->supplierPaymentsReport($ownerId, $request, $from, $to),
            'supplier_purchase_bills' => $this->supplierPurchaseBillsReport($ownerId, $request, $from, $to),
            'employee_payments' => $this->employeePaymentsReport($ownerId, $request, $from, $to),
            'employee_work' => $this->employeeWorkReport($ownerId, $request, $from, $to),
            'sale_bills' => $this->saleBillsReport($ownerId, $request, $from, $to),
            'all_purchase_bills' => $this->allPurchaseBillsReport($ownerId, $request, $from, $to),
            'expenses' => $this->expensesReport($ownerId, $request, $from, $to),
            'customer_balances' => $this->customerBalancesReport($ownerId),
            'supplier_balances' => $this->supplierBalancesReport($ownerId),
            default => abort(404),
        };
    }

    private function supplierPaymentsReport(int $ownerId, Request $request, string $from, string $to)
    {
        return SupplierPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id))
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to)
            ->with('supplier:id,name,phone')
            ->latest('payment_date')
            ->limit(1000)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'payment_date' => $row->payment_date,
                'supplier_name' => $row->supplier?->name,
                'phone' => $row->supplier?->phone,
                'amount' => (float) $row->amount,
                'type' => $row->type,
                'note' => $row->note,
            ]);
    }

    private function supplierPurchaseBillsReport(int $ownerId, Request $request, string $from, string $to)
    {
        return PurchaseBill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id))
            ->whereDate('purchase_date', '>=', $from)
            ->whereDate('purchase_date', '<=', $to)
            ->with('supplier:id,name')
            ->latest('purchase_date')
            ->limit(1000)
            ->get();
    }

    private function employeePaymentsReport(int $ownerId, Request $request, string $from, string $to)
    {
        return \App\Models\EmployeePayment::query()
            ->join('employees', 'employees.id', '=', 'employee_payments.employee_id')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to)
            ->where('employees.shop_owner_id', $ownerId)
            ->with('employee:id,name,job_title,shop_owner_id')
            ->select('employee_payments.*')
            ->get();
    }

    private function employeeWorkReport(int $ownerId, Request $request, string $from, string $to)
    {
        return $this->billReport($ownerId, $request, $from, $to, 'employee');
    }

    private function saleBillsReport(int $ownerId, Request $request, string $from, string $to)
    {
        return $this->billReport($ownerId, $request, $from, $to, 'sales');
    }

    private function allPurchaseBillsReport(int $ownerId, Request $request, string $from, string $to)
    {
        return PurchaseBill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id))
            ->whereDate('purchase_date', '>=', $from)
            ->whereDate('purchase_date', '<=', $to)
            ->with(['supplier:id,name', 'creator:id,name'])
            ->latest('purchase_date')
            ->limit(1000)
            ->get();
    }

    private function expensesReport(int $ownerId, Request $request, string $from, string $to)
    {
        return Expense::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->latest('expense_date')
            ->limit(1000)
            ->get();
    }

    private function customerBalancesReport(int $ownerId)
    {
        return Customer::withoutGlobalScopes()->where('user_id', $ownerId)->orderBy('name')->limit(1000)->get();
    }

    private function supplierBalancesReport(int $ownerId)
    {
        return Supplier::withoutGlobalScopes()->where('user_id', $ownerId)->orderBy('name')->limit(1000)->get();
    }

    private function billReport(int $ownerId, Request $request, string $from, string $to, string $mode)
    {
        [$startUtc, $endUtc] = ShopTime::utcRange($from, $to, $ownerId);
        $query = Bill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->with(['customer:id,name,phone', 'creator:id,name']);

        if ($mode === 'customer' || $mode === 'statement') {
            $query->when($request->integer('customer_id'), fn ($q, $id) => $q->where('customer_id', $id));
        }

        if ($mode === 'employee') {
            $employeeUserId = $request->integer('employee_user_id');
            if ($employeeUserId) {
                $query->where('created_by', $employeeUserId);
            } else {
                $query->whereIn('created_by', User::withoutGlobalScopes()->where('role', 'employee')->where('shop_owner_id', $ownerId)->pluck('id'));
            }
        }

        $bills = $query->latest()->limit(1000)->get();
        $profits = DB::table('bill_product')
            ->whereIn('bill_id', $bills->pluck('id'))
            ->selectRaw('bill_id, COALESCE(SUM(((selling_price - cost_price) * quantity) - COALESCE(discount, 0)), 0) as profit')
            ->groupBy('bill_id')
            ->pluck('profit', 'bill_id');

        return $bills->map(function (Bill $bill) use ($profits) {
            return [
                'id' => $bill->id,
                'created_at' => $bill->created_at,
                'customer_name' => $bill->customer?->name,
                'phone' => $bill->customer?->phone,
                'creator_name' => $bill->creator?->name,
                'total_price' => (float) $bill->total_price,
                'profit' => round((float) ($profits[$bill->id] ?? 0), 2),
                'is_damaged' => (bool) $bill->is_damaged,
                'is_returned' => (bool) $bill->is_returned,
                'note' => $bill->note,
            ];
        });
    }

    private function fillProfitLossSheet($sheet, array $report): void
    {
        $sheet->setTitle('Profit Loss');
        $sheet->fromArray(ReportCatalog::profitLossRows($report));
    }

    private function fillAgingSheet($sheet, array $report, string $title): void
    {
        $sheet->setTitle(mb_substr($title, 0, 30));
        $sheet->fromArray([[__('finance.restored.name'), '0-30', '31-60', '61-90', '90+', __('finance.reports.unallocated'), __('finance.common.total')]]);
        $row = 2;
        foreach ($report['rows'] as $entry) {
            $entity = $entry['customer'] ?? $entry['supplier'];
            $sheet->fromArray([[
                ExportSanitizer::csvValue($entity->name),
                $entry['buckets']['0_30'],
                $entry['buckets']['31_60'],
                $entry['buckets']['61_90'],
                $entry['buckets']['90_plus'],
                $entry['buckets']['unallocated'] ?? 0,
                $entry['total'],
            ]], null, 'A'.$row);
            $row++;
        }
    }

    private function fillInventorySheet($sheet, array $report): void
    {
        $sheet->setTitle('Inventory');
        $sheet->fromArray([[__('finance.common.category'), __('finance.restored.quantity'), __('finance.common.amount')]]);
        $row = 2;
        foreach ($report['categories'] as $entry) {
            $sheet->fromArray([[ExportSanitizer::csvValue($entry->category ?: __('finance.common.no_data')), $entry->qty, $entry->value]], null, 'A'.$row);
            $row++;
        }
    }

    private function fillBalancesSheet($sheet, array $report): void
    {
        $sheet->setTitle('Balances');
        $sheet->fromArray([
            [__('finance.common.item'), __('finance.common.amount')],
            [__('finance.dashboard.cash_drawer_balance'), $report['cash_estimate']],
            [__('finance.reports.receivables_aging'), $report['receivables']],
            [__('finance.reports.payables_aging'), $report['payables']],
            [__('finance.reports.inventory_valuation'), $report['inventory_value']],
            [__('messages.Capital'), $report['capital']],
        ]);
    }

    private function sanitizeDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function validateDates(Request $request): void
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);
    }

    private function ownerId(): int
    {
        return (int) auth()->user()->ownerId();
    }
}
