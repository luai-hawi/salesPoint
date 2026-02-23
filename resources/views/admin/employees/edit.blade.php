@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-4">
            <a href="{{ route('admin.employees.index') }}"
                class="text-gray-600 hover:text-gray-900 transition-colors duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
                    </path>
                </svg>
            </a>
            <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                {{ __('messages.edit_employee_title', ['name' => $employee->name]) }}
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center space-x-4">
                        <div
                            class="w-12 h-12 bg-gradient-to-br from-green-500 to-blue-600 rounded-full flex items-center justify-center">
                            <span
                                class="text-white font-bold text-lg">{{ strtoupper(substr($employee->name, 0, 2)) }}</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ __('messages.edit_employee_information') }}</h3>
                            <p class="text-sm text-gray-600">{{ __('messages.employee_id') }}: #{{ $employee->id }}</p>
                        </div>
                    </div>
                </div>

                <form action="{{ route('admin.employees.update', $employee->id) }}" method="POST"
                    class="p-6 space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Name -->
                    <div>
                        <label for="name"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.full_name') }}</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $employee->name) }}"
                            required
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.email_address') }}</label>
                        <input type="email" name="email" id="email"
                            value="{{ old('email', $employee->email) }}" required
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label for="phone_number"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.phone_number') }}</label>
                        <input type="text" name="phone_number" id="phone_number"
                            value="{{ old('phone_number', $employee->phone_number) }}"
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="{{ __('messages.enter_phone_number') }}">
                        @error('phone_number')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('messages.password') }}
                            <span class="text-xs text-gray-500">({{ __('messages.leave_empty_keep_password') }})</span>
                        </label>
                        <input type="password" name="password" id="password"
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="{{ __('messages.enter_new_password') }}">
                        @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Shop Owner Assignment -->
                    @if (auth()->user()->role !== 'employee')
                        <div>
                            <label for="shop_owner_id"
                                class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.assign_to_shop_owner') }}</label>
                            <select name="shop_owner_id" id="shop_owner_id" required
                                class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                                <option value="">{{ __('messages.select_shop_owner') }}</option>
                                @foreach ($shopOwners as $owner)
                                    <option value="{{ $owner->id }}"
                                        {{ old('shop_owner_id', $employee->shop_owner_id) == $owner->id ? 'selected' : '' }}>
                                        {{ $owner->name }} ({{ $owner->email }})
                                    </option>
                                @endforeach
                            </select>
                            @error('shop_owner_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <input type="hidden" name="shop_owner_id" value="{{ $employee->shop_owner_id }}">
                    @endif

                    <!-- Current Assignment Info -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('messages.current_assignment') }}:
                        </h4>
                        <div class="flex items-center space-x-2">
                            @if ($employee->shopOwner)
                                <div
                                    class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center">
                                    <span
                                        class="text-white font-bold text-xs">{{ strtoupper(substr($employee->shopOwner->name, 0, 2)) }}</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $employee->shopOwner->name }}</p>
                                    <p class="text-xs text-gray-600">{{ $employee->shopOwner->email }}</p>
                                </div>
                            @else
                                <span class="text-sm text-red-600">⚠️ {{ __('messages.not_assigned_to_shop') }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 mt-2">{{ __('messages.employee_since') }}:
                            {{ $employee->created_at->format('M j, Y') }}</p>
                    </div>

                    <!-- Permissions -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Employee Permissions') }}</label>
                        <div class="bg-gray-50 rounded-lg p-4 space-y-4">
                            <p class="text-sm text-gray-600 mb-3">
                                {{ __('messages.Select the permissions this employee should have') }}:</p>

                            @php
                                $employeePermissions = $employee->getPermissions();
                            @endphp

                            <!-- Products Permissions -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('messages.Products') }}</h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="view_products"
                                            {{ in_array('view_products', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.View') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="create_products"
                                            {{ in_array('create_products', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Create') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="edit_products"
                                            {{ in_array('edit_products', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Edit') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="delete_products"
                                            {{ in_array('delete_products', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Delete') }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Bills Permissions -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('messages.Bills') }}</h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="view_bills"
                                            {{ in_array('view_bills', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.View') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="create_bills"
                                            {{ in_array('create_bills', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Create') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="edit_bills"
                                            {{ in_array('edit_bills', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Edit') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="delete_bills"
                                            {{ in_array('delete_bills', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Delete') }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Customers Permissions -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('messages.Customers') }}</h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="view_customers"
                                            {{ in_array('view_customers', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.View') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="create_customers"
                                            {{ in_array('create_customers', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Create') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="edit_customers"
                                            {{ in_array('edit_customers', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Edit') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="delete_customers"
                                            {{ in_array('delete_customers', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Delete') }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Suppliers Permissions -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('messages.Suppliers') }}</h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="view_suppliers"
                                            {{ in_array('view_suppliers', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.View') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="create_suppliers"
                                            {{ in_array('create_suppliers', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Create') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="edit_suppliers"
                                            {{ in_array('edit_suppliers', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Edit') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="delete_suppliers"
                                            {{ in_array('delete_suppliers', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Delete') }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Purchase Bills Permissions -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('messages.Purchase Bills') }}
                                </h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="view_purchase_bills"
                                            {{ in_array('view_purchase_bills', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.View') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="create_purchase_bills"
                                            {{ in_array('create_purchase_bills', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Create') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="edit_purchase_bills"
                                            {{ in_array('edit_purchase_bills', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Edit') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="delete_purchase_bills"
                                            {{ in_array('delete_purchase_bills', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Delete') }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Tags Permissions -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('messages.Tags') }}</h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="view_tags"
                                            {{ in_array('view_tags', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.View') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="create_tags"
                                            {{ in_array('create_tags', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Create') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="edit_tags"
                                            {{ in_array('edit_tags', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Edit') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="delete_tags"
                                            {{ in_array('delete_tags', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Delete') }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Expenses Permissions -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('messages.Expenses') }}</h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="view_expenses"
                                            {{ in_array('view_expenses', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.View') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="create_expenses"
                                            {{ in_array('create_expenses', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Create') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="edit_expenses"
                                            {{ in_array('edit_expenses', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Edit') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="delete_expenses"
                                            {{ in_array('delete_expenses', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-xs text-gray-700">{{ __('messages.Delete') }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Other Permissions -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">
                                    {{ __('messages.Other Permissions') }}</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="manage_settings"
                                            {{ in_array('manage_settings', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span
                                            class="ml-2 text-sm text-gray-700">{{ __('messages.Manage Settings') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="view_financial"
                                            {{ in_array('view_financial', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span
                                            class="ml-2 text-sm text-gray-700">{{ __('messages.View Financial Reports') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="manage_employees"
                                            {{ in_array('manage_employees', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span
                                            class="ml-2 text-sm text-gray-700">{{ __('messages.Manage Employees') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="permissions[]" value="manage_payments_receipts"
                                            {{ in_array('manage_payments_receipts', $employeePermissions) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span
                                            class="ml-2 text-sm text-gray-700">{{ __('messages.Payments and Receipts') }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        @error('permissions')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end space-x-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('admin.employees.index') }}"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                            {{ __('messages.cancel') }}
                        </a>
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200">
                            {{ __('messages.update_employee') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Quick Actions -->
            <div class="mt-6 bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('messages.quick_actions') }}</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center space-x-4">
                        @if ($employee->shopOwner)
                            <a href="{{ route('admin.shop-owners.show', $employee->shopOwner->id) }}"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                                {{ __('messages.view_shop_details') }}
                            </a>
                        @endif
                        <form action="{{ route('admin.employees.destroy', $employee->id) }}" method="POST"
                            class="inline"
                            onsubmit="return confirm('{{ __('messages.confirm_delete_employee_permanent') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                                {{ __('messages.delete_employee') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
