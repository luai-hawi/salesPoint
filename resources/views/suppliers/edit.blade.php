<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('messages.Edit Supplier: ') }} {{ $supplier->name }}
            </h2>
            <a href="{{ route('suppliers.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                {{ __('messages.Back to Suppliers') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Supplier Information -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('messages.Supplier Information') }}</h3>
                        
                        <form method="POST" action="{{ route('suppliers.update', $supplier) }}">
                            @csrf
                            @method('PUT')
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700">{{ __('messages.Name') }} *</label>
                                    <input type="text" name="name" id="name" required
                                           class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           value="{{ old('name', $supplier->name) }}">
                                    @error('name')
                                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="phone" class="block text-sm font-medium text-gray-700">{{ __('messages.Phone') }}</label>
                                        <input type="text" name="phone" id="phone"
                                               class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               value="{{ old('phone', $supplier->phone) }}">
                                    </div>
                                    
                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700">{{ __('messages.Email') }}</label>
                                        <input type="email" name="email" id="email"
                                               class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               value="{{ old('email', $supplier->email) }}">
                                    </div>
                                </div>

                                <div>
                                    <label for="address" class="block text-sm font-medium text-gray-700">{{ __('messages.Address') }}</label>
                                    <textarea name="address" id="address" rows="3"
                                              class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('address', $supplier->address) }}</textarea>
                                </div>

                                <div>
                                    <label for="notes" class="block text-sm font-medium text-gray-700">{{ __('messages.Notes') }}</label>
                                    <textarea name="notes" id="notes" rows="3"
                                              class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('notes', $supplier->notes) }}</textarea>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <a href="{{ route('suppliers.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-lg transition-colors">
                                    {{ __('messages.Cancel') }}
                                </a>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                    {{ __('messages.Update Supplier') }}
                                </button>
                            </div>
                        </form>

                        <!-- Balance Information -->
                        <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="font-medium text-gray-900">{{ __('messages.Current Balance') }}</h4>
                                <span class="px-3 py-1 text-sm font-semibold rounded-full 
                                    {{ $supplier->balance > 0 ? 'bg-red-100 text-red-800' : 
                                       ($supplier->balance < 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') }}">
                                    ₪{{ number_format(abs($supplier->balance), 2) }}
                                    {{ $supplier->balance > 0 ? '(' . __('messages.We Owe Them') . ')' : ($supplier->balance < 0 ? '(' . __('messages.They Owe Us') . ')' : '(' . __('messages.Even') . ')') }}
                                </span>
                            </div>
                            
                            <!-- Quick Payment Form -->
                            <form id="quick-payment-form" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                @csrf
                                <div>
                                    <input type="number" name="amount" step="0.01" placeholder="{{ __('messages.Amount') }}"
                                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <select name="type" class="w-full border border-gray-300 rounded px-8 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="cash">{{ __('messages.Cash') }}</option>
                                        <option value="card">{{ __('messages.Card') }}</option>
                                        <option value="transfer">{{ __('messages.Transfer') }}</option>
                                        <option value="check">{{ __('messages.Check') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <input type="date" name="payment_date" value="{{ date('Y-m-d') }}"
                                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-3 rounded text-sm transition-colors">
                                        {{ __('messages.Add Payment') }}
                                    </button>
                                </div>
                                <div class="md:col-span-4">
                                    <input type="text" name="note" placeholder="{{ __('messages.Payment note (optional)') }}"
                                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="space-y-6">
                    <!-- Recent Purchase Bills -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">{{ __('messages.Recent Purchase Bills') }}</h3>
                        </div>
                        <div class="p-6">
                            @if($recentBills->count() > 0)
                                <div class="space-y-3">
                                    @foreach($recentBills as $bill)
                                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <div class="font-medium text-gray-900">{{ __('messages.Bill #') }}{{ $bill->id }}</div>
                                            <div class="text-sm text-gray-500">{{ $bill->purchase_date->format('M d, Y') }}</div>
                                            @if($bill->reference_number)
                                                <div class="text-xs text-gray-400">{{ __('messages.Ref:') }} {{ $bill->reference_number }}</div>
                                            @endif
                                        </div>
                                        <div class="text-right">
                                            <div class="font-medium text-gray-900">₪{{ number_format($bill->total_amount, 2) }}</div>
                                            <a href="{{ route('purchase-bills.show', $bill) }}" class="text-xs text-blue-600 hover:text-blue-800">{{ __('messages.View') }}</a>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                <div class="mt-4">
                                    <a href="{{ route('purchase-bills.index', ['supplier_id' => $supplier->id]) }}" class="text-sm text-blue-600 hover:text-blue-800">
                                        {{ __('messages.View all purchase bills →') }}
                                    </a>
                                </div>
                            @else
                                <div class="text-center text-gray-500">{{ __('messages.No purchase bills yet') }}</div>
                            @endif
                        </div>
                    </div>

                    <!-- Recent Payments -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">{{ __('messages.Recent Payments') }}</h3>
                        </div>
                        <div class="p-6" id="recent-payments">
                            @if($recentPayments->count() > 0)
                                <div class="space-y-3">
                                    @foreach($recentPayments as $payment)
                                    <div class="flex justify-between items-center p-3 border border-gray-200 rounded-lg" data-payment-id="{{ $payment->id }}">
                                        <div>
                                            <div class="font-medium text-gray-900">₪{{ number_format(abs($payment->amount), 2) }}</div>
                                            <div class="text-sm text-gray-500 capitalize">{{ $payment->type }} • {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</div>
                                            @if($payment->note)
                                                <div class="text-xs text-gray-400">{{ $payment->note }}</div>
                                            @endif
                                        </div>
                                        <div class="text-right flex items-center gap-2">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $payment->amount > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                                {{ $payment->amount > 0 ? __('messages.We Paid') : __('messages.They Paid') }}
                                            </span>
                                            <button type="button" class="delete-payment-btn text-red-600 hover:text-red-800 p-1" data-payment-id="{{ $payment->id }}" title="{{ __('messages.Delete Payment') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-gray-500">{{ __('messages.No payments yet') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle quick payment form
        document.getElementById('quick-payment-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonContent = submitButton.innerHTML;
            
            submitButton.disabled = true;
            submitButton.innerHTML = '{{ __('messages.Processing...') }}';
            
            try {
                const response = await fetch(`/suppliers/{{ $supplier->id }}/payments`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                if (response.ok) {
                    const result = await response.json();
                    
                    // Reset form
                    this.reset();
                    this.querySelector('input[name="payment_date"]').value = '{{ date("Y-m-d") }}';
                    
                    // Reload the page to show updated balance and payments
                    window.location.reload();
                    
                } else {
                    const errorData = await response.json();
                    alert(errorData.message || '{{ __('messages.Failed to add payment') }}');
                }
            } catch (error) {
                console.error('Payment error:', error);
                alert('{{ __('messages.Failed to add payment') }}');
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonContent;
            }
        });

        // Handle delete payment buttons
document.addEventListener('click', function(e) {
    if (e.target.closest('.delete-payment-btn')) {
        const button = e.target.closest('.delete-payment-btn');
        const paymentId = button.dataset.paymentId;
        
        if (confirm('{{ __('messages.Are you sure you want to delete this payment?') }}')) {
            deleteSupplierPayment(paymentId, button);
        }
    }
});

async function deleteSupplierPayment(paymentId, button) {
    const originalContent = button.innerHTML;
    
    // Show loading state
    button.disabled = true;
    button.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
    
    try {
        const response = await fetch(`/supplier-payments/${paymentId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });

        if (response.ok) {
            // Remove the payment row with animation
            const paymentRow = button.closest('[data-payment-id]');
            paymentRow.style.transition = 'opacity 0.3s, transform 0.3s';
            paymentRow.style.opacity = '0';
            paymentRow.style.transform = 'translateX(-100%)';
            
            setTimeout(() => {
                paymentRow.remove();
                // Reload page to update balance
                window.location.reload();
            }, 300);
            
        } else {
            const errorData = await response.json();
            alert(errorData.message || '{{ __('messages.Failed to delete payment') }}');
            button.disabled = false;
            button.innerHTML = originalContent;
        }
    } catch (error) {
        console.error('Delete error:', error);
        alert('{{ __('messages.Failed to delete payment') }}');
        button.disabled = false;
        button.innerHTML = originalContent;
    }
}
    </script>
</x-app-layout>