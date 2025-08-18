<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-4">
            <a href="{{ route('admin.dashboard') }}" 
               class="text-gray-600 hover:text-gray-900 transition-colors duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                {{ __('Edit User: ') . $shopOwner->name }}
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
                    <h3 class="text-lg font-semibold text-gray-900">Edit User Information</h3>
                </div>

                <form action="{{ route('admin.shop-owners.update', $shopOwner->id) }}" method="POST" class="p-6 space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               value="{{ old('name', $shopOwner->name) }}" 
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
                               value="{{ old('email', $shopOwner->email) }}" 
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

                    <!-- Role -->
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 mb-2">User Role</label>
                        <select name="role" 
                                id="role" 
                                required
                                class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                            <option value="shop_owner" {{ old('role', $shopOwner->role) == 'shop_owner' ? 'selected' : '' }}>Shop Owner</option>
                            <option value="admin" {{ old('role', $shopOwner->role) == 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="disabled" {{ old('role', $shopOwner->role) == 'disabled' ? 'selected' : '' }}>Disabled</option>
                        </select>
                        @error('role') 
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Current Status Info -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Current Status:</h4>
                        <div class="flex items-center space-x-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $shopOwner->role === 'admin' ? 'bg-red-100 text-red-800' : 
                                   ($shopOwner->role === 'disabled' ? 'bg-gray-100 text-gray-800' : 'bg-green-100 text-green-800') }}">
                                {{ ucfirst(str_replace('_', ' ', $shopOwner->role)) }}
                            </span>
                            <span class="text-sm text-gray-600">since {{ $shopOwner->created_at->format('M j, Y') }}</span>
                        </div>
                        
                        @if($shopOwner->role === 'shop_owner' && $shopOwner->employees_count > 0)
                            <p class="text-sm text-yellow-600 mt-2">
                                ⚠️ This shop owner has {{ $shopOwner->employees_count }} employees. Changing role will affect their access.
                            </p>
                        @endif
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end space-x-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('admin.dashboard') }}" 
                           class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200">
                            Update User
                        </button>
                    </div>
                </form>
            </div>

            <!-- Quick Actions for Shop Owners -->
            @if($shopOwner->role === 'shop_owner')
                <div class="mt-6 bg-white shadow-lg rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center space-x-4">
                            <a href="{{ route('admin.shop-owners.show', $shopOwner->id) }}" 
                               class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                                View Shop Details
                            </a>
                            <form action="{{ route('admin.shop-owners.toggle-status', $shopOwner->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                        class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200"
                                        onclick="return confirm('Are you sure you want to {{ $shopOwner->role === 'shop_owner' ? 'disable' : 'enable' }} this shop owner?')">
                                    {{ $shopOwner->role === 'shop_owner' ? 'Disable Shop' : 'Enable Shop' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>