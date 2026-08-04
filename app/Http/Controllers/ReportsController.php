<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    private function getOwnerId(): int
    {
        $user = auth()->user();
        return $user->role === 'employee' ? (int) $user->shop_owner_id : (int) $user->id;
    }

    public function index()
    {
        $ownerId = $this->getOwnerId();

        $customers    = Customer::where('user_id', $ownerId)->orderBy('name')->get(['id', 'name', 'balance']);
        $suppliers    = Supplier::where('user_id', $ownerId)->orderBy('name')->get(['id', 'name', 'balance']);
        $employees    = Employee::where('shop_owner_id', $ownerId)->orderBy('name')->get(['id', 'name', 'job_title']);
        $employeeUsers = User::where('role', 'employee')
            ->where('shop_owner_id', $ownerId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('reports.index', compact('customers', 'suppliers', 'employees', 'employeeUsers'));
    }

    public function generate(Request $request)
    {
        $ownerId = $this->getOwnerId();
        $type    = (string) $request->get('type', '');
        $from    = $this->sanitizeDate($request->get('from'));
        $to      = $this->sanitizeDate($request->get('to'));

        if ($from && $to && $from > $to) {
            return response()->json(['success' => false, 'message' => __('messages.Invalid date range')], 422);
        }

        return match ($type) {
            'customer_payments'      => $this->customerPaymentsReport($ownerId, $request, $from, $to),
            'customer_bills'         => $this->customerBillsReport($ownerId, $request, $from, $to),
            'customer_statement'     => $this->customerStatementReport($ownerId, $request, $from, $to),
            'supplier_payments'      => $this->supplierPaymentsReport($ownerId, $request, $from, $to),
            'supplier_purchase_bills'=> $this->supplierPurchaseBillsReport($ownerId, $request, $from, $to),
            'employee_payments'      => $this->employeePaymentsReport($ownerId, $request, $from, $to),
            'employee_work'          => $this->employeeWorkReport($ownerId, $request, $from, $to),
            'sale_bills'             => $this->saleBillsReport($ownerId, $request, $from, $to),
            'all_purchase_bills'     => $this->allPurchaseBillsReport($ownerId, $request, $from, $to),
            'expenses'               => $this->expensesReport($ownerId, $request, $from, $to),
            'customer_balances'      => $this->customerBalancesReport($ownerId),
            'supplier_balances'      => $this->supplierBalancesReport($ownerId),
            default                  => response()->json(['success' => false, 'message' => __('messages.Invalid report type')], 422),
        };
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function sanitizeDate(mixed $value): ?string
    {
        if (!$value || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
            return null;
        }
        return (string) $value;
    }

    // ── Reports ──────────────────────────────────────────────────────────────

    private function customerPaymentsReport(int $ownerId, Request $request, ?string $from, ?string $to)
    {
        $customerId = (int) $request->get('customer_id', 0);

        $q = DB::table('customer_payments as cp')
            ->join('customers as c', 'c.id', '=', 'cp.customer_id')
            ->where('cp.user_id', $ownerId)
            ->select('cp.id', 'cp.amount', 'cp.type', 'cp.note', 'cp.created_at',
                     'c.name as customer_name', 'c.phone');

        if ($customerId > 0) $q->where('cp.customer_id', $customerId);
        if ($from) $q->whereDate('cp.created_at', '>=', $from);
        if ($to)   $q->whereDate('cp.created_at', '<=', $to);

        $rows = $q->orderByDesc('cp.created_at')->limit(1000)->get();

        return response()->json([
            'success' => true,
            'rows'    => $rows,
            'summary' => ['total' => $rows->sum('amount'), 'count' => $rows->count()],
        ]);
    }

    private function customerBillsReport(int $ownerId, Request $request, ?string $from, ?string $to)
    {
        $customerId = (int) $request->get('customer_id', 0);

        $q = DB::table('bills as b')
            ->leftJoin('customers as c', 'c.id', '=', 'b.customer_id')
            ->where('b.user_id', $ownerId)
            ->select(
                'b.id', 'b.total_price', 'b.note', 'b.is_damaged', 'b.is_returned', 'b.created_at',
                'c.name as customer_name', 'c.phone',
                DB::raw('(SELECT COALESCE(SUM((bp.selling_price - COALESCE(bp.cost_price,0)) * bp.quantity),0)
                          FROM bill_product bp WHERE bp.bill_id = b.id) as profit')
            );

        if ($customerId > 0) $q->where('b.customer_id', $customerId);
        if ($from) $q->whereDate('b.created_at', '>=', $from);
        if ($to)   $q->whereDate('b.created_at', '<=', $to);

        $rows = $q->orderByDesc('b.created_at')->limit(1000)->get();

        return response()->json([
            'success' => true,
            'rows'    => $rows,
            'summary' => [
                'total'  => $rows->sum('total_price'),
                'profit' => $rows->sum('profit'),
                'count'  => $rows->count(),
            ],
        ]);
    }

    private function customerStatementReport(int $ownerId, Request $request, ?string $from, ?string $to)
    {
        $customerId = (int) $request->get('customer_id', 0);

        $q = DB::table('bills as b')
            ->leftJoin('customers as c', 'c.id', '=', 'b.customer_id')
            ->where('b.user_id', $ownerId)
            ->select(
                'b.id', 'b.total_price', 'b.note', 'b.is_damaged', 'b.is_returned', 'b.created_at',
                'c.name as customer_name', 'c.phone', 'c.balance'
            );

        if ($customerId > 0) $q->where('b.customer_id', $customerId);
        if ($from) $q->whereDate('b.created_at', '>=', $from);
        if ($to)   $q->whereDate('b.created_at', '<=', $to);

        $rows = $q->orderBy('b.created_at')->limit(1000)->get();

        // Fetch customer info separately for the statement header
        $customer = $customerId > 0
            ? DB::table('customers')->where('id', $customerId)->where('user_id', $ownerId)
                  ->select('name', 'phone', 'balance')->first()
            : null;

        return response()->json([
            'success'  => true,
            'rows'     => $rows,
            'meta'     => $customer,
            'summary'  => [
                'total' => $rows->sum('total_price'),
                'count' => $rows->count(),
            ],
        ]);
    }

    private function supplierPaymentsReport(int $ownerId, Request $request, ?string $from, ?string $to)
    {
        $supplierId = (int) $request->get('supplier_id', 0);

        $q = DB::table('supplier_payments as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->where('sp.user_id', $ownerId)
            ->select('sp.id', 'sp.amount', 'sp.type', 'sp.note', 'sp.payment_date', 'sp.created_at',
                     's.name as supplier_name', 's.phone');

        if ($supplierId > 0) $q->where('sp.supplier_id', $supplierId);
        if ($from) $q->whereDate('sp.payment_date', '>=', $from);
        if ($to)   $q->whereDate('sp.payment_date', '<=', $to);

        $rows = $q->orderByDesc('sp.payment_date')->limit(1000)->get();

        return response()->json([
            'success' => true,
            'rows'    => $rows,
            'summary' => ['total' => $rows->sum('amount'), 'count' => $rows->count()],
        ]);
    }

    private function supplierPurchaseBillsReport(int $ownerId, Request $request, ?string $from, ?string $to)
    {
        $supplierId = (int) $request->get('supplier_id', 0);

        $q = DB::table('purchase_bills as pb')
            ->leftJoin('suppliers as s', 's.id', '=', 'pb.supplier_id')
            ->where('pb.user_id', $ownerId)
            ->select('pb.id', 'pb.total_amount', 'pb.notes', 'pb.reference_number',
                     'pb.purchase_date', 's.name as supplier_name');

        if ($supplierId > 0) $q->where('pb.supplier_id', $supplierId);
        if ($from) $q->whereDate('pb.purchase_date', '>=', $from);
        if ($to)   $q->whereDate('pb.purchase_date', '<=', $to);

        $rows = $q->orderByDesc('pb.purchase_date')->limit(1000)->get();

        return response()->json([
            'success' => true,
            'rows'    => $rows,
            'summary' => ['total' => $rows->sum('total_amount'), 'count' => $rows->count()],
        ]);
    }

    private function employeePaymentsReport(int $ownerId, Request $request, ?string $from, ?string $to)
    {
        $employeeId = (int) $request->get('employee_id', 0);

        $q = DB::table('employee_payments as ep')
            ->join('employees as e', 'e.id', '=', 'ep.employee_id')
            ->where('e.shop_owner_id', $ownerId)
            ->select('ep.id', 'ep.amount', 'ep.type', 'ep.note', 'ep.payment_date',
                     'e.name as employee_name', 'e.job_title');

        if ($employeeId > 0) $q->where('ep.employee_id', $employeeId);
        if ($from) $q->whereDate('ep.payment_date', '>=', $from);
        if ($to)   $q->whereDate('ep.payment_date', '<=', $to);

        $rows = $q->orderByDesc('ep.payment_date')->limit(1000)->get();

        return response()->json([
            'success' => true,
            'rows'    => $rows,
            'summary' => ['total' => $rows->sum('amount'), 'count' => $rows->count()],
        ]);
    }

    private function employeeWorkReport(int $ownerId, Request $request, ?string $from, ?string $to)
    {
        $employeeUserId = (int) $request->get('employee_user_id', 0);

        $q = DB::table('bills as b')
            ->leftJoin('customers as c', 'c.id', '=', 'b.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'b.created_by')
            ->where('b.user_id', $ownerId)
            ->select(
                'b.id', 'b.total_price', 'b.note', 'b.is_damaged', 'b.is_returned', 'b.created_at',
                'c.name as customer_name', 'u.name as creator_name',
                DB::raw('(SELECT COALESCE(SUM((bp.selling_price - COALESCE(bp.cost_price,0)) * bp.quantity),0)
                          FROM bill_product bp WHERE bp.bill_id = b.id) as profit')
            );

        if ($employeeUserId > 0) {
            $q->where('b.created_by', $employeeUserId);
        } else {
            $ids = User::where('role', 'employee')->where('shop_owner_id', $ownerId)->pluck('id');
            $q->whereIn('b.created_by', $ids);
        }

        if ($from) $q->whereDate('b.created_at', '>=', $from);
        if ($to)   $q->whereDate('b.created_at', '<=', $to);

        $rows = $q->orderByDesc('b.created_at')->limit(1000)->get();

        return response()->json([
            'success' => true,
            'rows'    => $rows,
            'summary' => [
                'total'  => $rows->sum('total_price'),
                'profit' => $rows->sum('profit'),
                'count'  => $rows->count(),
            ],
        ]);
    }

    private function saleBillsReport(int $ownerId, Request $request, ?string $from, ?string $to)
    {
        $customerId = (int) $request->get('customer_id', 0);

        $q = DB::table('bills as b')
            ->leftJoin('customers as c', 'c.id', '=', 'b.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'b.created_by')
            ->where('b.user_id', $ownerId)
            ->select(
                'b.id', 'b.total_price', 'b.note', 'b.is_damaged', 'b.is_returned', 'b.created_at',
                'c.name as customer_name', 'u.name as creator_name',
                DB::raw('(SELECT COALESCE(SUM((bp.selling_price - COALESCE(bp.cost_price,0)) * bp.quantity),0)
                          FROM bill_product bp WHERE bp.bill_id = b.id) as profit')
            );

        if ($customerId > 0) $q->where('b.customer_id', $customerId);
        if ($from) $q->whereDate('b.created_at', '>=', $from);
        if ($to)   $q->whereDate('b.created_at', '<=', $to);

        $rows = $q->orderByDesc('b.created_at')->limit(1000)->get();

        return response()->json([
            'success' => true,
            'rows'    => $rows,
            'summary' => [
                'total'  => $rows->sum('total_price'),
                'profit' => $rows->sum('profit'),
                'count'  => $rows->count(),
            ],
        ]);
    }

    private function allPurchaseBillsReport(int $ownerId, Request $request, ?string $from, ?string $to)
    {
        $supplierId = (int) $request->get('supplier_id', 0);

        $q = DB::table('purchase_bills as pb')
            ->leftJoin('suppliers as s', 's.id', '=', 'pb.supplier_id')
            ->leftJoin('users as u', 'u.id', '=', 'pb.created_by')
            ->where('pb.user_id', $ownerId)
            ->select('pb.id', 'pb.total_amount', 'pb.notes', 'pb.reference_number',
                     'pb.purchase_date', 's.name as supplier_name', 'u.name as creator_name');

        if ($supplierId > 0) $q->where('pb.supplier_id', $supplierId);
        if ($from) $q->whereDate('pb.purchase_date', '>=', $from);
        if ($to)   $q->whereDate('pb.purchase_date', '<=', $to);

        $rows = $q->orderByDesc('pb.purchase_date')->limit(1000)->get();

        return response()->json([
            'success' => true,
            'rows'    => $rows,
            'summary' => ['total' => $rows->sum('total_amount'), 'count' => $rows->count()],
        ]);
    }

    private function expensesReport(int $ownerId, Request $request, ?string $from, ?string $to)
    {
        $q = DB::table('expenses')
            ->where('user_id', $ownerId)
            ->select('id', 'title', 'amount', 'expense_date', 'notes');

        if ($from) $q->whereDate('expense_date', '>=', $from);
        if ($to)   $q->whereDate('expense_date', '<=', $to);

        $rows = $q->orderByDesc('expense_date')->limit(1000)->get();

        return response()->json([
            'success' => true,
            'rows'    => $rows,
            'summary' => ['total' => $rows->sum('amount'), 'count' => $rows->count()],
        ]);
    }

    private function customerBalancesReport(int $ownerId)
    {
        $rows = DB::table('customers')
            ->where('user_id', $ownerId)
            ->select('id', 'name', 'phone', 'balance')
            ->orderByDesc('balance')
            ->limit(1000)
            ->get();

        return response()->json([
            'success' => true,
            'rows'    => $rows,
            'summary' => [
                'total_owing'  => $rows->where('balance', '>', 0)->sum('balance'),
                'total_credit' => abs($rows->where('balance', '<', 0)->sum('balance')),
                'count'        => $rows->count(),
            ],
        ]);
    }

    private function supplierBalancesReport(int $ownerId)
    {
        $rows = DB::table('suppliers')
            ->where('user_id', $ownerId)
            ->select('id', 'name', 'phone', 'email', 'balance')
            ->orderByDesc('balance')
            ->limit(1000)
            ->get();

        return response()->json([
            'success' => true,
            'rows'    => $rows,
            'summary' => [
                'total_owing'  => $rows->where('balance', '>', 0)->sum('balance'),
                'total_credit' => abs($rows->where('balance', '<', 0)->sum('balance')),
                'count'        => $rows->count(),
            ],
        ]);
    }
}
