<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Bill;
use App\Models\User;
use App\Services\Finance\FinanceInsightsService;
use App\Support\ShopTime;
use Illuminate\Http\Request;

class TeamSummaryController extends Controller
{
    public function __construct(private readonly FinanceInsightsService $insights) {}

    public function index(Request $request)
    {
        $ownerId = $this->ownerId();
        $fromDate = $request->string('from')->toString() ?: ShopTime::today($ownerId);
        $toDate = $request->string('to')->toString() ?: $fromDate;
        $drillUser = $request->filled('user_id')
            ? $this->shopUser($ownerId, $request->integer('user_id'))
            : null;

        [$startUtc, $endUtc] = ShopTime::utcRange($fromDate, $toDate, $ownerId);

        return view('finance.team-summary.index', [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'summary' => $this->insights->teamSummary($ownerId, $fromDate, $toDate),
            'drillUser' => $drillUser,
            'drillBills' => $drillUser
                ? Bill::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->where('created_by', $drillUser->id)
                    ->whereBetween('created_at', [$startUtc, $endUtc])
                    ->latest()
                    ->limit(20)
                    ->get()
                : collect(),
            'drillActivity' => $drillUser
                ? ActivityLog::query()
                    ->forOwner($ownerId)
                    ->where('actor_id', $drillUser->id)
                    ->whereBetween('created_at', [$startUtc, $endUtc])
                    ->latest()
                    ->limit(20)
                    ->get()
                : collect(),
        ]);
    }

    private function ownerId(): int
    {
        return (int) auth()->user()->ownerId();
    }

    private function shopUser(int $ownerId, int $userId): User
    {
        return User::withoutGlobalScopes()
            ->where(function ($query) use ($ownerId) {
                $query->whereKey($ownerId)
                    ->orWhere(function ($inner) use ($ownerId) {
                        $inner->where('role', 'employee')->where('shop_owner_id', $ownerId);
                    });
            })
            ->findOrFail($userId);
    }
}
