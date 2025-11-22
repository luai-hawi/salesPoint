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

        return view('dashboard.financial', compact(
            'summaryData',
            'storeValueData',
            'revenueData',
            'profitData',
            'expenseData',
            'customerPaymentData',
            'employeePaymentData',
            'damagedData',
            'customerBalanceData',
            'purchaseData',
            'supplierPaymentData',
            'supplierBalanceData',
            'topProducts',
            'topSuppliers',
            'growthData',
            'startDate',
            'endDate'
        ));
    }

    private function getSummaryData($startDate, $endDate, $userId)
    {
        // Calculate total revenue
        $totalRevenue = Bill::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_damaged', false)
            ->sum('total_price');

        // Calculate total profit
        $totalProfit = Bill::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_damaged', false)
            ->with('products')
            ->get()
            ->sum(function ($bill) {
                return $bill->products->sum(function ($product) {
                    return (($product->pivot->selling_price - $product->pivot->cost_price) * $product->pivot->quantity) - $product->pivot->discount;
                });
            });

        // Calculate total expenses
        $totalExpenses = Expense::where('user_id', $userId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');

        // Calculate total employee payments
        $totalEmployeePayments = EmployeePayment::whereHas('employee', function ($q) use ($userId) {
            $q->where('shop_owner_id', $userId);
        })
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        // NEW: Calculate total purchases
        $totalPurchases = PurchaseBill::where('created_by', $userId)
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->sum('total_amount');

        // NEW: Calculate total supplier payments
        $totalSupplierPayments = SupplierPayment::whereHas('supplier', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('amount', '>', 0) // Only outgoing payments
            ->sum('amount');

        $netIncome = $totalProfit - $totalExpenses - $totalEmployeePayments;

        return [
            'totalRevenue' => $totalRevenue,
            'totalProfit' => $totalProfit,
            'totalExpenses' => $totalExpenses,
            'totalEmployeePayments' => $totalEmployeePayments,
            'totalPurchases' => $totalPurchases,
            'totalSupplierPayments' => $totalSupplierPayments,
            'netIncome' => $netIncome
        ];
    }

    // NEW: Store Value Data
    private function getStoreValueData($userId)
    {
        $products = Product::where('user_id', $userId)
            ->where('quantity', '>', 0) // Only positive quantity
            ->get();

        $totalCostValue = $products->sum(function ($product) {
            return $product->quantity * $product->cost_price;
        });

        $totalSellingValue = $products->sum(function ($product) {
            return $product->quantity * $product->selling_price;
        });

        $totalItems = $products->sum('quantity');
        $totalProducts = $products->count();

        $potentialProfit = $totalSellingValue - $totalCostValue;

        return [
            'totalCostValue' => $totalCostValue,
            'totalSellingValue' => $totalSellingValue,
            'potentialProfit' => $potentialProfit,
            'totalItems' => $totalItems,
            'totalProducts' => $totalProducts
        ];
    }

    // NEW: Purchase Data
    private function getPurchaseData($startDate, $endDate, $userId)
    {
        $purchases = PurchaseBill::where('created_by', $userId)
            ->whereBetween('purchase_date', [$startDate, $endDate])
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
            ->whereBetween('payment_date', [$startDate, $endDate])
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
        $suppliers = Supplier::where('user_id', $userId)->get();

        $totalOwing = $suppliers->where('balance', '>', 0)->sum('balance'); // We owe them
        $totalOwed = abs($suppliers->where('balance', '<', 0)->sum('balance')); // They owe us

        $topOwing = $suppliers->where('balance', '>', 0)
            ->sortByDesc('balance')
            ->take(10);

        $topOwed = $suppliers->where('balance', '<', 0)
            ->sortBy('balance')
            ->take(10);

        return [
            'totalOwing' => $totalOwing,
            'totalOwed' => $totalOwed,
            'topOwing' => [
                'labels' => $topOwing->pluck('name')->toArray(),
                'data' => $topOwing->pluck('balance')->toArray()
            ],
            'topOwed' => [
                'labels' => $topOwed->pluck('name')->toArray(),
                'data' => $topOwed->map(function ($supplier) {
                    return abs($supplier->balance);
                })->toArray()
            ]
        ];
    }

    // NEW: Top Suppliers
    private function getTopSuppliers($startDate, $endDate, $userId)
    {
        return DB::table('purchase_bills')
            ->join('suppliers', 'purchase_bills.supplier_id', '=', 'suppliers.id')
            ->where('purchase_bills.created_by', $userId)
            ->whereBetween('purchase_bills.purchase_date', [$startDate, $endDate])
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

    private function getRevenueData($startDate, $endDate, $userId)
    {
        $bills = Bill::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
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
        $bills = Bill::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_damaged', false)
            ->with('products')
            ->get()
            ->groupBy(function ($bill) {
                return $bill->created_at->format('Y-m-d');
            });

        $labels = [];
        $data = [];
        $total = 0;

        foreach ($bills as $date => $dayBills) {
            $dayProfit = $dayBills->sum(function ($bill) {
                return $bill->products->sum(function ($product) {
                    return ($product->pivot->selling_price - $product->pivot->cost_price) * $product->pivot->quantity;
                });
            });

            $labels[] = Carbon::parse($date)->format('M d');
            $data[] = $dayProfit;
            $total += $dayProfit;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'total' => $total
        ];
    }

    private function getExpenseData($startDate, $endDate, $userId)
    {
        $expenses = Expense::where('user_id', $userId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        $total = $expenses->sum('amount');

        // Group by title for categories
        $categories = $expenses->groupBy('title')->map(function ($group) {
            return $group->sum('amount');
        });

        return [
            'total' => $total,
            'categories' => [
                'labels' => $categories->keys()->toArray(),
                'data' => $categories->values()->toArray()
            ]
        ];
    }

    private function getCustomerPaymentData($startDate, $endDate, $userId)
    {
        $payments = CustomerPayment::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
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
            'totalPaid' => $totalPaid
        ];
    }

    private function getEmployeePaymentData($startDate, $endDate, $userId)
    {
        $payments = EmployeePayment::whereHas('employee', function ($q) use ($userId) {
            $q->where('shop_owner_id', $userId);
        })
            ->whereBetween('payment_date', [$startDate, $endDate])
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
        $damagedBills = Bill::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_damaged', true)
            ->with('products')
            ->get();

        $count = 0;
        // Calculate total loss based on cost price (since selling price is 0 due to 100% discount)
        $totalLoss = 0;
        $products = collect();

        foreach ($damagedBills as $bill) {
            foreach ($bill->products as $product) {
                // Calculate loss for this product (cost_price * quantity)
                $productLoss = $product->pivot->cost_price * $product->pivot->quantity;
                $totalLoss += $productLoss;
                $count += $product->pivot->quantity;

                // Group by product name for the chart
                $existing = $products->where('name', $product->name)->first();
                if ($existing) {
                    // Add to existing product's loss value
                    $existingIndex = $products->search(function ($item) use ($product) {
                        return $item['name'] === $product->name;
                    });
                    $existingItem = $products->get($existingIndex);
                    $existingItem['value'] += $productLoss;
                    $products->put($existingIndex, $existingItem);
                } else {
                    // Add new product to collection
                    $products->push([
                        'name' => $product->name,
                        'value' => $productLoss
                    ]);
                }
            }
        }

        // Sort by loss value (highest first) and take top 10
        $products = $products->sortByDesc('value')->take(10);

        return [
            'total' => $totalLoss,  // Total loss based on cost prices
            'count' => $count,
            'products' => [
                'labels' => $products->pluck('name')->toArray(),
                'data' => $products->pluck('value')->toArray()
            ]
        ];
    }

    private function getCustomerBalanceData($userId)
    {
        $customers = Customer::where('user_id', $userId)->get();

        $totalOwing = abs($customers->where('balance', '<', 0)->sum('balance'));
        $totalOwed = $customers->where('balance', '>', 0)->sum('balance');

        $topOwing = $customers->where('balance', '<', 0)
            ->sortBy('balance')
            ->take(10);

        $topOwed = $customers->where('balance', '>', 0)
            ->sortByDesc('balance')
            ->take(10);

        return [
            'totalOwing' => $totalOwing,
            'totalOwed' => $totalOwed,
            'topOwing' => [
                'labels' => $topOwing->pluck('name')->toArray(),
                'data' => $topOwing->pluck('balance')->toArray()
            ],
            'topOwed' => [
                'labels' => $topOwed->pluck('name')->toArray(),
                'data' => $topOwed->map(function ($customer) {
                    return abs($customer->balance);
                })->toArray()
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
            ->whereBetween('bills.created_at', [$startDate, $endDate])
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
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_damaged', false)
            ->sum('total_price');

        $currentProfit = Bill::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_damaged', false)
            ->with('products')
            ->get()
            ->sum(function ($bill) {
                return $bill->products->sum(function ($product) {
                    return ($product->pivot->selling_price - $product->pivot->cost_price) * $product->pivot->quantity;
                });
            });

        $currentExpenses = Expense::where('user_id', $userId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');

        // NEW: Current purchases
        $currentPurchases = PurchaseBill::where('created_by', $userId)
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->sum('total_amount');

        // Previous period data
        $previousRevenue = Bill::where('user_id', $userId)
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->where('is_damaged', false)
            ->sum('total_price');

        $previousProfit = Bill::where('user_id', $userId)
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->where('is_damaged', false)
            ->with('products')
            ->get()
            ->sum(function ($bill) {
                return $bill->products->sum(function ($product) {
                    return ($product->pivot->selling_price - $product->pivot->cost_price) * $product->pivot->quantity;
                });
            });

        $previousExpenses = Expense::where('user_id', $userId)
            ->whereBetween('expense_date', [$previousStart, $previousEnd])
            ->sum('amount');

        // NEW: Previous purchases
        $previousPurchases = PurchaseBill::where('created_by', $userId)
            ->whereBetween('purchase_date', [$previousStart, $previousEnd])
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
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));

        $userId = auth()->id();
        $shopOwnerId = auth()->user()->role === 'employee' ? auth()->user()->shop_owner_id : $userId;

        // Get all data for the comprehensive report
        $data = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'generated_by' => auth()->user()->name,
        ];

        // 1. Sales Bills (Selling Bills)
        $data['sales_bills'] = Bill::where('user_id', $shopOwnerId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_damaged', false)
            ->with(['products', 'customer', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. Purchase Bills
        $data['purchase_bills'] = PurchaseBill::where('user_id', $shopOwnerId)
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->with(['supplier', 'products', 'creator'])
            ->orderBy('purchase_date', 'desc')
            ->get();

        // 3. Damaged Bills
        $data['damaged_bills'] = Bill::where('user_id', $shopOwnerId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_damaged', true)
            ->with(['products', 'customer', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        // 4. Expenses
        $data['expenses'] = Expense::where('user_id', $shopOwnerId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->orderBy('expense_date', 'desc')
            ->get();

        // 5. Customer Payments
        $data['customer_payments'] = CustomerPayment::where('user_id', $shopOwnerId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->get();

        // 6. Supplier Payments
        $data['supplier_payments'] = SupplierPayment::whereHas('supplier', function ($q) use ($shopOwnerId) {
            $q->where('user_id', $shopOwnerId);
        })
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->with('supplier')
            ->orderBy('payment_date', 'desc')
            ->get();

        // 7. Employee Payments
        $data['employee_payments'] = EmployeePayment::whereHas('employee', function ($q) use ($shopOwnerId) {
            $q->where('shop_owner_id', $shopOwnerId);
        })
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->with('employee')
            ->orderBy('payment_date', 'desc')
            ->get();

        // Calculate summary statistics
        $data['summary'] = [
            'total_sales' => $data['sales_bills']->sum('total_price'),
            'total_purchases' => $data['purchase_bills']->sum('total_amount'),
            'total_expenses' => $data['expenses']->sum('amount'),
            'total_customer_payments' => $data['customer_payments']->sum('amount'),
            'total_supplier_payments' => $data['supplier_payments']->sum('amount'),
            'total_employee_payments' => $data['employee_payments']->sum('amount'),
            'total_damaged_loss' => $data['damaged_bills']->sum(function ($bill) {
                return $bill->products->sum(function ($product) {
                    return $product->pivot->cost_price * $product->pivot->quantity;
                });
            }),
        ];

        // Calculate profit exactly as shown in financial dashboard (gross profit from sales)
        $financialDashboardProfit = $data['sales_bills']->sum(function ($bill) {
            return $bill->products->sum(function ($product) {
                return (($product->pivot->selling_price - $product->pivot->cost_price) * $product->pivot->quantity) - $product->pivot->discount;
            });
        });

        // Calculate profit/loss
        $totalRevenue = $data['summary']['total_sales'];
        $totalCosts = $data['summary']['total_purchases'] + $data['summary']['total_expenses'] + $data['summary']['total_employee_payments'] + $data['summary']['total_damaged_loss'];
        $netCashFlow = $data['summary']['total_customer_payments'] - $data['summary']['total_supplier_payments'];

        $data['summary']['total_revenue'] = $totalRevenue;
        $data['summary']['total_costs'] = $totalCosts;
        $data['summary']['net_profit'] = $totalRevenue - $totalCosts;
        $data['summary']['net_cash_flow'] = $netCashFlow;
        $data['summary']['financial_dashboard_profit'] = $financialDashboardProfit;

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

        // 8. Employee Payments Sheet (NEW)
        $employeePaymentsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Employee Payments');
        $spreadsheet->addSheet($employeePaymentsSheet);

        $employeePayments = EmployeePayment::whereHas('employee', function ($q) use ($shopOwnerId) {
            $q->where('shop_owner_id', $shopOwnerId);
        })->with('employee')->get();

        $employeePaymentsSheet->fromArray([
            ['ID', 'Employee Name', 'Amount', 'Payment Date', 'Created At']
        ]);

        $row = 2;
        foreach ($employeePayments as $payment) {
            $employeePaymentsSheet->fromArray([
                [
                    $payment->id,
                    $payment->employee->name ?? 'N/A',
                    $payment->amount,
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
            ['Purchase Bill ID', 'Product Name', 'Quantity', 'Unit Cost', 'Total Cost', 'Purchase Date']
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
}
