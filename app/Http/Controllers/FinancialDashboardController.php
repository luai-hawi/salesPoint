<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Batch;
use App\Models\Tag;
use App\Models\User;
// Add these imports for supplier functionality
use App\Models\Supplier;
use App\Models\PurchaseBill;
use App\Models\SupplierPayment;
use App\Models\ProductBarcode;
use App\Models\CapitalEntry;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialDashboardController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));

        $user = auth()->user();
        $userId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        // For employees, get the shop owner ID
        $shopOwnerId = auth()->user()->role === 'employee' ? auth()->user()->shop_owner_id : $userId;

        // Summary Data - Updated to include purchases and supplier payments
        $summaryData = $this->getSummaryData($startDate, $endDate, $shopOwnerId);

        // Store Value Data - NEW
        $storeValueData = $this->getStoreValueData($shopOwnerId);

        // Revenue Data
        $revenueData = $this->getRevenueData($startDate, $endDate, $userId);

        // Profit Data
        $profitData = $this->getProfitData($startDate, $endDate, $userId);

        // Expense Data
        $expenseData = $this->getExpenseData($startDate, $endDate, $userId);

        // Customer Payment Data
        $customerPaymentData = $this->getCustomerPaymentData($startDate, $endDate, $userId);

        // Employee Payment Data
        $employeePaymentData = $this->getEmployeePaymentData($startDate, $endDate, $shopOwnerId);

        // Damaged Products Data
        $damagedData = $this->getDamagedData($startDate, $endDate, $userId);

        // NEW: Returned Bills Data
        $returnedData = $this->getReturnedData($startDate, $endDate, $userId);

        // Customer Balance Data
        $customerBalanceData = $this->getCustomerBalanceData($userId);

        // NEW: Purchase Data
        $purchaseData = $this->getPurchaseData($startDate, $endDate, $shopOwnerId);

        // NEW: Supplier Payment Data
        $supplierPaymentData = $this->getSupplierPaymentData($startDate, $endDate, $shopOwnerId);

        // NEW: Supplier Balance Data
        $supplierBalanceData = $this->getSupplierBalanceData($shopOwnerId);

        // Top Products
        $topProducts = $this->getTopProducts($startDate, $endDate, $userId);

        // NEW: Top Suppliers
        $topSuppliers = $this->getTopSuppliers($startDate, $endDate, $shopOwnerId);

        // Growth Data - Updated to include purchases
        $growthData = $this->getGrowthData($startDate, $endDate, $shopOwnerId);

        // Capital Data - NEW
        $capitalData = $this->getCapitalData($shopOwnerId);

        // Daily Cash Flow Data - NEW
        $cashFlowStartDate = $request->get('cash_flow_start_date', Carbon::now()->format('Y-m-d'));
        $cashFlowEndDate = $request->get('cash_flow_end_date', Carbon::now()->format('Y-m-d'));
        $dailyCashFlowData = $this->getDailyCashFlowData($shopOwnerId, $cashFlowStartDate, $cashFlowEndDate);

        // Sales by User - NEW
        $salesByUserData = $this->getSalesByUser($shopOwnerId);

        return view('dashboard.financial', compact(
            'summaryData',
            'storeValueData',
            'revenueData',
            'profitData',
            'expenseData',
            'customerPaymentData',
            'employeePaymentData',
            'damagedData',
            'returnedData',
            'customerBalanceData',
            'purchaseData',
            'supplierPaymentData',
            'supplierBalanceData',
            'topProducts',
            'topSuppliers',
            'growthData',
            'capitalData',
            'dailyCashFlowData',
            'cashFlowStartDate',
            'cashFlowEndDate',
            'salesByUserData',
            'startDate',
            'endDate'
        ));
    }

    private function getSummaryData($startDate, $endDate, $userId)
    {
        // Calculate total revenue
        $totalRevenue = Bill::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('is_damaged', false)
            ->sum('total_price');

        // Calculate total profit via SQL join — no PHP loops
        $totalProfit = DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $userId)
            ->whereBetween('bills.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('bills.is_damaged', false)
            ->selectRaw('SUM((bill_product.selling_price - bill_product.cost_price) * bill_product.quantity - bill_product.discount) as profit')
            ->value('profit') ?? 0;

        // Calculate total expenses
        $totalExpenses = Expense::where('user_id', $userId)
            ->whereBetween('expense_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('amount');

        // Calculate total employee payments
        $totalEmployeePayments = EmployeePayment::whereHas('employee', function ($q) use ($userId) {
            $q->where('shop_owner_id', $userId);
        })
            ->whereBetween('payment_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('amount');

        // NEW: Calculate total purchases
        $totalPurchases = PurchaseBill::where('created_by', $userId)
            ->whereBetween('purchase_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('total_amount');

        // NEW: Calculate total supplier payments
        $totalSupplierPayments = SupplierPayment::whereHas('supplier', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->whereBetween('payment_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('amount', '>', 0) // Only outgoing payments
            ->sum('amount');

        // Calculate losses from damaged bills via SQL join
        $damagedBillsLoss = DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $userId)
            ->whereBetween('bills.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('bills.is_damaged', true)
            ->selectRaw('SUM(bill_product.cost_price * bill_product.quantity) as loss')
            ->value('loss') ?? 0;

        // Calculate net income including damaged bills losses
        $netIncome = $totalProfit - $damagedBillsLoss - $totalExpenses - $totalEmployeePayments;

        return [
            'totalRevenue' => $totalRevenue,
            'totalProfit' => $totalProfit,
            'totalExpenses' => $totalExpenses,
            'totalEmployeePayments' => $totalEmployeePayments,
            'totalPurchases' => $totalPurchases,
            'totalSupplierPayments' => $totalSupplierPayments,
            'damagedBillsLoss' => $damagedBillsLoss,
            'netIncome' => $netIncome
        ];
    }

    // NEW: Store Value Data
    private function getStoreValueData($userId)
    {
        $storeData = DB::table('products')
            ->where('user_id', $userId)
            ->where('quantity', '>', 0)
            ->selectRaw(
                'SUM(quantity * cost_price) as total_cost_value,' .
                    'SUM(quantity * selling_price) as total_selling_value,' .
                    'SUM(quantity) as total_items,' .
                    'COUNT(*) as total_products'
            )
            ->first();

        $totalCostValue    = $storeData->total_cost_value    ?? 0;
        $totalSellingValue = $storeData->total_selling_value ?? 0;
        $totalItems        = $storeData->total_items         ?? 0;
        $totalProducts     = $storeData->total_products      ?? 0;
        $potentialProfit   = $totalSellingValue - $totalCostValue;

        return [
            'totalCostValue'    => $totalCostValue,
            'totalSellingValue' => $totalSellingValue,
            'potentialProfit'   => $potentialProfit,
            'totalItems'        => $totalItems,
            'totalProducts'     => $totalProducts
        ];
    }

    // NEW: Purchase Data
    private function getPurchaseData($startDate, $endDate, $userId)
    {
        $purchases = PurchaseBill::where('created_by', $userId)
            ->whereBetween('purchase_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('DATE(purchase_date) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $data = [];
        $total = 0;

        foreach ($purchases as $purchase) {
            $labels[] = Carbon::parse($purchase->date)->format('M d');
            $data[] = $purchase->total;
            $total += $purchase->total;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'total' => $total
        ];
    }

    // NEW: Supplier Payment Data
    private function getSupplierPaymentData($startDate, $endDate, $userId)
    {
        $payments = SupplierPayment::whereHas('supplier', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->whereBetween('payment_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('amount', '>', 0) // Only outgoing payments
            ->selectRaw('DATE(payment_date) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $data = [];
        $total = 0;

        foreach ($payments as $payment) {
            $labels[] = Carbon::parse($payment->date)->format('M d');
            $data[] = $payment->total;
            $total += $payment->total;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'total' => $total
        ];
    }

    // NEW: Supplier Balance Data
    private function getSupplierBalanceData($userId)
    {
        $summary = DB::table('suppliers')
            ->where('user_id', $userId)
            ->selectRaw(
                'SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END) as total_owing,' .
                    'SUM(CASE WHEN balance < 0 THEN ABS(balance) ELSE 0 END) as total_owed'
            )
            ->first();

        $topOwing = DB::table('suppliers')
            ->where('user_id', $userId)
            ->where('balance', '>', 0)
            ->orderBy('balance', 'desc')
            ->limit(10)
            ->get(['name', 'balance']);

        $topOwed = DB::table('suppliers')
            ->where('user_id', $userId)
            ->where('balance', '<', 0)
            ->orderBy('balance', 'asc')
            ->limit(10)
            ->get(['name', 'balance']);

        return [
            'totalOwing' => $summary->total_owing ?? 0,
            'totalOwed'  => $summary->total_owed  ?? 0,
            'topOwing' => [
                'labels' => $topOwing->pluck('name')->toArray(),
                'data'   => $topOwing->pluck('balance')->toArray()
            ],
            'topOwed' => [
                'labels' => $topOwed->pluck('name')->toArray(),
                'data'   => $topOwed->map(fn($s) => abs($s->balance))->toArray()
            ]
        ];
    }

    // NEW: Top Suppliers
    private function getTopSuppliers($startDate, $endDate, $userId)
    {
        return DB::table('purchase_bills')
            ->join('suppliers', 'purchase_bills.supplier_id', '=', 'suppliers.id')
            ->where('purchase_bills.created_by', $userId)
            ->whereBetween('purchase_bills.purchase_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                'suppliers.name',
                DB::raw('COUNT(purchase_bills.id) as total_bills'),
                DB::raw('SUM(purchase_bills.total_amount) as total_purchases')
            )
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_purchases')
            ->limit(10)
            ->get();
    }

    // NEW: Capital Data
    private function getCapitalData($userId)
    {
        $capitalEntries = CapitalEntry::where('user_id', $userId)
            ->orderBy('entry_date', 'desc')
            ->get();

        $totalCapital = $capitalEntries->sum('amount');

        return [
            'entries' => $capitalEntries,
            'total' => $totalCapital
        ];
    }

    // NEW: Daily Cash Flow Data - Get cash in and out for a date range
    private function getDailyCashFlowData($userId, $startDate, $endDate)
    {
        // Cash In:
        // 1. Total sales from bills (completed sales)
        $salesCashIn = Bill::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('is_damaged', false)
            ->sum('total_price');

        // 2. Customer payments received (positive amounts)
        $customerPaymentsIn = CustomerPayment::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('amount', '>', 0)
            ->sum('amount');

        // 3. Capital entries (money coming in from outside)
        $capitalIn = CapitalEntry::where('user_id', $userId)
            ->whereBetween('entry_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('amount');

        // Total Cash In
        $totalCashIn = $salesCashIn + $customerPaymentsIn + $capitalIn;

        // Cash Out:
        // 1. Supplier payments (actual payments to suppliers)
        $supplierPaymentsOut = SupplierPayment::whereHas('supplier', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->whereBetween('payment_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('amount', '>', 0)
            ->sum('amount');

        // 2. Employee payments (salaries, etc.)
        $employeePaymentsOut = EmployeePayment::whereHas('employee', function ($q) use ($userId) {
            $q->where('shop_owner_id', $userId);
        })
            ->whereBetween('payment_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('amount');

        // 3. Expenses (operational expenses)
        $expensesOut = Expense::where('user_id', $userId)
            ->whereBetween('expense_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('amount');

        // 4. Minus payments (new customer debt - negative amounts)
        // This represents sales on credit (customer owes us money)
        $minusPaymentsOut = abs(CustomerPayment::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('amount', '<', 0)
            ->sum('amount'));

        // Note: Purchase bills are NOT included because they represent debt to suppliers,
        // not actual cash out. Only actual supplier payments are counted.

        // Total Cash Out
        $totalCashOut = $supplierPaymentsOut + $employeePaymentsOut + $expensesOut + $minusPaymentsOut;

        // Net Cash Flow
        $netCashFlow = $totalCashIn - $totalCashOut;

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'cashIn' => [
                'sales' => $salesCashIn,
                'customerPayments' => $customerPaymentsIn,
                'capital' => $capitalIn,
                'total' => $totalCashIn
            ],
            'cashOut' => [
                'supplierPayments' => $supplierPaymentsOut,
                'employeePayments' => $employeePaymentsOut,
                'expenses' => $expensesOut,
                'minusPayments' => $minusPaymentsOut,
                'total' => $totalCashOut
            ],
            'netCashFlow' => $netCashFlow
        ];
    }

    // NEW: Sales by User - Get sales for each user (shop owner + employees) for today
    private function getSalesByUser($shopOwnerId)
    {
        $today = Carbon::today();

        // Single query grouped by creator — replaces the N+1 loop
        $salesIndex = DB::table('bills')
            ->where('user_id', $shopOwnerId)
            ->whereDate('created_at', $today)
            ->where('is_damaged', false)
            ->selectRaw('created_by, SUM(total_price) as sales, COUNT(*) as bill_count')
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');

        // Load all users (owner + employees) with a single query
        $users = User::where(function ($q) use ($shopOwnerId) {
            $q->where('id', $shopOwnerId)
                ->orWhere(function ($q2) use ($shopOwnerId) {
                    $q2->where('shop_owner_id', $shopOwnerId)->where('role', 'employee');
                });
        })->get(['id', 'name', 'role']);

        $salesByUser = [];
        $totalSales  = 0;

        foreach ($users as $user) {
            $row   = $salesIndex->get($user->id);
            $sales = $row ? (float) $row->sales : 0;

            $salesByUser[] = [
                'id'         => $user->id,
                'name'       => $user->name,
                'role'       => $user->role,
                'sales'      => $sales,
                'bill_count' => $row ? (int) $row->bill_count : 0,
            ];

            $totalSales += $sales;
        }

        return [
            'users' => $salesByUser,
            'total' => $totalSales,
        ];
    }

    private function getRevenueData($startDate, $endDate, $userId)
    {
        $bills = Bill::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('is_damaged', false)
            ->selectRaw('DATE(created_at) as date, SUM(total_price) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $data = [];
        $total = 0;

        foreach ($bills as $bill) {
            $labels[] = Carbon::parse($bill->date)->format('M d');
            $data[] = $bill->total;
            $total += $bill->total;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'total' => $total
        ];
    }

    private function getProfitData($startDate, $endDate, $userId)
    {
        $rows = DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $userId)
            ->whereBetween('bills.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('bills.is_damaged', false)
            ->selectRaw('DATE(bills.created_at) as date, SUM((bill_product.selling_price - bill_product.cost_price) * bill_product.quantity) as profit')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $data   = [];
        $total  = 0;

        foreach ($rows as $row) {
            $labels[] = Carbon::parse($row->date)->format('M d');
            $data[]   = $row->profit;
            $total   += $row->profit;
        }

        return [
            'labels' => $labels,
            'data'   => $data,
            'total'  => $total
        ];
    }

    private function getExpenseData($startDate, $endDate, $userId)
    {
        $rows = DB::table('expenses')
            ->where('user_id', $userId)
            ->whereBetween('expense_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('title, SUM(amount) as total')
            ->groupBy('title')
            ->get();

        $total = $rows->sum('total');

        return [
            'total' => $total,
            'categories' => [
                'labels' => $rows->pluck('title')->toArray(),
                'data'   => $rows->pluck('total')->toArray()
            ]
        ];
    }

    private function getCustomerPaymentData($startDate, $endDate, $userId)
    {
        $payments = CustomerPayment::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('DATE(created_at) as date, amount')
            ->orderBy('created_at')
            ->get();

        // Group payments by date and separate positive/negative amounts
        $groupedPayments = $payments->groupBy('date');

        $dates = $groupedPayments->keys()->sort()->values();
        $labels = $dates->map(function ($date) {
            return Carbon::parse($date)->format('M d');
        })->toArray();

        $received = []; // Positive amounts (customer paid us)
        $paid = [];     // Negative amounts (we owe customer)
        $totalReceived = 0;
        $totalPaid = 0;

        foreach ($dates as $date) {
            $dayPayments = $groupedPayments->get($date);

            // Sum positive amounts (customer paid us)
            $receivedAmount = $dayPayments->where('amount', '>', 0)->sum('amount');

            // Sum negative amounts (we owe customer) - convert to positive for display
            $paidAmount = abs($dayPayments->where('amount', '<', 0)->sum('amount'));

            $received[] = $receivedAmount;
            $paid[] = $paidAmount;
            $totalReceived += $receivedAmount;
            $totalPaid += $paidAmount;
        }

        return [
            'labels' => $labels,
            'received' => $received,
            'paid' => $paid,
            'totalReceived' => $totalReceived,
            'totalPaid' => $totalPaid,
            'total' => $totalReceived - $totalPaid
        ];
    }

    private function getEmployeePaymentData($startDate, $endDate, $userId)
    {
        $payments = EmployeePayment::whereHas('employee', function ($q) use ($userId) {
            $q->where('shop_owner_id', $userId);
        })
            ->whereBetween('payment_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with('employee')
            ->get();

        $total = $payments->sum('amount');

        // Group by employee
        $byEmployee = $payments->groupBy('employee.name')->map(function ($group) {
            return $group->sum('amount');
        });

        return [
            'total' => $total,
            'byEmployee' => [
                'labels' => $byEmployee->keys()->toArray(),
                'data' => $byEmployee->values()->toArray()
            ]
        ];
    }

    private function getDamagedData($startDate, $endDate, $userId)
    {
        $summary = DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $userId)
            ->whereBetween('bills.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('bills.is_damaged', true)
            ->selectRaw(
                'SUM(bill_product.cost_price * bill_product.quantity) as total_loss,' .
                    'SUM(bill_product.quantity) as total_count'
            )
            ->first();

        $productRows = DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->join('products', 'bill_product.product_id', '=', 'products.id')
            ->where('bills.user_id', $userId)
            ->whereBetween('bills.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('bills.is_damaged', true)
            ->selectRaw('products.name, SUM(bill_product.cost_price * bill_product.quantity) as loss_value')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('loss_value')
            ->limit(10)
            ->get();

        return [
            'total'    => $summary->total_loss  ?? 0,
            'count'    => $summary->total_count ?? 0,
            'products' => [
                'labels' => $productRows->pluck('name')->toArray(),
                'data'   => $productRows->pluck('loss_value')->toArray()
            ]
        ];
    }

    private function getCustomerBalanceData($userId)
    {
        $summary = DB::table('customers')
            ->where('user_id', $userId)
            ->selectRaw(
                'SUM(CASE WHEN balance < 0 THEN ABS(balance) ELSE 0 END) as total_owing,' .
                    'SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END) as total_owed'
            )
            ->first();

        $topOwing = DB::table('customers')
            ->where('user_id', $userId)
            ->where('balance', '<', 0)
            ->orderBy('balance', 'asc')
            ->limit(10)
            ->get(['name', 'balance']);

        $topOwed = DB::table('customers')
            ->where('user_id', $userId)
            ->where('balance', '>', 0)
            ->orderBy('balance', 'desc')
            ->limit(10)
            ->get(['name', 'balance']);

        return [
            'totalOwing' => $summary->total_owing ?? 0,
            'totalOwed'  => $summary->total_owed  ?? 0,
            'topOwing' => [
                'labels' => $topOwing->pluck('name')->toArray(),
                'data'   => $topOwing->pluck('balance')->toArray()
            ],
            'topOwed' => [
                'labels' => $topOwed->pluck('name')->toArray(),
                'data'   => $topOwed->map(fn($c) => abs($c->balance))->toArray()
            ]
        ];
    }

    private function getTopProducts($startDate, $endDate, $userId)
    {
        return DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->join('products', 'bill_product.product_id', '=', 'products.id')
            ->where('bills.user_id', $userId)
            ->where('bills.is_damaged', false)
            ->whereBetween('bills.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                'products.name',
                DB::raw('SUM(bill_product.quantity) as total_quantity'),
                DB::raw('SUM(bill_product.selling_price * bill_product.quantity) as total_revenue'),
                DB::raw('SUM((bill_product.selling_price - bill_product.cost_price) * bill_product.quantity) as total_profit')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_profit')
            ->limit(10)
            ->get();
    }

    private function getGrowthData($startDate, $endDate, $userId)
    {
        $currentStart = Carbon::parse($startDate);
        $currentEnd = Carbon::parse($endDate);
        $daysDiff = $currentStart->diffInDays($currentEnd);

        $previousStart = $currentStart->copy()->subDays($daysDiff + 1);
        $previousEnd = $currentStart->copy()->subDay();

        // Current period data
        $currentRevenue = Bill::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('is_damaged', false)
            ->sum('total_price');

        $currentProfit = DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $userId)
            ->whereBetween('bills.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('bills.is_damaged', false)
            ->selectRaw('SUM((bill_product.selling_price - bill_product.cost_price) * bill_product.quantity) as profit')
            ->value('profit') ?? 0;

        $currentExpenses = Expense::where('user_id', $userId)
            ->whereBetween('expense_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('amount');

        // NEW: Current purchases
        $currentPurchases = PurchaseBill::where('created_by', $userId)
            ->whereBetween('purchase_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('total_amount');

        // Previous period data
        $previousRevenue = Bill::where('user_id', $userId)
            ->whereBetween('created_at', [$previousStart->format('Y-m-d') . ' 00:00:00', $previousEnd->format('Y-m-d') . ' 23:59:59'])
            ->where('is_damaged', false)
            ->sum('total_price');

        $previousProfit = DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $userId)
            ->whereBetween('bills.created_at', [$previousStart->format('Y-m-d') . ' 00:00:00', $previousEnd->format('Y-m-d') . ' 23:59:59'])
            ->where('bills.is_damaged', false)
            ->selectRaw('SUM((bill_product.selling_price - bill_product.cost_price) * bill_product.quantity) as profit')
            ->value('profit') ?? 0;

        $previousExpenses = Expense::where('user_id', $userId)
            ->whereBetween('expense_date', [$previousStart->format('Y-m-d') . ' 00:00:00', $previousEnd->format('Y-m-d') . ' 23:59:59'])
            ->sum('amount');

        // NEW: Previous purchases
        $previousPurchases = PurchaseBill::where('created_by', $userId)
            ->whereBetween('purchase_date', [$previousStart->format('Y-m-d') . ' 00:00:00', $previousEnd->format('Y-m-d') . ' 23:59:59'])
            ->sum('total_amount');

        return [
            'revenue' => [
                'current' => $currentRevenue,
                'previous' => $previousRevenue,
                'growth' => $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : 0
            ],
            'profit' => [
                'current' => $currentProfit,
                'previous' => $previousProfit,
                'growth' => $previousProfit > 0 ? (($currentProfit - $previousProfit) / $previousProfit) * 100 : 0
            ],
            'expenses' => [
                'current' => $currentExpenses,
                'previous' => $previousExpenses,
                'growth' => $previousExpenses > 0 ? (($currentExpenses - $previousExpenses) / $previousExpenses) * 100 : 0
            ],
            'purchases' => [
                'current' => $currentPurchases,
                'previous' => $previousPurchases,
                'growth' => $previousPurchases > 0 ? (($currentPurchases - $previousPurchases) / $previousPurchases) * 100 : 0
            ]
        ];
    }

    public function printComprehensiveReport(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', $request->get('toDate', Carbon::now()->format('Y-m-d')));

        $userId = auth()->id();
        $shopOwnerId = auth()->user()->role === 'employee' ? auth()->user()->shop_owner_id : $userId;

        // Get all data for the comprehensive report
        $data = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'toDate' => $endDate,
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'generated_by' => auth()->user()->name,
        ];

        // 1. Sales Bills (Selling Bills)
        $data['sales_bills'] = Bill::where('user_id', $shopOwnerId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('is_damaged', false)
            ->where('is_returned', false)
            ->with(['products', 'customer', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. Purchase Bills
        $data['purchase_bills'] = PurchaseBill::where('user_id', $shopOwnerId)
            ->whereBetween('purchase_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with(['supplier', 'products', 'creator'])
            ->orderBy('purchase_date', 'desc')
            ->get();

        // 3. Damaged Bills
        $data['damaged_bills'] = Bill::where('user_id', $shopOwnerId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('is_damaged', true)
            ->with(['products', 'customer', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        // 3b. Returned Bills (NEW)
        $returnedBills = Bill::where('user_id', $shopOwnerId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('is_returned', true)
            ->with('products')
            ->get();

        $data['returnedData'] = [
            'count' => 0,
            'total_bill_value' => 0,
            'inventory_return_value' => 0,
            'lost_profit' => 0,
            'products' => ['labels' => [], 'data' => []]
        ];

        $products = collect();
        foreach ($returnedBills as $bill) {
            $data['returnedData']['total_bill_value'] += $bill->total_price;

            foreach ($bill->products as $product) {
                $quantity = abs($product->pivot->quantity);

                $data['returnedData']['inventory_return_value'] += $product->pivot->cost_price * $quantity;
                $productProfit = ($product->pivot->selling_price - $product->pivot->cost_price) * $quantity;
                $data['returnedData']['lost_profit'] += $productProfit;
                $data['returnedData']['count'] += $quantity;

                $existing = $products->where('name', $product->name)->first();
                if ($existing) {
                    $existingIndex = $products->search(function ($item) use ($product) {
                        return $item['name'] === $product->name;
                    });
                    $existingItem = $products->get($existingIndex);
                    $existingItem['value'] += $product->pivot->cost_price * $quantity;
                    $products->put($existingIndex, $existingItem);
                } else {
                    $products->push([
                        'name' => $product->name,
                        'value' => $product->pivot->cost_price * $quantity
                    ]);
                }
            }
        }

        $products = $products->sortByDesc('value')->take(10);
        $data['returnedData']['products'] = [
            'labels' => $products->pluck('name')->toArray(),
            'data' => $products->pluck('value')->toArray()
        ];

        // 4. Expenses
        $data['expenses'] = Expense::where('user_id', $shopOwnerId)
            ->whereBetween('expense_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('expense_date', 'desc')
            ->get();

        // 5. Customer Payments
        $data['customer_payments'] = CustomerPayment::where('user_id', $shopOwnerId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->get();

        // 6. Supplier Payments
        $data['supplier_payments'] = SupplierPayment::whereHas('supplier', function ($q) use ($shopOwnerId) {
            $q->where('user_id', $shopOwnerId);
        })
            ->whereBetween('payment_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with('supplier')
            ->orderBy('payment_date', 'desc')
            ->get();

        // 7. Employee Payments
        $data['employee_payments'] = EmployeePayment::whereHas('employee', function ($q) use ($shopOwnerId) {
            $q->where('shop_owner_id', $shopOwnerId);
        })
            ->whereBetween('payment_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with('employee')
            ->orderBy('payment_date', 'desc')
            ->get();

        // Calculate summary statistics
        $total_returned_bill_value = isset($data['returnedData']) ? $data['returnedData']['total_bill_value'] : 0;
        $total_returned_profit_loss = isset($data['returnedData']) ? $data['returnedData']['lost_profit'] : 0;

        // Calculate revenue from ALL bills (sales + damaged + returned)
        $totalRevenue = $data['sales_bills']->sum('total_price') +
            $data['damaged_bills']->sum('total_price') +
            $returnedBills->sum('total_price');

        // Calculate profit from ALL bills (sales + damaged + returned)
        $allBillsProfit = $data['sales_bills']->sum(function ($bill) {
            return $bill->products->sum(function ($product) {
                return (($product->pivot->selling_price - $product->pivot->cost_price) * $product->pivot->quantity) - $product->pivot->discount;
            });
        });

        // Add damaged bills profit (which should be negative/loss)
        $allBillsProfit += $data['damaged_bills']->sum(function ($bill) {
            return $bill->products->sum(function ($product) {
                return (($product->pivot->selling_price - $product->pivot->cost_price) * $product->pivot->quantity) - $product->pivot->discount;
            });
        });

        // Subtract returned bills loss
        $allBillsProfit -= abs($total_returned_profit_loss);

        // Calculate total expenses and salaries (same as "Expenses and Salaries" in financial dashboard)
        $totalExpenses = $data['expenses']->sum('amount');
        $totalEmployeePayments = $data['employee_payments']->sum('amount');
        $expensesAndSalaries = $totalExpenses + $totalEmployeePayments;

        // Calculate net cash flow using the same formula as money flow section in financial dashboard
        // Cash In:
        // 1. Total sales from bills (including returned bills)
        $salesCashIn = $data['sales_bills']->sum('total_price') + $returnedBills->sum('total_price');

        // 2. Customer payments received (positive amounts)
        $customerPaymentsIn = $data['customer_payments']->where('amount', '>', 0)->sum('amount');

        // 3. Capital entries (money coming in from outside)
        $capitalIn = CapitalEntry::where('user_id', $shopOwnerId)
            ->whereBetween('entry_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('amount');

        // Total Cash In
        $totalCashIn = $salesCashIn + $customerPaymentsIn + $capitalIn;

        // Cash Out:
        // 1. Supplier payments (actual payments to suppliers)
        $supplierPaymentsOut = $data['supplier_payments']->where('amount', '>', 0)->sum('amount');

        // 2. Employee payments (salaries, etc.)
        $employeePaymentsOut = $data['employee_payments']->sum('amount');

        // 3. Expenses (operational expenses)
        $expensesOut = $data['expenses']->sum('amount');

        // 4. Minus payments (negative customer payments - representing sales on credit)
        $minusPaymentsOut = abs($data['customer_payments']->where('amount', '<', 0)->sum('amount'));

        // Total Cash Out
        $totalCashOut = $supplierPaymentsOut + $employeePaymentsOut + $expensesOut + $minusPaymentsOut;

        // Net Cash Flow
        $netCashFlow = $totalCashIn - $totalCashOut;

        // Summary data structure
        $data['summary'] = [
            'total_sales' => $data['sales_bills']->sum('total_price'),
            'total_purchases' => $data['purchase_bills']->sum('total_amount'),
            'total_expenses' => $totalExpenses,
            'total_customer_payments' => $data['customer_payments']->sum('amount'),
            'total_supplier_payments' => $data['supplier_payments']->where('amount', '>', 0)->sum('amount'),
            'total_employee_payments' => $totalEmployeePayments,
            'total_damaged_loss' => $data['damaged_bills']->sum(function ($bill) {
                return $bill->products->sum(function ($product) {
                    return $product->pivot->cost_price * $product->pivot->quantity;
                });
            }),
            'total_returned_loss' => $total_returned_profit_loss,
            // Financial dashboard metrics
            'total_revenue' => $totalRevenue,
            'expenses_and_salaries' => $expensesAndSalaries,
            'financial_dashboard_profit' => $allBillsProfit,
            'net_cash_flow' => $netCashFlow,
        ];

        return view('dashboard.comprehensive-report', $data);
    }

    public function exportData()
    {
        $userId = auth()->id();
        // For employees, get the shop owner ID
        $shopOwnerId = auth()->user()->role === 'employee' ? auth()->user()->shop_owner_id : $userId;

        $spreadsheet = new Spreadsheet();

        // Remove default sheet and create custom sheets
        $spreadsheet->removeSheetByIndex(0);

        // 1. Products Sheet (Updated with category)
        $productsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Products');
        $spreadsheet->addSheet($productsSheet);

        $products = Product::where('user_id', $shopOwnerId)->get();
        $productsSheet->fromArray([
            ['ID', 'Name', 'Category', 'Barcode', 'Quantity', 'Cost Price', 'Selling Price', 'Has Tags', 'Created At']
        ]);

        $row = 2;
        foreach ($products as $product) {
            $productsSheet->fromArray([
                [
                    $product->id,
                    $product->name,
                    $product->category,
                    $product->barcode,
                    $product->quantity,
                    $product->cost_price,
                    $product->selling_price,
                    $product->has_tags ? 'Yes' : 'No',
                    $product->created_at->format('Y-m-d H:i:s')
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 2. Customers Sheet
        $customersSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Customers');
        $spreadsheet->addSheet($customersSheet);

        $customers = Customer::where('user_id', $shopOwnerId)->get();
        $customersSheet->fromArray([
            ['ID', 'Name', 'Phone', 'Balance', 'Created At']
        ]);

        $row = 2;
        foreach ($customers as $customer) {
            $customersSheet->fromArray([
                [
                    $customer->id,
                    $customer->name,
                    $customer->phone,
                    $customer->balance,
                    $customer->created_at->format('Y-m-d H:i:s')
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 3. Bills Sheet (Updated with created_by)
        $billsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Bills');
        $spreadsheet->addSheet($billsSheet);

        $bills = Bill::where('user_id', $shopOwnerId)->with('customer')->get();
        $billsSheet->fromArray([
            ['ID', 'Customer Name', 'Total Price', 'Note', 'Is Damaged', 'Created By', 'Created At']
        ]);

        $row = 2;
        foreach ($bills as $bill) {
            $billsSheet->fromArray([
                [
                    $bill->id,
                    $bill->customer->name ?? 'N/A',
                    $bill->total_price,
                    $bill->note,
                    $bill->is_damaged ? 'Yes' : 'No',
                    $bill->created_by,
                    $bill->created_at->format('Y-m-d H:i:s')
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 4. Bill Products Sheet (NEW - with tags)
        $billProductsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Bill Products');
        $spreadsheet->addSheet($billProductsSheet);

        $billProducts = DB::table('bill_product')
            ->join('bills', 'bill_product.bill_id', '=', 'bills.id')
            ->join('products', 'bill_product.product_id', '=', 'products.id')
            ->where('bills.user_id', $shopOwnerId)
            ->select(
                'bill_product.*',
                'bills.created_at as bill_date',
                'products.name as product_name'
            )
            ->get();

        $billProductsSheet->fromArray([
            ['Bill ID', 'Product Name', 'Quantity', 'Cost Price', 'Selling Price', 'Discount', 'Tags', 'Bill Date']
        ]);

        $row = 2;
        foreach ($billProducts as $billProduct) {
            $billProductsSheet->fromArray([
                [
                    $billProduct->bill_id,
                    $billProduct->product_name,
                    $billProduct->quantity,
                    $billProduct->cost_price,
                    $billProduct->selling_price,
                    $billProduct->discount,
                    $billProduct->tags ?? 'N/A',
                    Carbon::parse($billProduct->bill_date)->format('Y-m-d H:i:s')
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 5. Customer Payments Sheet (Updated with new type column)
        $customerPaymentsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Customer Payments');
        $spreadsheet->addSheet($customerPaymentsSheet);

        $customerPayments = CustomerPayment::where('user_id', $shopOwnerId)->with('customer')->get();
        $customerPaymentsSheet->fromArray([
            ['ID', 'Customer Name', 'Amount', 'Payment Type', 'Note', 'Created At']
        ]);

        $row = 2;
        foreach ($customerPayments as $payment) {
            $customerPaymentsSheet->fromArray([
                [
                    $payment->id,
                    $payment->customer->name ?? 'N/A',
                    $payment->amount,
                    ucfirst($payment->type),
                    $payment->note,
                    $payment->created_at->format('Y-m-d H:i:s')
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 6. Expenses Sheet
        $expensesSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Expenses');
        $spreadsheet->addSheet($expensesSheet);

        $expenses = Expense::where('user_id', $shopOwnerId)->get();
        $expensesSheet->fromArray([
            ['ID', 'Title', 'Amount', 'Expense Date', 'Notes', 'Created At']
        ]);

        $row = 2;
        foreach ($expenses as $expense) {
            $expensesSheet->fromArray([
                [
                    $expense->id,
                    $expense->title,
                    $expense->amount,
                    $expense->expense_date,
                    $expense->notes,
                    $expense->created_at->format('Y-m-d H:i:s')
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 7. Employees Sheet
        $employeesSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Employees');
        $spreadsheet->addSheet($employeesSheet);

        $employees = Employee::where('shop_owner_id', $shopOwnerId)->get();
        $employeesSheet->fromArray([
            ['ID', 'Name', 'Job Title', 'Monthly Salary', 'Created At']
        ]);

        $row = 2;
        foreach ($employees as $employee) {
            $employeesSheet->fromArray([
                [
                    $employee->id,
                    $employee->name,
                    $employee->job_title,
                    $employee->monthly_salary,
                    $employee->created_at->format('Y-m-d H:i:s')
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 8. Employee Payments Sheet (Updated with type and note columns)
        $employeePaymentsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Employee Payments');
        $spreadsheet->addSheet($employeePaymentsSheet);

        $employeePayments = EmployeePayment::whereHas('employee', function ($q) use ($shopOwnerId) {
            $q->where('shop_owner_id', $shopOwnerId);
        })->with('employee')->get();

        $employeePaymentsSheet->fromArray([
            ['ID', 'Employee Name', 'Amount', 'Type', 'Note', 'Payment Date', 'Created At']
        ]);

        $row = 2;
        foreach ($employeePayments as $payment) {
            $employeePaymentsSheet->fromArray([
                [
                    $payment->id,
                    $payment->employee->name ?? 'N/A',
                    $payment->amount,
                    ucfirst($payment->type),
                    $payment->note,
                    $payment->payment_date,
                    $payment->created_at->format('Y-m-d H:i:s')
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 9. Batches Sheet (NEW)
        $batchesSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Batches');
        $spreadsheet->addSheet($batchesSheet);

        $batches = DB::table('batches')
            ->join('products', 'batches.product_id', '=', 'products.id')
            ->where('products.user_id', $shopOwnerId)
            ->select('batches.*', 'products.name as product_name')
            ->get();

        $batchesSheet->fromArray([
            ['ID', 'Product Name', 'Quantity', 'Cost Price', 'Created At']
        ]);

        $row = 2;
        foreach ($batches as $batch) {
            $batchesSheet->fromArray([
                [
                    $batch->id,
                    $batch->product_name,
                    $batch->quantity,
                    $batch->cost_price,
                    $batch->created_at
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 10. Tags Sheet (NEW)
        $tagsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Tags');
        $spreadsheet->addSheet($tagsSheet);

        $tags = Tag::where('user_id', $shopOwnerId)->get();
        $tagsSheet->fromArray([
            ['ID', 'Name', 'Price', 'Created At']
        ]);

        $row = 2;
        foreach ($tags as $tag) {
            $tagsSheet->fromArray([
                [$tag->id, $tag->name, $tag->price, $tag->created_at->format('Y-m-d H:i:s')]
            ], null, 'A' . $row);
            $row++;
        }

        // 11. Suppliers Sheet (NEW)
        $suppliersSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Suppliers');
        $spreadsheet->addSheet($suppliersSheet);

        $suppliers = Supplier::where('user_id', $shopOwnerId)->get();
        $suppliersSheet->fromArray([
            ['ID', 'Name', 'Phone', 'Email', 'Address', 'Balance', 'Notes', 'Created At']
        ]);

        $row = 2;
        foreach ($suppliers as $supplier) {
            $suppliersSheet->fromArray([
                [
                    $supplier->id,
                    $supplier->name,
                    $supplier->phone,
                    $supplier->email,
                    $supplier->address,
                    $supplier->balance,
                    $supplier->notes,
                    $supplier->created_at->format('Y-m-d H:i:s')
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 12. Purchase Bills Sheet (NEW)
        $purchaseBillsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Purchase Bills');
        $spreadsheet->addSheet($purchaseBillsSheet);

        $purchaseBills = PurchaseBill::where('user_id', $shopOwnerId)->with('supplier')->get();
        $purchaseBillsSheet->fromArray([
            ['ID', 'Supplier Name', 'Total Amount', 'Reference Number', 'Purchase Date', 'Notes', 'Created By', 'Created At']
        ]);

        $row = 2;
        foreach ($purchaseBills as $bill) {
            $purchaseBillsSheet->fromArray([
                [
                    $bill->id,
                    $bill->supplier->name ?? 'N/A',
                    $bill->total_amount,
                    $bill->reference_number,
                    $bill->purchase_date,
                    $bill->notes,
                    $bill->created_by,
                    $bill->created_at->format('Y-m-d H:i:s')
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 13. Purchase Bill Products Sheet (NEW)
        $purchaseProductsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Purchase Bill Products');
        $spreadsheet->addSheet($purchaseProductsSheet);

        $purchaseProducts = DB::table('purchase_bill_product')
            ->join('purchase_bills', 'purchase_bill_product.purchase_bill_id', '=', 'purchase_bills.id')
            ->join('products', 'purchase_bill_product.product_id', '=', 'products.id')
            ->where('purchase_bills.user_id', $shopOwnerId)
            ->select(
                'purchase_bill_product.*',
                'purchase_bills.purchase_date',
                'products.name as product_name'
            )
            ->get();

        $purchaseProductsSheet->fromArray([
            ['Purchase Bill ID', 'Product Name', 'Quantity', 'Unit Cost', 'Total Cost', 'Barcodes', 'Purchase Date']
        ]);

        $row = 2;
        foreach ($purchaseProducts as $product) {
            $purchaseProductsSheet->fromArray([
                [
                    $product->purchase_bill_id,
                    $product->product_name,
                    $product->quantity,
                    $product->unit_cost,
                    $product->total_cost,
                    json_encode($product->barcodes),
                    $product->purchase_date
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 14. Supplier Payments Sheet (NEW)
        $supplierPaymentsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Supplier Payments');
        $spreadsheet->addSheet($supplierPaymentsSheet);

        $supplierPayments = SupplierPayment::whereHas('supplier', function ($q) use ($shopOwnerId) {
            $q->where('user_id', $shopOwnerId);
        })->with('supplier')->get();

        $supplierPaymentsSheet->fromArray([
            ['ID', 'Supplier Name', 'Amount', 'Payment Type', 'Payment Date', 'Note', 'Created At']
        ]);

        $row = 2;
        foreach ($supplierPayments as $payment) {
            $supplierPaymentsSheet->fromArray([
                [
                    $payment->id,
                    $payment->supplier->name ?? 'N/A',
                    $payment->amount,
                    ucfirst($payment->type),
                    $payment->payment_date,
                    $payment->note,
                    $payment->created_at->format('Y-m-d H:i:s')
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 15. Product Barcodes Sheet (NEW)
        $productBarcodesSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Product Barcodes');
        $spreadsheet->addSheet($productBarcodesSheet);

        $productBarcodes = ProductBarcode::whereHas('product', function ($q) use ($shopOwnerId) {
            $q->where('user_id', $shopOwnerId);
        })->with('product')->get();

        $productBarcodesSheet->fromArray([
            ['ID', 'Product Name', 'Barcode', 'Created At']
        ]);

        $row = 2;
        foreach ($productBarcodes as $barcode) {
            $productBarcodesSheet->fromArray([
                [
                    $barcode->id,
                    $barcode->product->name ?? 'N/A',
                    $barcode->barcode,
                    $barcode->created_at->format('Y-m-d H:i:s')
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // 16. Capital Entries Sheet (NEW)
        $capitalSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Capital Entries');
        $spreadsheet->addSheet($capitalSheet);

        $capitalEntries = CapitalEntry::where('user_id', $shopOwnerId)->get();

        $capitalSheet->fromArray([
            ['ID', 'Amount', 'Note', 'Entry Date', 'Created At']
        ]);

        $row = 2;
        foreach ($capitalEntries as $entry) {
            $capitalSheet->fromArray([
                [
                    $entry->id,
                    $entry->amount,
                    $entry->note,
                    $entry->entry_date,
                    $entry->created_at->format('Y-m-d H:i:s')
                ]
            ], null, 'A' . $row);
            $row++;
        }

        $fileName = 'complete_database_export_' . auth()->user()->name . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function getReturnedData($startDate, $endDate, $userId)
    {
        $summary = DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $userId)
            ->whereBetween('bills.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('bills.is_returned', true)
            ->selectRaw(
                'SUM(bills.total_price) as total_bill_value,' .
                    'SUM(bill_product.cost_price * ABS(bill_product.quantity)) as inventory_return_value,' .
                    'SUM((bill_product.selling_price - bill_product.cost_price) * ABS(bill_product.quantity)) as lost_profit,' .
                    'SUM(ABS(bill_product.quantity)) as total_count'
            )
            ->first();

        $productRows = DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->join('products', 'bill_product.product_id', '=', 'products.id')
            ->where('bills.user_id', $userId)
            ->whereBetween('bills.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('bills.is_returned', true)
            ->selectRaw('products.name, SUM(bill_product.cost_price * ABS(bill_product.quantity)) as return_value')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('return_value')
            ->limit(10)
            ->get();

        return [
            'total_bill_value'        => - ($summary->total_bill_value        ?? 0),
            'inventory_return_value'  => - ($summary->inventory_return_value  ?? 0),
            'lost_profit'             => - ($summary->lost_profit             ?? 0),
            'count'                   => ($summary->total_count             ?? 0),
            'products' => [
                'labels' => $productRows->pluck('name')->toArray(),
                'data'   => $productRows->pluck('return_value')->toArray()
            ]
        ];
    }
}
