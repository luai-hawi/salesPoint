<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminEmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()
            ->where('role', 'employee')
            ->with('shopOwner:id,name,role')
            ->when($request->string('search')->value(), function ($employees, $search) {
                $employees->where(function ($employee) use ($search) {
                    $employee->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone_number', 'like', '%' . $search . '%');
                });
            })
            ->when($request->integer('shop'), fn ($employees, $shop) => $employees->where('shop_owner_id', $shop));

        $employees = $query->latest()->paginate((int) $request->input('per_page', 25))->withQueryString();

        $lastActivity = ActivityLog::query()
            ->whereIn('actor_id', $employees->pluck('id'))
            ->selectRaw('actor_id, MAX(created_at) as last_activity')
            ->groupBy('actor_id')
            ->pluck('last_activity', 'actor_id');

        $shops = User::query()->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']))->orderBy('name')->get(['id', 'name']);

        return view('admin.employees.index', compact('employees', 'shops', 'lastActivity'));
    }

    public function create()
    {
        $shopOwners = User::query()
            ->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.employees.create', compact('shopOwners'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'phone_number' => 'nullable|string|max:30',
            'shop_owner_id' => 'required|exists:users,id',
            'permissions' => 'nullable|array',
            'permissions.*' => array_merge(['bail'], explode('|', PermissionCatalog::validationRule())),
        ]);

        $owner = User::query()
            ->whereKey($validated['shop_owner_id'])
            ->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']))
            ->firstOrFail();

        $employee = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone_number' => $validated['phone_number'] ?? null,
            'role' => 'employee',
            'shop_owner_id' => $owner->id,
            'permissions' => PermissionCatalog::normalize($validated['permissions'] ?? []),
        ]);

        return $request->boolean('from_shop')
            ? redirect()->route('admin.shop-owners.show', $owner)->with('success', __('admin.messages.employee_created'))
            : redirect()->route('admin.employees.index')->with('success', __('admin.messages.employee_created'));
    }

    public function edit(User $employee)
    {
        abort_unless($employee->role === 'employee', 404);

        $shopOwners = User::query()
            ->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.employees.edit', compact('employee', 'shopOwners'));
    }

    public function update(Request $request, User $employee)
    {
        abort_unless($employee->role === 'employee', 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->id)],
            'password' => 'nullable|string|min:8',
            'phone_number' => 'nullable|string|max:30',
            'shop_owner_id' => 'required|exists:users,id',
            'permissions' => 'nullable|array',
            'permissions.*' => array_merge(['bail'], explode('|', PermissionCatalog::validationRule())),
        ]);

        $owner = User::query()
            ->whereKey($validated['shop_owner_id'])
            ->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']))
            ->firstOrFail();

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? null,
            'shop_owner_id' => $owner->id,
            'permissions' => PermissionCatalog::normalize($validated['permissions'] ?? []),
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $employee->update($payload);
        if (! empty($validated['password'])) {
            $employee->revokeRememberedAccess();
        }

        return redirect()->route('admin.employees.index')->with('success', __('admin.messages.employee_updated'));
    }

    public function destroy(User $employee)
    {
        abort_unless($employee->role === 'employee', 404);

        $employee->revokeRememberedAccess();
        $employee->delete();

        return redirect()->route('admin.employees.index')->with('success', __('admin.messages.employee_deleted'));
    }
}
