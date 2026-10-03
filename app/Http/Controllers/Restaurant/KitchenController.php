<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\KitchenTicket;
use App\Services\ActivityLogger;
use App\Services\Restaurant\KitchenDisplayService;
use App\Services\Restaurant\RestaurantSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    public function __construct(
        private readonly KitchenDisplayService $displayService,
    ) {
    }

    public function display(Request $request)
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        RestaurantSupport::ensurePermission($user, 'view_kitchen');

        return view('kitchen.display', [
            'pollSeconds' => 4,
            'thresholds' => [
                'green' => 8,
                'amber' => 15,
                'red' => 25,
            ],
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        RestaurantSupport::ensurePermission($user, 'view_kitchen');

        $ownerId = RestaurantSupport::ownerId($user);
        $feed = $this->displayService->feed($ownerId, $request->query('since'));
        $ifNoneMatch = trim((string) $request->header('If-None-Match'), '"');

        if ($ifNoneMatch !== '' && hash_equals($feed['etag'], $ifNoneMatch)) {
            return response()->json([], 304, ['ETag' => '"' . $feed['etag'] . '"']);
        }

        return response()->json($feed, 200, ['ETag' => '"' . $feed['etag'] . '"']);
    }

    public function transition(Request $request, KitchenTicket $ticket): JsonResponse
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        RestaurantSupport::ensurePermission($user, 'view_kitchen');

        if ((int) $ticket->user_id !== RestaurantSupport::ownerId($user)) {
            abort(403);
        }

        $data = $request->validate([
            'action' => 'required|string|in:start,ready,served,recall,rush,cancel',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($data['action'] === 'cancel' && empty($data['reason'])) {
            return response()->json([
                'message' => __('restaurant.validation.cancel_reason_required'),
                'errors' => ['reason' => [__('restaurant.validation.cancel_reason_required')]],
            ], 422);
        }

        $ticket = $this->displayService->transition($ticket, $data['action'], $data['reason'] ?? null);

        ActivityLogger::record(
            'updated',
            'kitchen_order',
            $ticket->order,
            ['ticket_id' => $ticket->id, 'ticket_action' => $data['action']],
            (float) $ticket->order?->total,
            $ticket->order?->label,
            $ticket->user_id,
        );

        return response()->json([
            'message' => __('restaurant.messages.ticket_updated'),
            'ticket' => $ticket,
        ]);
    }

    public function print(KitchenTicket $ticket, Request $request)
    {
        $user = $request->user();
        RestaurantSupport::ensureRestaurantAccount($user);
        RestaurantSupport::ensurePermission($user, 'view_kitchen');

        if ((int) $ticket->user_id !== RestaurantSupport::ownerId($user)) {
            abort(403);
        }

        return view('kitchen.print', ['ticket' => $ticket->load('order.table')]);
    }
}
