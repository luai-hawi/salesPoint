<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Finance\FinanceInsightsService;
use App\Support\ExportSanitizer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function __construct(private readonly FinanceInsightsService $insights) {}

    public function index(Request $request)
    {
        $ownerId = $this->ownerId();
        $actors = User::withoutGlobalScopes()
            ->whereKey($ownerId)
            ->orWhere(function ($query) use ($ownerId) {
                $query->where('role', 'employee')->where('shop_owner_id', $ownerId);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('finance.activity.index', [
            'activities' => $this->insights->activityFeed($ownerId, $request),
            'actors' => $actors,
        ]);
    }

    public function export(Request $request)
    {
        $query = $this->insights->activityFeedQuery($this->ownerId(), $request)
            ->orderBy('activity_logs.id')
            ->limit(5000);

        return new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                __('finance.activity.when'),
                __('finance.activity.actor'),
                __('finance.activity.action'),
                __('finance.activity.amount'),
            ]);

            foreach ($query->cursor() as $row) {
                fputcsv($handle, [
                    ExportSanitizer::csvValue((string) $row->created_at),
                    ExportSanitizer::csvValue($row->actor_name),
                    ExportSanitizer::csvValue($row->describe()),
                    $row->amount,
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="activity-log.csv"',
        ]);
    }

    private function ownerId(): int
    {
        return (int) auth()->user()->ownerId();
    }
}
