<?php
namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Expense;
use App\Models\Employee;
use App\Models\CustomerPayment;
use App\Models\Product;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->input('from', now()->subMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        // Financial Metrics
        $totalRevenue = Bill::whereBetween('created_at', [$from, $to])->sum('total_price');
        $totalExpenses = Expense::whereBetween('expense_date', [$from, $to])->sum('amount');
        $profit = $totalRevenue - $totalExpenses;

        // Damaged Goods
        $damagedBills = Bill::where('is_damaged', true)
                            ->whereBetween('bills.created_at', [$from, $to])
                            ->with('products')
                            ->get();
        $damagedQuantity = $damagedBills->sum(fn($bill) => $bill->products->sum('pivot.quantity'));
        $damagedValue = $damagedBills->sum(fn($bill) => $bill->products->sum(fn($product) => $product->pivot->quantity * $product->pivot->cost_price));

        // Employee Payments
        $employees = Employee::all();
        foreach ($employees as $employee) {
            $employee->paidThisMonth = $employee->payments()
                                                ->whereBetween('payment_date', [$from, $to])
                                                ->sum('amount');
            $employee->remainingThisMonth = $employee->monthly_salary - $employee->paidThisMonth;
        }

        // Customer Payments
        $customerPayments = CustomerPayment::whereBetween('created_at', [$from, $to])->get();
        $totalCustomerPayments = $customerPayments->sum('amount');
        $customerBalances = $customerPayments->groupBy('customer_id')->map(fn($payments) => $payments->sum('amount'));

        // Product Performance
        $products = Product::with(['bills' => function ($query) use ($from, $to) {
            $query->whereBetween('bills.created_at', [$from, $to]);
        }])->get();

        foreach ($products as $product) {
            $product->soldQuantity = $product->bills->sum('pivot.quantity');
            $product->totalRevenue = $product->bills->sum(fn($bill) => $bill->pivot->quantity * $bill->pivot->selling_price);
            $product->totalCost = $product->bills->sum(fn($bill) => $bill->pivot->quantity * $bill->pivot->cost_price);
            $product->profit = $product->totalRevenue - $product->totalCost;
        }

        return view('shopowner.dashboard.index', compact(
            'totalRevenue', 'totalExpenses', 'profit',
            'damagedQuantity', 'damagedValue',
            'employees', 'totalCustomerPayments', 'customerBalances',
            'products'
        ));
    }
}
