@if($payments->count() > 0)
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            {{ __('messages.Date') }}
                        </div>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                            {{ __('messages.Amount') }}
                        </div>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('employees.Payment Method') }}
                    </th>
                    <th scope="col" class="relative px-6 py-3">
                        <span class="sr-only">{{ __('employees.Actions') }}</span>
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($payments as $payment)
                    @php
                        $isRecent = \Carbon\Carbon::parse($payment->payment_date)->isToday();
                        $isThisWeek = \Carbon\Carbon::parse($payment->payment_date)->isCurrentWeek();
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors duration-200 {{ $isRecent ? 'bg-green-50' : '' }}">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    @if($isRecent)
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                    @elseif($isThisWeek)
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($payment->payment_date)->format('l') }}
                                        @if($isRecent)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 ml-2">
                                                {{ __('employees.Today') }}
                                            </span>
                                        @elseif($isThisWeek)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 ml-2">
                                                {{ __('employees.This Week') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="text-lg font-bold text-gray-900">
                                    ₪{{ number_format($payment->amount, 2) }}
                                </div>
                                @if($payment->amount >= 1000)
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                        {{ __('employees.Large Payment') }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                <span class="text-sm text-gray-500">
                                    {{ $payment->payment_method ?? __('messages.Cash') }}
                                </span>
                            </div>
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-2">
                                <!-- View Details Button -->
                                <button onclick="showPaymentDetails({{ $payment->id }})" 
                                        class="text-indigo-600 hover:text-indigo-900 transition-colors duration-200 p-1 rounded-full hover:bg-indigo-50"
                                        title="{{ __('employees.View Details') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                                
                                <!-- Delete Payment Form -->
                                <form action="{{ route('shopowner.employees.destroyPayment', $payment->id) }}" 
                                      method="POST" 
                                      class="inline"
                                      onsubmit="return confirm('Are you sure you want to delete this payment of ₪{{ number_format($payment->amount, 2) }} made on {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}? This action cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="text-red-600 hover:text-red-900 transition-colors duration-200 p-1 rounded-full hover:bg-red-50"
                                            title="{{ __('employees.Delete Payment') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    
    <!-- Payment Summary at Bottom -->
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-600">
                {{ __('employees.Showing') }} {{ $payments->count() }} {{ __('employees.of') }} {{ $payments->total() }} {{ __('employees.payments') }}
            </div>
            <div class="flex items-center space-x-4">
                <div class="text-sm">
                    <span class="text-gray-600">{{ __('employees.Total on this page') }}:</span>
                    <span class="font-semibold text-gray-900">₪{{ number_format($payments->sum('amount'), 2) }}</span>
                </div>
                @if($payments->total() > $payments->count())
                    <div class="text-sm">
                        <span class="text-gray-600">{{ __('employees.Grand Total') }}:</span>
                        <span class="font-bold text-green-600">₪{{ number_format($payments->getCollection()->sum('amount'), 2) }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

@else
    <!-- Empty State -->
    <div class="text-center py-12">
        <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-6">
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('employees.No payments recorded') }}</h3>
        <p class="text-gray-600 mb-6 max-w-sm mx-auto">
            {{ __('employees.This employee hasn\'t received any payments yet. Use the form above to record their first payment.') }}
        </p>
        <div class="flex items-center justify-center space-x-2 text-sm text-gray-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ __('employees.Payments will appear here once recorded') }}</span>
        </div>
    </div>
@endif

<!-- Payment Details Modal (Optional - for future enhancement) -->
<div id="payment-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('employees.Payment Details') }}</h3>
                </button>
            </div>
            <div id="payment-details-content">
                <!-- Payment details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
    function showPaymentDetails(paymentId) {
        // This is a placeholder for future payment details functionality
        document.getElementById('payment-details-content').innerHTML = `
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('employees.Payment ID') }}</label>
                    <div class="text-sm text-gray-900">#${paymentId}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        {{ __('employees.Completed') }}
                    </span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('employees.Notes') }}</label>
                    <div class="text-sm text-gray-900">{{ __('employees.Regular salary payment') }}</div>
                </div>
            </div>
        `;
        document.getElementById('payment-modal').classList.remove('hidden');
    }
    
    function closePaymentModal() {
        document.getElementById('payment-modal').classList.add('hidden');
    }
    
    // Close modal when clicking outside
    document.getElementById('payment-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePaymentModal();
        }
    });
</script>