<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\Restaurant\RestaurantInsightsService;
use App\Services\Restaurant\RestaurantSupport;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InsightsController extends Controller
{
    public function __construct(private readonly RestaurantInsightsService $insights)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (! RestaurantSupport::canViewInsights($user)) {
            abort(403);
        }

        $ownerId = RestaurantSupport::ownerId($user);
        $localToday = ShopTime::today($ownerId);
        $from = $request->input('from', Carbon::parse($localToday, ShopTime::timezone($ownerId))->subDays(6)->toDateString());
        $to = $request->input('to', $localToday);
        $insights = $this->insights->build($user, $from, $to);

        return view('restaurant.insights.index', $insights);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (! RestaurantSupport::canViewInsights($user)) {
            abort(403);
        }

        $ownerId = RestaurantSupport::ownerId($user);
        $localToday = ShopTime::today($ownerId);
        $from = $request->input('from', Carbon::parse($localToday, ShopTime::timezone($ownerId))->subDays(6)->toDateString());
        $to = $request->input('to', $localToday);
        $insights = $this->insights->build($user, $from, $to);

        $headers = [
            __('restaurant.insights.section'),
            __('restaurant.insights.label'),
            __('restaurant.insights.value'),
        ];

        return response()->streamDownload(function () use ($headers, $insights) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            foreach ($insights['salesByType'] as $row) {
                fputcsv($out, [__('restaurant.insights.sales_by_type'), __('restaurant.order_types.' . $row->order_type), $row->total_sales]);
            }

            foreach ($insights['salesByTable'] as $row) {
                fputcsv($out, [__('restaurant.insights.sales_by_table'), $row->label, $row->total_sales]);
            }

            foreach ($insights['salesByHour'] as $row) {
                fputcsv($out, [__('restaurant.insights.sales_by_hour'), $row->label, $row->total_sales]);
            }

            foreach ($insights['salesByWeekday'] as $row) {
                fputcsv($out, [__('restaurant.insights.sales_by_weekday'), $row->label, $row->total_sales]);
            }

            fclose($out);
        }, 'restaurant-insights.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
