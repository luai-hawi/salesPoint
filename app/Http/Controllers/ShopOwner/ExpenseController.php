<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('view_expenses')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $query = Expense::where('user_id', $ownerId);

        // 🔍 Search by date range
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('expense_date', [$request->from . ' 00:00:00', $request->to . ' 23:59:59']);
        }

        $expenses = $query->latest('expense_date')->paginate(10);

        return view('shopowner.expenses.index', compact('expenses'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('create_expenses')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        Expense::create([
            'title' => $request->title,
            'amount' => $request->amount,
            'expense_date' => $request->expense_date,
            'notes' => $request->notes,
            'user_id' => $ownerId, // assign owner ID
        ]);

        return back()->with('success', 'Expense recorded successfully.');
    }

    public function destroy(Expense $expense)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('delete_expenses')) {
            abort(403, 'Unauthorized');
        }

        $this->authorizeOwner($expense);

        $expense->delete();
        return back()->with('success', 'Expense deleted successfully.');
    }

    private function authorizeOwner(Expense $expense)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        if ($expense->user_id !== $ownerId) {
            abort(403, 'Unauthorized action.');
        }
    }
}
