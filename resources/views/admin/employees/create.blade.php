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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                {{ __('messages.Add New Employee') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">{{__('messages.Employee Information')}}</h3>
                </div>

                <form action="{{ route('admin.employees.store') }}" method="POST" class="p-6 space-y-6">
                    @csrf

                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">{{__('messages.Full Name')}}</label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               value="{{ old('name') }}" 
                               required
                               class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                               placeholder="{{ __('messages.Enter employee\'s full name') }}">
                        @error('name') 
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">{{__('messages.Email Address')}}</label>
                        <input type="email"
                               name="email"
                               id="email"
                               value="{{ old('email') }}"
                               required
                               class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                               placeholder="{{ __('messages.Enter employee\'s email address') }}">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-2">{{__('messages.Phone Number')}}</label>
                        <input type="text"
                               name="phone_number"
                               id="phone_number"
                               value="{{ old('phone_number') }}"
                               class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                               placeholder="{{ __('messages.Enter phone number') }}">
                        @error('phone_number')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">{{__('messages.Password')}}</label>
                        <input type="password" 
                               name="password" 
                               id="password" 
                               required
                               class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                               placeholder="{{ __('messages.Enter password for the employee') }}">
                        @error('password') 
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Shop Owner -->
                    <div>
                        <label for="shop_owner_id" class="block text-sm font-medium text-gray-700 mb-2">{{__('messages.Assign to Shop Owner')}}</label>
                        <select name="shop_owner_id" 
                                id="shop_owner_id" 
                                required
                                class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                            <option value="">{{__('messages.Select Shop Owner')}}</option>
                            @foreach($shopOwners as $owner)
                                <option value="{{ $owner->id }}" {{ old('shop_owner_id') == $owner->id ? 'selected' : '' }}>
                                    {{ $owner->name }} ({{ $owner->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('shop_owner_id') 
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Shop Owners Info -->
                    @if($shopOwners->count() > 0)
                        <div class="bg-blue-50 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-blue-900 mb-2">{{__('messages.Available Shop Owners')}} ({{ $shopOwners->count() }}):</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-blue-800">
                                @foreach($shopOwners->take(4) as $owner)
                                    <div class="flex items-center space-x-2">
                                        <div class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0"></div>
                                        <span>{{ $owner->name }}</span>
                                    </div>
                                @endforeach
                                @if($shopOwners->count() > 4)
                                    <div class="text-xs text-blue-600">
                                        +{{ $shopOwners->count() - 4 }} more...
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">{{__('messages.No Shop Owners Available')}}</h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>{{__('messages.You need to create shop owners first before adding employees')}}.</p>
                                    </div>
                                    <div class="mt-4">
                                        <div class="-mx-2 -my-1.5 flex">
                                            <a href="{{ route('admin.shop-owners.create') }}" 
                                               class="bg-yellow-50 px-2 py-1.5 rounded-md text-sm font-medium text-yellow-800 hover:bg-yellow-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-yellow-50 focus:ring-yellow-600">
                                                {{__('messages.Create Shop Owner')}}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Permissions -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{__('messages.Employee Permissions')}}</label>
                        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                            <p class="text-sm text-gray-600 mb-3">{{__('messages.Select the permissions this employee should have')}}:</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="manage_products" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">{{__('messages.Manage Products')}}</span>
                                </label>

                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="manage_bills" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">{{__('messages.Manage Bills')}}</span>
                                </label>

                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="manage_customers" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">{{__('messages.Manage Customers')}}</span>
                                </label>

                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="manage_suppliers" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">{{__('messages.Manage Suppliers')}}</span>
                                </label>

                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="manage_purchase_bills" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">{{__('messages.Manage Purchase Bills')}}</span>
                                </label>

                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="manage_settings" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">{{__('messages.Manage Settings')}}</span>
                                </label>

                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="manage_tags" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">{{__('messages.Manage Tags')}}</span>
                                </label>

                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="view_financial" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">{{__('messages.View Financial Reports')}}</span>
                                </label>

                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="manage_employees" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">{{__('messages.Manage Employees')}}</span>
                                </label>

                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="manage_expenses" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">{{__('messages.Manage Expenses')}}</span>
                                </label>
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
                            {{__('messages.Cancel')}}
                        </a>
                        <button type="submit"
                                class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200"
                                {{ $shopOwners->count() == 0 ? 'disabled' : '' }}>
                            {{__('messages.Create Employee')}}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>