<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\DayClosing;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Finance\FinanceInsightsService;
use App\Support\ShopTime;
use Illuminate\Http\Request;

class DayCloseController extends Controller
{
    public function __construct(private readonly FinanceInsightsService $insights) {}

    public function index(Request $request)
    {
        $ownerId = $this->ownerId();
        $request->validate(['date' => 'nullable|date']);
        $date = $request->string('date')->toString() ?: ShopTime::today($ownerId);

        if (! $this->canSeeFinancials()) {
            return view('finance.day-close.index', [
                'date' => $date,
                'blind' => true,
                'summary' => null,
                'closings' => DayClosing::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->where('closed_by', auth()->id())
                    ->latest('closing_date')
                    ->paginate(15)
                    ->withQueryString(),
                'existingForDate' => DayClosing::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->whereDate('closing_date', $date)
                    ->first(),
            ]);
        }

        return view('finance.day-close.index', [
            'date' => $date,
            'blind' => false,
            'summary' => $this->insights->dayCloseSummary($ownerId, $date),
            'closings' => DayClosing::withoutGlobalScopes()
                ->with('closer:id,name')
                ->where('user_id', $ownerId)
                ->latest('closing_date')
                ->paginate(15)
                ->withQueryString(),
            'existingForDate' => null,
        ]);
    }

    public function store(Request $request)
    {
        $ownerId = $this->ownerId();
        $blind = ! $this->canSeeFinancials();
        $request->validate([
            'closing_date' => 'required|date' . ($blind ? '|before_or_equal:' . ShopTime::today($ownerId) : ''),
            'counted_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:4000',
        ]);

        $summary = $this->insights->dayCloseSummary(
            $ownerId,
            $request->string('closing_date')->toString(),
        );

        $record = DayClosing::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('closing_date', $request->string('closing_date')->toString())
            ->first();

        // A blind counter may correct their own count but never overwrite one saved by someone else.
        if ($blind && $record && (int) $record->closed_by !== (int) auth()->id()) {
            return back()->withInput()->withErrors([
                'closing_date' => __('finance.day_close.already_closed_by_other'),
            ]);
        }

        if (! $record) {
            $record = new DayClosing([
                'user_id' => $ownerId,
                'closing_date' => $request->string('closing_date')->toString(),
            ]);
        }

        $record->fill([
            'expected_cash' => $summary['expected_cash'] ?? 0,
            'counted_cash' => $request->input('counted_cash'),
            'variance' => round((float) $request->input('counted_cash') - (float) ($summary['expected_cash'] ?? 0), 2),
            'notes' => $request->string('notes')->toString() ?: null,
            'snapshot' => $summary,
            'closed_by' => auth()->id(),
        ]);
        $wasExisting = $record->exists;
        $record->save();

        ActivityLogger::record(
            $wasExisting ? 'updated' : 'created',
            'day_closing',
            $record,
            ['closing_date' => $record->closing_date?->toDateString()],
            (float) $record->variance,
            $record->closing_date?->toDateString(),
            $ownerId,
        );

        return redirect()->route('finance.day-close.index', ['date' => $record->closing_date?->toDateString()])
            ->with('success', __($blind ? 'finance.day_close.blind_saved' : 'finance.day_close.saved_successfully'));
    }

    public function print(Request $request)
    {
        $ownerId = $this->ownerId();
        $date = $request->string('date')->toString() ?: ShopTime::today($ownerId);
        $summary = $this->insights->dayCloseSummary($ownerId, $date);
        $closing = DayClosing::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('closing_date', $date)
            ->first();

        return view('finance.day-close.print', compact('summary', 'closing', 'date'));
    }

    private function ownerId(): int
    {
        return (int) auth()->user()->ownerId();
    }

    private function canSeeFinancials(): bool
    {
        $user = auth()->user();

        return $user->role !== 'employee' || $user->hasPermission('view_financial');
    }
}
