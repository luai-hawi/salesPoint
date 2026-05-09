@php
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.shop-owners.index') }}"
                    class="text-gray-500 hover:text-gray-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <h2 class="font-bold text-2xl text-gray-900 leading-tight flex items-center gap-3">
                    {{ __('messages.License Expiry Monitor') }}
                    @php $totalExpired = $expiredLicenses->count(); @endphp
                    @if ($totalExpired > 0)
                        <span class="inline-flex items-center justify-center w-7 h-7 bg-red-600 text-white text-sm font-bold rounded-full">
                            {{ $totalExpired }}
                        </span>
                    @endif
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            {{-- ══ EXPIRED LICENSES ══════════════════════════════════════════════ --}}
            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-red-200 bg-red-50 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-red-700 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        {{ __('messages.Expired Licenses') }}
                        <span class="bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                            {{ $expiredLicenses->count() }}
                        </span>
                    </h3>
                </div>

                @if ($expiredLicenses->isEmpty())
                    <div class="p-8 text-center text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <p class="font-medium text-green-600">{{ __('messages.No expired licenses') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.User') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Phone') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.License Expired') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Days Overdue') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Last Payment') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Subscription Cost') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($expiredLicenses as $user)
                                    @php $daysOverdue = abs((int) now()->diffInDays($user->license_expires_at, false)); @endphp
                                    <tr class="bg-red-50 hover:bg-red-100 transition-colors">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 bg-gradient-to-br from-red-400 to-red-600 rounded-full flex items-center justify-center flex-shrink-0">
                                                    <span class="text-white font-bold text-xs">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-semibold text-red-800">{{ $user->name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $user->email }}</div>
                                                    <span class="text-xs text-gray-400">{{ ucfirst(str_replace('_', ' ', $user->role)) }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $user->phone_number ?: '—' }}</td>
                                        <td class="px-4 py-3 text-sm font-semibold text-red-700">
                                            {{ $user->license_expires_at->format('M j, Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white">
                                                {{ $daysOverdue }} {{ __('messages.days') }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            @if ($user->last_payment_months || $user->last_payment_amount)
                                                @if ($user->last_payment_months)
                                                    <span class="font-medium">{{ $user->last_payment_months }}mo</span>
                                                @endif
                                                @if ($user->last_payment_amount)
                                                    <span class="text-green-700 font-semibold ml-1">₪{{ number_format($user->last_payment_amount, 2) }}</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            @if ($user->subscription_cost)
                                                <span class="font-semibold text-gray-800">₪{{ number_format($user->subscription_cost, 2) }}</span>
                                                <span class="text-xs text-gray-400">/mo</span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <button type="button"
                                                    onclick="openMarkPaidModal({{ $user->id }}, '{{ addslashes($user->name) }}', {{ $user->subscription_cost ?? 0 }})"
                                                    class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                                    {{ __('messages.Renew License') }}
                                                </button>
                                                <a href="{{ route('admin.shop-owners.edit', $user->id) }}"
                                                    class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                                    {{ __('messages.Edit') }}
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ══ EXPIRING SOON ══════════════════════════════════════════════════ --}}
            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-orange-200 bg-orange-50 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-orange-700 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('messages.Expiring Soon') }} ({{ __('messages.within 30 days') }})
                        <span class="bg-orange-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                            {{ $expiringSoonLicenses->count() }}
                        </span>
                    </h3>
                </div>

                @if ($expiringSoonLicenses->isEmpty())
                    <div class="p-8 text-center text-gray-400">
                        <p class="text-sm">{{ __('messages.No licenses expiring in the next 30 days.') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.User') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Phone') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.License Expires') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Days Left') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Last Payment') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Subscription Cost') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($expiringSoonLicenses as $user)
                                    @php $daysLeft = (int) now()->diffInDays($user->license_expires_at, false); @endphp
                                    <tr class="bg-orange-50 hover:bg-orange-100 transition-colors">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 bg-gradient-to-br from-orange-400 to-orange-600 rounded-full flex items-center justify-center flex-shrink-0">
                                                    <span class="text-white font-bold text-xs">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-semibold text-gray-800">{{ $user->name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $user->email }}</div>
                                                    <span class="text-xs text-gray-400">{{ ucfirst(str_replace('_', ' ', $user->role)) }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $user->phone_number ?: '—' }}</td>
                                        <td class="px-4 py-3 text-sm font-semibold text-orange-700">
                                            {{ $user->license_expires_at->format('M j, Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
                                                {{ $daysLeft <= 7 ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700' }}">
                                                {{ $daysLeft }} {{ __('messages.days') }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            @if ($user->last_payment_months || $user->last_payment_amount)
                                                @if ($user->last_payment_months)
                                                    <span class="font-medium">{{ $user->last_payment_months }}mo</span>
                                                @endif
                                                @if ($user->last_payment_amount)
                                                    <span class="text-green-700 font-semibold ml-1">₪{{ number_format($user->last_payment_amount, 2) }}</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            @if ($user->subscription_cost)
                                                <span class="font-semibold text-gray-800">₪{{ number_format($user->subscription_cost, 2) }}</span>
                                                <span class="text-xs text-gray-400">/mo</span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <button type="button"
                                                    onclick="openMarkPaidModal({{ $user->id }}, '{{ addslashes($user->name) }}', {{ $user->subscription_cost ?? 0 }})"
                                                    class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                                    {{ __('messages.Renew License') }}
                                                </button>
                                                <a href="{{ route('admin.shop-owners.edit', $user->id) }}"
                                                    class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                                    {{ __('messages.Edit') }}
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ══ ONLINE MENU – EXPIRED SUBSCRIPTIONS ══════════════════════════ --}}
            @if ($menuExpiredUsers->isNotEmpty())
                <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-purple-200 bg-purple-50 flex items-center gap-2">
                        <h3 class="text-lg font-bold text-purple-700 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
                            </svg>
                            {{ __('messages.Online Menu – Expired/No Subscription') }}
                            <span class="bg-purple-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                                {{ $menuExpiredUsers->count() }}
                            </span>
                        </h3>
                        <span class="text-xs text-purple-500 ml-2">({{ __('messages.Separate online menu system') }})</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Restaurant') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Email') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Phone') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Last Paid') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Last Amount') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Expired On') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($menuExpiredUsers as $mu)
                                    <tr class="bg-purple-50 hover:bg-purple-100 transition-colors">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 bg-gradient-to-br from-purple-400 to-purple-600 rounded-full flex items-center justify-center flex-shrink-0">
                                                    <span class="text-white font-bold text-xs">{{ strtoupper(substr($mu->name ?? '?', 0, 2)) }}</span>
                                                </div>
                                                <span class="text-sm font-semibold text-gray-800">{{ $mu->name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $mu->email }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $mu->phone ?: '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            {{ $mu->paid_at ? \Carbon\Carbon::parse($mu->paid_at)->format('M j, Y') : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm font-semibold text-green-700">
                                            {{ $mu->amount ? '₪' . number_format($mu->amount, 2) : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm font-semibold text-red-700">
                                            {{ $mu->expires_at ? \Carbon\Carbon::parse($mu->expires_at)->format('M j, Y') : __('messages.Never set') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-purple-200 bg-purple-50">
                        <h3 class="text-lg font-bold text-purple-700">{{ __('messages.Online Menu – Expired/No Subscription') }}</h3>
                    </div>
                    <div class="p-8 text-center text-gray-400">
                        <p class="text-sm">{{ __('messages.No expired menu subscriptions found or menu database unavailable.') }}</p>
                    </div>
                </div>
            @endif

        </div>
    </div>

    <!-- Mark Paid / Renew License Modal -->
    <div id="mark-paid-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20">
            <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeMarkPaidModal()"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md z-10 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">{{ __('messages.Renew License') }}</h3>
                    <button onclick="closeMarkPaidModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-gray-600 mb-5">
                    {{ __('messages.Renewing license for:') }} <strong id="modal-user-name"></strong>
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
                            {{ __('messages.Confirm & Renew') }}
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
