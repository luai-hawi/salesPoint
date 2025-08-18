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
                {{ __('Add New User') }}
            </h2>
        </div>
    </x-slot>
    
    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">User Information</h3>
                </div>

                <form action="{{ route('admin.shop-owners.store') }}" method="POST" class="p-6 space-y-6">
                    @csrf

                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               value="{{ old('name') }}" 
                               required
                               class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                               placeholder="Enter full name">
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
                               value="{{ old('email') }}" 
                               required
                               class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                               placeholder="Enter email address">
                        @error('email') 
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" 
                               name="password" 
                               id="password" 
                               required
                               class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                               placeholder="Enter password">
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
                            <option value="">Select a role</option>
                            <option value="shop_owner" {{ old('role') == 'shop_owner' ? 'selected' : '' }}>Shop Owner</option>
                            <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                        @error('role') 
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Role Descriptions -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Role Descriptions:</h4>
                        <div class="space-y-2 text-sm text-gray-600">
                            <div class="flex items-start space-x-2">
                                <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>
                                <div>
                                    <span class="font-medium">Shop Owner:</span> Can manage their own shop, products, employees, and sales data.
                                </div>
                            </div>
                            <div class="flex items-start space-x-2">
                                <div class="w-2 h-2 bg-red-500 rounded-full mt-2 flex-shrink-0"></div>
                                <div>
                                    <span class="font-medium">Admin:</span> Full system access, can manage all shop owners and their data.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end space-x-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('admin.dashboard') }}" 
                           class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200">
                            Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>