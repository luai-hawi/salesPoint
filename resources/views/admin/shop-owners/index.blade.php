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
            <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                {{ __('messages.All Users Management') }}
            </h2>
            <div class="flex items-center space-x-4">
                @php
                    $expiredCount = $users
                        ->filter(function ($user) {
                            return $user->isTempExpired();
                        })
                        ->count();
                @endphp
                @if ($expiredCount > 0)
                    <form action="{{ route('admin.shop-owners.delete-expired') }}" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200"
                            onclick="return confirm('{{ __('messages.Delete all expired accounts?') }}');">
                            {{ __('messages.Delete All Expired') }} ({{ $expiredCount }})
                        </button>
                    </form>
                @endif
                <a href="{{ route('admin.shop-owners.create') }}"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                    {{ __('messages.Add New User') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('info'))
                <div class="mb-6 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg">
                    {{ session('info') }}
                </div>
            @endif

            <!-- Users Table -->
            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('messages.Users List') }}</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('messages.User') }}</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('messages.Phone') }}</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('messages.Role') }}</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('messages.Account Type') }}</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('messages.Status') }}</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('messages.Subscription') }}</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('messages.Joined') }}</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('messages.Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($users as $user)
                                @php
                                    $isExpired = $user->isTempExpired();
                                @endphp
                                <tr
                                    class="{{ $isExpired ? 'bg-red-50' : '' }} hover:{{ $isExpired ? 'bg-red-100' : 'bg-gray-50' }} transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div
                                                class="w-10 h-10 bg-gradient-to-br
                                                {{ $user->role === 'admin' ? 'from-red-500 to-pink-600' : ($isExpired ? 'from-red-400 to-red-600' : 'from-indigo-500 to-purple-600') }}
                                                rounded-full flex items-center justify-center">
                                                <span
                                                    class="text-white font-bold text-sm">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                                            </div>
                                            <div class="ml-4">
                                                <div
                                                    class="text-sm font-medium {{ $isExpired ? 'text-red-700' : 'text-gray-900' }}">
                                                    {{ $user->name }}
                                                </div>
                                                <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $user->phone_number ?: '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $user->role === 'admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ ucfirst(str_replace('_', ' ', $user->role)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($user->account_type === 'temp')
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $isExpired ? 'bg-red-600 text-white' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ $isExpired ? __('messages.Expired') : __('messages.Temporary') }}
                                            </span>
                                            @if ($user->temp_expires_at)
                                                <div class="text-xs text-gray-500 mt-1">
                                                    @if ($isExpired)
                                                        {{ __('messages.Expired:') }}
                                                        {{ $user->temp_expires_at->format('M j, Y') }}
                                                    @else
                                                        {{ __('messages.Expires:') }}
                                                        {{ $user->temp_expires_at->format('M j, Y') }}
                                                    @endif
                                                </div>
                                            @endif
                                        @elseif($user->account_type === 'full')
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                {{ __('messages.Full Account') }}
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                -
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $user->role === 'disabled' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                            {{ $user->role === 'disabled' ? __('messages.Disabled') : __('messages.Active') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($user->subscription_paid)
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                {{ __('messages.Paid') }}
                                            </span>
                                            @if ($user->subscription_cost)
                                                <div class="text-xs text-gray-500 mt-1">
                                                    ₪{{ number_format($user->subscription_cost, 2) }}</div>
                                            @endif
                                            @if ($user->account_type === 'full' && $user->license_expires_at)
                                                @php $licDays = (int) now()->diffInDays($user->license_expires_at, false); @endphp
                                                <div class="text-xs mt-1 {{ $licDays < 0 ? 'text-red-600 font-semibold' : ($licDays <= 30 ? 'text-orange-500' : 'text-gray-400') }}">
                                                    🗓 {{ $user->license_expires_at->format('M j, Y') }}
                                                    ({{ $licDays < 0 ? 'exp.' : ($licDays . 'd left') }})
                                                </div>
                                            @endif
                                            <!-- Renew license button (always available for full accounts) -->
                                            @if ($user->account_type === 'full')
                                                <button type="button"
                                                    onclick="openMarkPaidModal({{ $user->id }}, '{{ addslashes($user->name) }}', {{ $user->subscription_cost ?? 0 }})"
                                                    class="mt-1 bg-blue-500 hover:bg-blue-600 text-white px-2 py-0.5 rounded text-xs font-medium transition-colors duration-200">
                                                    {{ __('messages.Renew License') }}
                                                </button>
                                            @endif
                                        @else
                                            <div class="flex items-center space-x-2">
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    {{ __('messages.Unpaid') }}
                                                </span>
                                                <button type="button"
                                                    onclick="openMarkPaidModal({{ $user->id }}, '{{ addslashes($user->name) }}', {{ $user->subscription_cost ?? 0 }})"
                                                    class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors duration-200">
                                                    {{ __('messages.Mark Paid') }}
                                                </button>
                                            </div>
                                            @if ($user->subscription_cost)
                                                <div class="text-xs text-gray-500 mt-1">
                                                    ₪{{ number_format($user->subscription_cost, 2) }}</div>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $user->created_at->format('M j, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-3">
                                            @if ($user->role === 'shop_owner' || $user->role === 'restaurant' || $user->role === 'merchant')
                                                <a href="{{ route('admin.shop-owners.show', $user->id) }}"
                                                    class="text-indigo-600 hover:text-indigo-900 transition-colors duration-200">
                                                    {{ __('messages.View Shop') }}
                                                </a>
                                            @endif
                                            @if ($user->account_type === 'temp')
                                                <form
                                                    action="{{ route('admin.shop-owners.convert-to-full', $user->id) }}"
                                                    method="POST" class="inline"
                                                    onsubmit="return confirm('{{ __('messages.Convert to full account?') }}');">
                                                    @csrf
                                                    <button type="submit"
                                                        class="text-green-600 hover:text-green-900 transition-colors duration-200">
                                                        {{ __('messages.Convert to Full') }}
                                                    </button>
                                                </form>
                                            @endif
                                            <a href="{{ route('admin.shop-owners.edit', $user->id) }}"
                                                class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
                                                {{ __('messages.Edit') }}
                                            </a>
                                            <form action="{{ route('admin.shop-owners.destroy', $user->id) }}"
                                                method="POST" class="inline"
                                                onsubmit="return confirm('{{ __('messages.Are you sure you want to delete this user?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="text-red-600 hover:text-red-900 transition-colors duration-200">
                                                    {{ __('messages.Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center">
                                        <div class="text-gray-500">
                                            <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                                                </path>
                                            </svg>
                                            <p class="text-lg font-medium">{{ __('messages.No users found') }}</p>
                                            <p class="text-sm text-gray-400 mt-1">
                                                {{ __('messages.Get started by creating your first user.') }}</p>
                                            <a href="{{ route('admin.shop-owners.create') }}"
                                                class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                                {{ __('messages.Add User') }}
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Mark Paid / Renew License Modal -->
    <div id="mark-paid-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20">
            <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeMarkPaidModal()"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md z-10 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900" id="modal-title">{{ __('messages.Mark Subscription Paid') }}</h3>
                    <button onclick="closeMarkPaidModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-gray-600 mb-5">
                    {{ __('messages.Marking paid for:') }} <strong id="modal-user-name"></strong>
                </p>
                <form id="mark-paid-form" method="POST" action="">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Number of Months') }}</label>
                            <input type="number" name="months" id="modal-months" value="1" min="1" max="120" required
                                class="w-full border border-gray-300 px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <p class="text-xs text-gray-500 mt-1">{{ __('messages.License will be extended from the current expiry date (or today if expired).') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Amount Charged (₪)') }}</label>
                            <input type="number" name="amount" id="modal-amount" step="0.01" min="0"
                                class="w-full border border-gray-300 px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="{{ __('messages.Leave empty to use subscription cost') }}">
                        </div>
                    </div>
                    <div class="flex items-center justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                        <button type="button" onclick="closeMarkPaidModal()"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            {{ __('messages.Cancel') }}
                        </button>
                        <button type="submit"
                            class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                            {{ __('messages.Confirm & Mark Paid') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openMarkPaidModal(userId, userName, defaultAmount) {
            document.getElementById('modal-user-name').textContent = userName;
            document.getElementById('mark-paid-form').action = '/admin/shop-owners/' + userId + '/mark-paid';
            document.getElementById('modal-amount').value = defaultAmount > 0 ? defaultAmount : '';
            document.getElementById('modal-months').value = 1;
            document.getElementById('mark-paid-modal').classList.remove('hidden');
        }
        function closeMarkPaidModal() {
            document.getElementById('mark-paid-modal').classList.add('hidden');
        }
    </script>
</x-app-layout>
