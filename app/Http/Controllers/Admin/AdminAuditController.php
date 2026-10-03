<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class AdminAuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::query()
            ->where('actor_role', 'admin')
            ->when($request->string('action')->value(), fn ($query, $action) => $query->where('action', $action))
            ->when($request->integer('shop'), fn ($query, $shop) => $query->where('owner_id', $shop))
            ->when($request->date('from'), fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date('to'), fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        $shops = User::query()->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']))->orderBy('name')->get(['id', 'name']);
        $actions = ActivityLog::query()->where('actor_role', 'admin')->distinct()->orderBy('action')->pluck('action');

        return view('admin.audit.index', compact('logs', 'shops', 'actions'));
    }
}
