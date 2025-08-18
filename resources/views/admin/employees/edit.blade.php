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
                {{ __('Edit Employee: ') . $employee->name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-blue-600 rounded-full flex items-center justify-center">
                            <span class="text-white font-bold text-lg">{{ strtoupper(substr($employee->name, 0, 2)) }}</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Edit Employee Information</h3>
                            <p class="text-sm text-gray-600">Employee ID: #{{ $employee->id }}</p>
                        </div>
                    </div>
                </div>

                <form action="{{ route('admin.employees.update', $employee->id) }}" method="POST" class="p-6 space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               value="{{ old('name', $employee->name) }}" 
                               required
                               class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                        @error('name') 
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" 
                               name="email" 
                               id="email" 
                               value="{{ old('email', $employee->email) }}" 
                               required
                               class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                        @error('email') 
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password 
                            <span class="text-xs text-gray-500">(leave empty to keep current password)</span>
                        </label>
                        <input type="password" 
                               name="password" 
                               id="password"
                               class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                               placeholder="Enter new password">
                        @error('password') 
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Shop Owner Assignment -->
                    <div>
                        <label for="shop_owner_id" class="block text-sm font-medium text-gray-700 mb-2">Assign to Shop Owner</label>
                        <select name="shop_owner_id" 
                                id="shop_owner_id" 
                                required
                                class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                            <option value="">Select Shop Owner</option>
                            @foreach($shopOwners as $owner)
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

                    <!-- Current Assignment Info -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Current Assignment:</h4>
                        <div class="flex items-center space-x-2">
                            @if($employee->shopOwner)
                                <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center">
                                    <span class="text-white font-bold text-xs">{{ strtoupper(substr($employee->shopOwner->name, 0, 2)) }}</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $employee->shopOwner->name }}</p>
                                    <p class="text-xs text-gray-600">{{ $employee->shopOwner->email }}</p>
                                </div>
                            @else
                                <span class="text-sm text-red-600">⚠️ Not assigned to any shop owner</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Employee since: {{ $employee->created_at->format('M j, Y') }}</p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end space-x-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('admin.employees.index') }}" 
                           class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200">
                            Update Employee
                        </button>
                    </div>
                </form>
            </div>

            <!-- Quick Actions -->
            <div class="mt-6 bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center space-x-4">
                        @if($employee->shopOwner)
                            <a href="{{ route('admin.shop-owners.show', $employee->shopOwner->id) }}" 
                               class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                                View Shop Details
                            </a>
                        @endif
                        <form action="{{ route('admin.employees.destroy', $employee->id) }}" 
                              method="POST" 
                              class="inline"
                              onsubmit="return confirm('Are you sure you want to permanently delete this employee? This action cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                                Delete Employee
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>