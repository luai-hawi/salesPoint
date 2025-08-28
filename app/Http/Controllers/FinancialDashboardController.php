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
        
        $userId = auth()->id();
        
        // Summary Data
        $summaryData = $this->getSummaryData($startDate, $endDate, $userId);
        
        
        // Revenue Data
        $revenueData = $this->getRevenueData($startDate, $endDate, $userId);
        
        // Profit Data
        $profitData = $this->getProfitData($startDate, $endDate, $userId);
        
        // Expense Data
        $expenseData = $this->getExpenseData($startDate, $endDate, $userId);
        
        // Customer Payment Data
        $customerPaymentData = $this->getCustomerPaymentData($startDate, $endDate, $userId);
        
        
        // Employee Payment Data
        $employeePaymentData = $this->getEmployeePaymentData($startDate, $endDate, $userId);
        
        // Damaged Products Data
        $damagedData = $this->getDamagedData($startDate, $endDate, $userId);
        
        // Customer Balance Data
        $customerBalanceData = $this->getCustomerBalanceData($userId);
        
        // Top Products
        $topProducts = $this->getTopProducts($startDate, $endDate, $userId);
        
        // Growth Data
        $growthData = $this->getGrowthData($startDate, $endDate, $userId);
        
        return view('dashboard.financial', compact(
            'summaryData', 'revenueData', 'profitData', 'expenseData',
            'customerPaymentData', 'employeePaymentData', 'damagedData',
            'customerBalanceData', 'topProducts', 'growthData',
            'startDate', 'endDate'
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
        $totalEmployeePayments = EmployeePayment::whereHas('employee', function($q) use ($userId) {
            $q->where('shop_owner_id', $userId);
        })
        ->whereBetween('payment_date', [$startDate, $endDate])
        ->sum('amount');
        
        $netIncome = $totalProfit - $totalExpenses - $totalEmployeePayments;
        
        return [
            'totalRevenue' => $totalRevenue,
            'totalProfit' => $totalProfit,
            'totalExpenses' => $totalExpenses,
            'totalEmployeePayments' => $totalEmployeePayments,
            'netIncome' => $netIncome
        ];
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
            ->groupBy(function($bill) {
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
        $categories = $expenses->groupBy('title')->map(function($group) {
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
    $labels = $dates->map(function($date) {
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
        $payments = EmployeePayment::whereHas('employee', function($q) use ($userId) {
            $q->where('shop_owner_id', $userId);
        })
        ->whereBetween('payment_date', [$startDate, $endDate])
        ->with('employee')
        ->get();
        
        $total = $payments->sum('amount');
        
        // Group by employee
        $byEmployee = $payments->groupBy('employee.name')->map(function($group) {
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
            $count+= $product->pivot->quantity;
            
            // Group by product name for the chart
            $existing = $products->where('name', $product->name)->first();
            if ($existing) {
                // Add to existing product's loss value
                $existingIndex = $products->search(function($item) use ($product) {
                    return $item['name'] === $product->name;
                });
                $products[$existingIndex]['value'] += $productLoss;
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
                'data' => $topOwed->map(function($customer) {
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
            ]
        ];
    }
    public function exportData()
{
    $userId = auth()->id();
    $spreadsheet = new Spreadsheet();
    
    // Remove default sheet and create custom sheets
    $spreadsheet->removeSheetByIndex(0);
    
    // 1. Products Sheet
    $productsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Products');
    $spreadsheet->addSheet($productsSheet);
    
    $products = Product::where('user_id', $userId)->get();
    $productsSheet->fromArray([
        ['ID', 'Name', 'Barcode', 'Quantity', 'Cost Price', 'Selling Price', 'Has Tags', 'Created At']
    ]);
    
    $row = 2;
    foreach ($products as $product) {
        $productsSheet->fromArray([
            [$product->id, $product->name, $product->barcode, $product->quantity, 
             $product->cost_price, $product->selling_price, $product->has_tags ? 'Yes' : 'No', 
             $product->created_at->format('Y-m-d H:i:s')]
        ], null, 'A' . $row);
        $row++;
    }
    
    // 2. Customers Sheet
    $customersSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Customers');
    $spreadsheet->addSheet($customersSheet);
    
    $customers = Customer::where('user_id', $userId)->get();
    $customersSheet->fromArray([
        ['ID', 'Name', 'Phone', 'Balance', 'Created At']
    ]);
    
    $row = 2;
    foreach ($customers as $customer) {
        $customersSheet->fromArray([
            [$customer->id, $customer->name, $customer->phone, $customer->balance, 
             $customer->created_at->format('Y-m-d H:i:s')]
        ], null, 'A' . $row);
        $row++;
    }
    
    // 3. Bills Sheet
    $billsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Bills');
    $spreadsheet->addSheet($billsSheet);
    
    $bills = Bill::where('user_id', $userId)->with('customer')->get();
    $billsSheet->fromArray([
        ['ID', 'Customer Name', 'Total Price', 'Note', 'Is Damaged', 'Created At']
    ]);
    
    $row = 2;
    foreach ($bills as $bill) {
        $billsSheet->fromArray([
            [$bill->id, $bill->customer->name ?? 'N/A', $bill->total_price, $bill->note, 
             $bill->is_damaged ? 'Yes' : 'No', $bill->created_at->format('Y-m-d H:i:s')]
        ], null, 'A' . $row);
        $row++;
    }
    
    // 4. Bill Products Sheet (Pivot table data)
    $billProductsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Bill_Products');
    $spreadsheet->addSheet($billProductsSheet);
    
    $billProducts = \DB::table('bill_product')
        ->join('bills', 'bill_product.bill_id', '=', 'bills.id')
        ->join('products', 'bill_product.product_id', '=', 'products.id')
        ->where('bills.user_id', $userId)
        ->select('bill_product.*', 'products.name as product_name')
        ->get();
    
    $billProductsSheet->fromArray([
        ['Bill ID', 'Product ID', 'Product Name', 'Quantity', 'Discount', 'Cost Price', 'Selling Price', 'Tags']
    ]);
    
    $row = 2;
    foreach ($billProducts as $billProduct) {
        $billProductsSheet->fromArray([
            [$billProduct->bill_id, $billProduct->product_id, $billProduct->product_name,
             $billProduct->quantity, $billProduct->discount, $billProduct->cost_price,
             $billProduct->selling_price, $billProduct->tags]
        ], null, 'A' . $row);
        $row++;
    }
    
    // 5. Customer Payments Sheet
    $paymentsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Customer_Payments');
    $spreadsheet->addSheet($paymentsSheet);
    
    $payments = CustomerPayment::where('user_id', $userId)->with('customer')->get();
    $paymentsSheet->fromArray([
        ['ID', 'Customer Name', 'Amount', 'Type', 'Note', 'Created At']
    ]);
    
    $row = 2;
    foreach ($payments as $payment) {
        $paymentsSheet->fromArray([
            [$payment->id, $payment->customer->name ?? 'N/A', $payment->amount, 
             $payment->type, $payment->note, $payment->created_at->format('Y-m-d H:i:s')]
        ], null, 'A' . $row);
        $row++;
    }
    
    // 6. Expenses Sheet
    $expensesSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Expenses');
    $spreadsheet->addSheet($expensesSheet);
    
    $expenses = Expense::where('user_id', $userId)->get();
    $expensesSheet->fromArray([
        ['ID', 'Title', 'Amount', 'Expense Date', 'Notes', 'Created At']
    ]);
    
    $row = 2;
    foreach ($expenses as $expense) {
        $expensesSheet->fromArray([
            [$expense->id, $expense->title, $expense->amount, $expense->expense_date,
             $expense->notes, $expense->created_at->format('Y-m-d H:i:s')]
        ], null, 'A' . $row);
        $row++;
    }
    
    // 7. Employees Sheet
    $employeesSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Employees');
    $spreadsheet->addSheet($employeesSheet);
    
    $employees = Employee::where('shop_owner_id', $userId)->get();
    $employeesSheet->fromArray([
        ['ID', 'Name', 'Job Title', 'Monthly Salary', 'Created At']
    ]);
    
    $row = 2;
    foreach ($employees as $employee) {
        $employeesSheet->fromArray([
            [$employee->id, $employee->name, $employee->job_title, $employee->monthly_salary,
             $employee->created_at->format('Y-m-d H:i:s')]
        ], null, 'A' . $row);
        $row++;
    }
    
    // 8. Employee Payments Sheet
    $empPaymentsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Employee_Payments');
    $spreadsheet->addSheet($empPaymentsSheet);
    
    $empPayments = EmployeePayment::whereHas('employee', function($query) use ($userId) {
        $query->where('shop_owner_id', $userId);
    })->with('employee')->get();
    
    $empPaymentsSheet->fromArray([
        ['ID', 'Employee Name', 'Amount', 'Payment Date', 'Created At']
    ]);
    
    $row = 2;
    foreach ($empPayments as $payment) {
        $empPaymentsSheet->fromArray([
            [$payment->id, $payment->employee->name ?? 'N/A', $payment->amount,
             $payment->payment_date, $payment->created_at->format('Y-m-d H:i:s')]
        ], null, 'A' . $row);
        $row++;
    }
    
    // 9. Batches Sheet
    $batchesSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Batches');
    $spreadsheet->addSheet($batchesSheet);
    
    $batches = Batch::where('user_id', $userId)->with('product')->get();
    $batchesSheet->fromArray([
        ['ID', 'Product Name', 'Quantity', 'Cost Price', 'Created At']
    ]);
    
    $row = 2;
    foreach ($batches as $batch) {
        $batchesSheet->fromArray([
            [$batch->id, $batch->product->name ?? 'N/A', $batch->quantity,
             $batch->cost_price, $batch->created_at->format('Y-m-d H:i:s')]
        ], null, 'A' . $row);
        $row++;
    }
    
    // 10. Tags Sheet
    $tagsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Tags');
    $spreadsheet->addSheet($tagsSheet);
    
    $tags = Tag::where('user_id', $userId)->get();
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

    $fileName = 'database_export_' . auth()->user()->name . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
    
    return new StreamedResponse(function() use ($spreadsheet) {
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }, 200, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        'Cache-Control' => 'max-age=0',
    ]);
}
}