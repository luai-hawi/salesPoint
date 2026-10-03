<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Supplier;
use App\Services\Finance\FinanceInsightsService;
use App\Support\ExportSanitizer;
use App\Support\ShopTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(private readonly FinanceInsightsService $insights) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $this->authorizePermission('view_expenses');
        $ownerId = (int) $user->ownerId();

        $query = Expense::withoutGlobalScopes()->where('user_id', $ownerId);

        if ($request->filled('from') || $request->filled('to')) {
            $fromDate = $request->string('from')->toString() ?: ShopTime::today($ownerId);
            $toDate = $request->string('to')->toString() ?: ($request->string('from')->toString() ?: ShopTime::today($ownerId));
            $query->whereDate('expense_date', '>=', $fromDate)
                ->whereDate('expense_date', '<=', $toDate);
        }

        if ($request->filled('category')) {
            if ($request->string('category')->toString() === 'uncategorised') {
                $query->whereNull('category');
            } else {
                $query->where('category', $request->string('category')->toString());
            }
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', $search)->orWhere('notes', 'like', $search);
            });
        }

        $expenses = $query->latest('expense_date')->latest('id')->paginate(25)->withQueryString();
        $monthExpression = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', expense_date)"
            : "DATE_FORMAT(expense_date, '%Y-%m')";

        $monthlyTotals = Expense::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->selectRaw("{$monthExpression} as month_key, COALESCE(category, '') as category, SUM(amount) as total")
            ->groupByRaw("{$monthExpression}, COALESCE(category, '')")
            ->orderByDesc('month_key')
            ->get();

        $supplierNames = Supplier::withoutGlobalScopes()->where('user_id', $ownerId)->orderBy('name')->pluck('name')->filter()->values();
        $recentSupplierPaymentAmounts = \App\Models\SupplierPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('amount', '>', 0)
            ->latest('payment_date')
            ->limit(20)
            ->pluck('amount')
            ->map(fn ($amount) => round((float) $amount, 2))
            ->values();
        $outstandingSupplierBalances = Supplier::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->limit(20)
            ->pluck('balance')
            ->map(fn ($amount) => round((float) $amount, 2))
            ->values();

        return view('shopowner.expenses.index', [
            'expenses' => $expenses,
            'monthlyTotals' => $monthlyTotals,
            'editExpense' => $request->filled('edit')
                ? Expense::withoutGlobalScopes()->where('user_id', $ownerId)->findOrFail($request->integer('edit'))
                : null,
            'supplierNames' => $supplierNames,
            'recentSupplierPaymentAmounts' => $recentSupplierPaymentAmounts,
            'outstandingSupplierBalances' => $outstandingSupplierBalances,
            'health' => $this->insights->dataHealth($ownerId),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('create_expenses');
        $expense = Expense::create($this->validatedData($request) + ['user_id' => $this->ownerId()]);

        return redirect()->route('shopowner.expenses.index')
            ->with('success', __('finance.expenses.saved_successfully', ['title' => $expense->title]));
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorizePermission('edit_expenses');
        $this->authorizeOwner($expense);
        $expense->update($this->validatedData($request));

        return redirect()->route('shopowner.expenses.index')
            ->with('success', __('finance.expenses.updated_successfully', ['title' => $expense->title]));
    }

    public function destroy(Expense $expense)
    {
        $this->authorizePermission('delete_expenses');
        $this->authorizeOwner($expense);
        $title = $expense->title;
        $expense->delete();

        return redirect()->route('shopowner.expenses.index')
            ->with('success', __('finance.expenses.deleted_successfully', ['title' => $title]));
    }

    public function export(Request $request)
    {
        $this->authorizePermission('view_expenses');
        $request->merge(['page' => null]);
        $query = $this->indexQuery($request)->limit(5000);

        return new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                __('finance.common.date'),
                __('finance.common.category'),
                __('finance.common.title'),
                __('finance.common.amount'),
                __('finance.common.notes'),
            ]);

            foreach ($query->cursor() as $row) {
                fputcsv($handle, [
                    ExportSanitizer::csvValue(optional($row->expense_date)->format('Y-m-d')),
                    ExportSanitizer::csvValue($row->category ?: __('finance.expenses.uncategorised')),
                    ExportSanitizer::csvValue($row->title),
                    $row->amount,
                    ExportSanitizer::csvValue($row->notes),
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="expenses.csv"',
        ]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:50',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);
    }

    private function indexQuery(Request $request)
    {
        $ownerId = $this->ownerId();
        $query = Expense::withoutGlobalScopes()->where('user_id', $ownerId);
        if ($request->filled('from') || $request->filled('to')) {
            $fromDate = $request->string('from')->toString() ?: ShopTime::today($ownerId);
            $toDate = $request->string('to')->toString() ?: ($request->string('from')->toString() ?: ShopTime::today($ownerId));
            $query->whereDate('expense_date', '>=', $fromDate)
                ->whereDate('expense_date', '<=', $toDate);
        }
        if ($request->filled('category')) {
            if ($request->string('category')->toString() === 'uncategorised') {
                $query->whereNull('category');
            } else {
                $query->where('category', $request->string('category')->toString());
            }
        }
        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', $search)->orWhere('notes', 'like', $search);
            });
        }

        return $query->latest('expense_date')->latest('id');
    }

    private function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission($permission)) {
            abort(403);
        }
    }

    private function authorizeOwner(Expense $expense): void
    {
        abort_unless((int) $expense->user_id === $this->ownerId(), 403);
    }

    private function ownerId(): int
    {
        return (int) auth()->user()->ownerId();
    }
}
