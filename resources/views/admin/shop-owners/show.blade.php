@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
    @endphp
<x-app-layout>
     <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.dashboard') }}" 
                   class="text-gray-600 hover:text-gray-900 transition-colors duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                    {{ __('messages.shop_title', ['name' => $shopOwner->name]) }}
                </h2>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.shop-owners.edit', $shopOwner->id) }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                    {{ __('messages.edit_shop_owner') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Shop Owner Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <!-- Shop Owner Info -->
                <div class="lg:col-span-1">
                    <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('messages.shop_owner_details') }}</h3>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center mb-6">
                                <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center">
                                    <span class="text-white font-bold text-xl">{{ strtoupper(substr($shopOwner->name, 0, 2)) }}</span>
                                </div>
                                <div class="ml-4">
                                    <h4 class="text-xl font-bold text-gray-900">{{ $shopOwner->name }}</h4>
                                    <p class="text-gray-600">{{ $shopOwner->email }}</p>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">{{ __('messages.status') }}</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        {{ $shopOwner->role === 'shop_owner' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ __('messages.role_' . $shopOwner->role) }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">{{ __('messages.member_since') }}</span>
                                    <span class="text-gray-900">{{ $shopOwner->created_at->format('M j, Y') }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">{{ __('messages.total_employees') }}</span>
                                    <span class="text-gray-900 font-semibold">{{ $shopOwner->employees_count }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Statistics -->
                <div class="lg:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-600">{{ __('messages.total_sales') }}</p>
                                        <p class="text-2xl font-bold text-gray-900">${{ number_format($shopOwner->total_sales, 2) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-600">{{ __('messages.this_month') }}</p>
                                        <p class="text-2xl font-bold text-gray-900">${{ number_format($shopOwner->sales_this_month, 2) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-600">{{ __('messages.products') }}</p>
                                        <p class="text-2xl font-bold text-gray-900">{{ $shopOwner->products_count }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-600">{{ __('messages.customers') }}</p>
                                        <p class="text-2xl font-bold text-gray-900">{{ $shopOwner->customers_count }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employees Section -->
            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('messages.employees_management') }}</h3>
                        <button onclick="toggleAddEmployeeForm()" 
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                            {{ __('messages.add_employee') }}
                        </button>
                    </div>
                </div>

                <!-- Add Employee Form (Hidden by default) -->
                <div id="addEmployeeForm" class="hidden border-b border-gray-200 bg-gray-50">
                    <form action="{{ route('admin.employees.store') }}" method="POST" class="p-6">
                        @csrf
                        <input type="hidden" name="shop_owner_id" value="{{ $shopOwner->id }}">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.full_name') }}</label>
                                <input type="text" 
                                       name="name" 
                                       id="name" 
                                       required
                                       class="w-full border border-gray-300 px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                       placeholder="{{ __('messages.enter_employee_name') }}">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.email_address') }}</label>
                                <input type="email" 
                                       name="email" 
                                       id="email" 
                                       required
                                       class="w-full border border-gray-300 px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                       placeholder="{{ __('messages.enter_email_address') }}">
                            </div>
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.password') }}</label>
                                <input type="password" 
                                       name="password" 
                                       id="password" 
                                       required
                                       class="w-full border border-gray-300 px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                       placeholder="{{ __('messages.enter_password') }}">
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-end space-x-3 mt-4">
                            <button type="button" 
                                    onclick="toggleAddEmployeeForm()"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                                {{ __('messages.cancel') }}
                            </button>
                            <button type="submit"
                                    class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200">
                                {{ __('messages.add_employee') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Employees List -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('messages.employee') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('messages.contact') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('messages.joined') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($shopOwner->employees as $employee)
                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-blue-600 rounded-full flex items-center justify-center">
                                                <span class="text-white font-bold text-sm">{{ strtoupper(substr($employee->name, 0, 2)) }}</span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ $employee->name }}</div>
                                                <div class="text-sm text-gray-500">{{ __('messages.employee_id') }}: #{{ $employee->id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $employee->email }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $employee->created_at->format('M j, Y') }}
                                        <div class="text-xs text-gray-400">{{ $employee->created_at->diffForHumans() }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-3">
                                            <a href="{{ route('admin.employees.edit', $employee->id) }}" 
                                               class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
                                                {{ __('messages.edit') }}
                                            </a>
                                            <form action="{{ route('admin.employees.destroy', $employee->id) }}" 
                                                  method="POST" 
                                                  class="inline"
                                                  onsubmit="return confirm('{{ __('messages.confirm_delete_employee') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="text-red-600 hover:text-red-900 transition-colors duration-200">
                                                    {{ __('messages.remove') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="text-gray-500">
                                            <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                            <p class="text-lg font-medium">{{ __('messages.no_employees_yet') }}</p>
                                            <p class="text-sm text-gray-400 mt-1">{{ __('messages.add_employees_help_text') }}</p>
                                            <button onclick="toggleAddEmployeeForm()" 
                                                    class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                                {{ __('messages.add_first_employee') }}
                                            </button>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-8 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <form action="{{ route('admin.shop-owners.toggle-status', $shopOwner->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                                class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200"
                                onclick="return confirm('{{ __('messages.confirm_toggle_status', ['action' => $shopOwner->role === 'shop_owner' ? __('messages.disable') : __('messages.enable')]) }}')">
                            {{ $shopOwner->role === 'shop_owner' ? __('messages.disable_shop') : __('messages.enable_shop') }}
                        </button>
                    </form>
                    
                    <a href="{{ route('admin.employees.index') }}" 
                       class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                        {{ __('messages.view_all_employees') }}
                    </a>
                </div>
                
                <form action="{{ route('admin.shop-owners.destroy', $shopOwner->id) }}" 
                      method="POST" 
                      class="inline"
                      onsubmit="return confirm('{{ __('messages.confirm_delete_shop_owner') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                        {{ __('messages.delete_shop_owner') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript for Toggle Form -->
    <script>
        function toggleAddEmployeeForm() {
            const form = document.getElementById('addEmployeeForm');
            form.classList.toggle('hidden');
            
            // Focus on the first input when showing the form
            if (!form.classList.contains('hidden')) {
                setTimeout(() => {
                    document.getElementById('name').focus();
                }, 100);
            }
        }
        
        // Hide form on ESC key press
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const form = document.getElementById('addEmployeeForm');
                if (!form.classList.contains('hidden')) {
                    form.classList.add('hidden');
                }
            }
        });
    </script>
</x-app-layout>