<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    private const VISIBILITY_KEYS = [
        'show_bills_total_sales',
        'show_bills_total_profit',
        'show_bills_count',
        'show_bill_total_value',
        'show_bill_profit_column',
        'show_dashboard_total_sales',
        'show_product_cost_price',
    ];

    public function index(Request $request): View
    {
        $owner = $this->requireOwner();
        $search = trim((string) $request->string('q'));
        $status = (string) $request->string('status', 'all');
        $sort = (string) $request->string('sort', 'latest');

        $baseQuery = User::query()
            ->where('role', 'employee')
            ->where('shop_owner_id', $owner->id);

        $lastActivitySubquery = DB::table('sessions')
            ->selectRaw('user_id, MAX(last_activity) as max_last_activity')
            ->groupBy('user_id');

        $employeesQuery = User::query()
            ->where('users.role', 'employee')
            ->where('users.shop_owner_id', $owner->id)
            ->leftJoinSub($lastActivitySubquery, 'session_stats', function ($join) {
                $join->on('session_stats.user_id', '=', 'users.id');
            })
            ->select('users.*', 'session_stats.max_last_activity as session_last_activity');

        if ($search !== '') {
            $employeesQuery->where(function ($query) use ($search) {
                $query
                    ->where('users.name', 'like', '%' . $search . '%')
                    ->orWhere('users.email', 'like', '%' . $search . '%')
                    ->orWhere('users.phone_number', 'like', '%' . $search . '%');
            });
        }

        if ($status === 'active') {
            $employeesQuery->where(function ($query) {
                $query->whereNull('users.is_active')->orWhere('users.is_active', true);
            });
        } elseif ($status === 'suspended') {
            $employeesQuery->where('users.is_active', false);
        }

        match ($sort) {
            'name' => $employeesQuery->orderBy('users.name'),
            'oldest' => $employeesQuery->orderBy('users.created_at'),
            'activity' => $employeesQuery->orderByDesc('session_stats.max_last_activity')->orderBy('users.name'),
            default => $employeesQuery->orderByDesc('users.created_at'),
        };

        $employees = $employeesQuery->paginate(25)->withQueryString();

        $currentSessionActivity = DB::table('sessions')
            ->whereIn('id', array_values(array_filter($employees->pluck('session_id')->all())))
            ->pluck('last_activity', 'id');

        $presetNames = collect(PermissionCatalog::presets())
            ->mapWithKeys(fn (array $permissions, string $key) => [
                json_encode(PermissionCatalog::normalize($permissions)) => __('permissions.presets.' . $key),
            ]);

        $employees->getCollection()->transform(function (User $employee) use ($currentSessionActivity, $presetNames) {
            $normalizedPermissions = PermissionCatalog::normalize($employee->getPermissions());
            $employee->resolved_last_activity = $employee->session_id && $currentSessionActivity->has($employee->session_id)
                ? $currentSessionActivity->get($employee->session_id)
                : $employee->session_last_activity;
            $employee->has_active_session = $employee->session_id && $currentSessionActivity->has($employee->session_id);
            $employee->permission_labels = collect($normalizedPermissions)
                ->map(fn (string $permission) => __('permissions.keys.' . $permission))
                ->values();
            $employee->permission_count = count($normalizedPermissions);
            $employee->detected_role_label = $presetNames->get(
                json_encode($normalizedPermissions),
                __('team.roles.custom')
            );

            return $employee;
        });

        $totalCount = (clone $baseQuery)->count();
        $activeCount = (clone $baseQuery)
            ->where(function ($query) {
                $query->whereNull('is_active')->orWhere('is_active', true);
            })
            ->count();
        $suspendedCount = (clone $baseQuery)->where('is_active', false)->count();

        return view('shopowner.team.index', [
            'employees' => $employees,
            'filters' => compact('search', 'status', 'sort'),
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'suspendedCount' => $suspendedCount,
        ]);
    }

    public function create(): View
    {
        $this->requireOwner();
        abort(403, __('team.admin_provisioning'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireOwner();
        abort(403, __('team.admin_provisioning'));
    }

    public function edit(User $user): View
    {
        $owner = $this->requireOwner();
        $employee = $this->findEmployee($owner, $user->id);

        return view('shopowner.team.edit', $this->formViewData($employee));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $owner = $this->requireOwner();
        $employee = $this->findEmployee($owner, $user->id);

        $validated = $request->validate($this->rules($employee));
        $wasActive = $employee->is_active !== false;

        $employee->name = $validated['name'];
        $employee->email = $validated['email'];
        $employee->phone_number = $validated['phone_number'] ?? null;
        $employee->permissions = PermissionCatalog::normalize($validated['permissions'] ?? []);
        $employee->visibility_settings = $this->buildVisibilitySettings($request, $employee->visibility_settings ?? []);
        $employee->is_active = $request->boolean('is_active', true);
        $passwordChanged = ! empty($validated['password']);

        if ($passwordChanged) {
            $employee->password = $validated['password'];
        }

        $employee->save();

        $isActive = $employee->is_active !== false;
        if ($passwordChanged) {
            $this->revokeCredentials($employee);
        }

        if ($wasActive && ! $isActive) {
            $this->revokeCredentials($employee);
            $this->logAction('suspended', $employee);
        } elseif (! $wasActive && $isActive) {
            $this->logAction('reactivated', $employee);
        }

        return back()->with('success', __('team.messages.updated'));
    }

    public function destroy(User $user): RedirectResponse
    {
        $owner = $this->requireOwner();
        $employee = $this->findEmployee($owner, $user->id);

        $this->revokeCredentials($employee);
        $employee->delete();

        return redirect()
            ->route('shopowner.team.index')
            ->with('success', __('team.messages.deleted'));
    }

    public function toggle(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $owner = $this->requireOwner();
        $employee = $this->findEmployee($owner, $user->id);

        $employee->is_active = $employee->is_active === false;
        $employee->save();

        if ($employee->is_active === false) {
            $this->revokeCredentials($employee);
            $this->logAction('suspended', $employee);
            $message = __('team.messages.suspended');
        } else {
            $this->logAction('reactivated', $employee);
            $message = __('team.messages.reactivated');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'is_active' => $employee->is_active !== false,
            ]);
        }

        return back()->with('success', $message);
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $owner = $this->requireOwner();
        $employee = $this->findEmployee($owner, $user->id);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $employee->password = $validated['password'];
        $employee->save();

        $this->revokeCredentials($employee);
        $this->logAction('password_reset', $employee);

        return back()->with('success', __('team.messages.password_reset'));
    }

    public function logout(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $owner = $this->requireOwner();
        $employee = $this->findEmployee($owner, $user->id);

        $this->revokeCredentials($employee);
        $this->logAction('logged_out', $employee);

        if ($request->expectsJson()) {
            return response()->json(['message' => __('team.messages.logged_out')]);
        }

        return back()->with('success', __('team.messages.logged_out'));
    }

    public function copyPermissions(Request $request, User $user): RedirectResponse
    {
        $owner = $this->requireOwner();
        $employee = $this->findEmployee($owner, $user->id);

        $validated = $request->validate([
            'source_user_id' => ['required', 'integer'],
        ]);

        $source = $this->findEmployee($owner, (int) $validated['source_user_id']);

        $employee->permissions = PermissionCatalog::normalize($source->getPermissions());
        $employee->save();

        return back()->with('success', __('team.messages.permissions_copied'));
    }

    private function rules(?User $employee = null): array
    {
        $passwordRules = $employee
            ? ['nullable', 'string', 'min:8', 'max:255', 'confirmed']
            : ['required', 'string', 'min:8', 'max:255', 'confirmed'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee?->id),
            ],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'password' => $passwordRules,
            'permissions' => ['nullable', 'array'],
            'permissions.*' => array_merge(['bail'], explode('|', PermissionCatalog::validationRule())),
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function formViewData(?User $employee): array
    {
        $owner = $this->requireOwner();
        $activityLogs = collect();

        if ($employee && Schema::hasTable('activity_logs')) {
            $activityLogs = ActivityLog::query()
                ->where('owner_id', $owner->id)
                ->where('actor_id', $employee->id)
                ->latest('created_at')
                ->limit(20)
                ->get();
        }

        return [
            'employee' => $employee,
            'visibilityKeys' => self::VISIBILITY_KEYS,
            'teammates' => $employee
                ? User::query()
                    ->where('role', 'employee')
                    ->where('shop_owner_id', $owner->id)
                    ->where('id', '!=', $employee->id)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email'])
                : collect(),
            'activityLogs' => $activityLogs,
        ];
    }

    private function buildVisibilitySettings(Request $request, array $existing = []): array
    {
        $preserved = array_diff_key($existing, array_flip(self::VISIBILITY_KEYS));
        $settings = [];

        foreach (self::VISIBILITY_KEYS as $key) {
            $settings[$key] = $request->boolean($key, false);
        }

        return array_merge($preserved, $settings);
    }

    private function requireOwner(): User
    {
        $user = $this->user();

        abort_unless($user->isOwnerAccount(), 403);

        return $user;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function findEmployee(User $owner, int $userId): User
    {
        return User::query()
            ->where('role', 'employee')
            ->where('shop_owner_id', $owner->id)
            ->findOrFail($userId);
    }

    private function revokeCredentials(User $employee): void
    {
        $employee->revokeRememberedAccess();
    }

    private function logAction(string $action, User $employee): void
    {
        ActivityLogger::record(
            $action,
            'team_account',
            $employee,
            ['employee_name' => $employee->name],
            null,
            $employee->name,
            $employee->shop_owner_id
        );
    }
}
