<?php

namespace App\Http\Controllers;

use App\Models\CapitalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CapitalController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'entry_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();
        $userId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $entry = CapitalEntry::create([
            'amount' => $request->amount,
            'entry_date' => $request->entry_date,
            'note' => $request->note,
            'user_id' => $userId,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('messages.Capital added successfully'),
                'entry' => $entry,
            ]);
        }

        return redirect()->route('dashboard.financial')->with('success', __('messages.Capital added successfully'));
    }

    public function destroy(Request $request, $id)
    {
        $user = auth()->user();
        $userId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $entry = CapitalEntry::where('user_id', $userId)->findOrFail($id);
        $entry->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('messages.Capital entry deleted successfully'),
            ]);
        }

        return redirect()->route('dashboard.financial')->with('success', __('messages.Capital entry deleted successfully'));
    }
}
