<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('messages.Customer Management')" :subtitle="__('receivables.statement_subtitle')">
            <a href="{{ route('customers.create') }}"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">
                {{ __('messages.Add Customer') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <div class="grid gap-4 md:grid-cols-3">
                <x-ui.stat :label="__('messages.Total Customers')" :value="$customers->total()" />
                <x-ui.stat :label="__('messages.Total Debt')" :value="'₪' . number_format(abs($customers->getCollection()->where('balance', '<', 0)->sum('balance')), 2)" />
                <x-ui.stat :label="__('receivables.open_bills')" :value="$customers->getCollection()->sum('open_bills_count')" />
            </div>

            <x-ui.card>
                <form method="GET" class="grid gap-4 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('messages.Search by name, phone, or ID...') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.payment_status') }}</label>
                        <select name="balance"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('messages.All Customers') }}</option>
                            <option value="debt" @selected(request('balance') === 'debt')>{{ __('messages.With Debt') }}</option>
                            <option value="credit" @selected(request('balance') === 'credit')>{{ __('messages.With Credit') }}</option>
                            <option value="settled" @selected(request('balance') === 'settled')>{{ __('receivables.settled') }}</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-3">
                        <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('messages.Filter') }}
                        </button>
                        <a href="{{ route('customers.index') }}"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            {{ __('messages.Clear') }}
                        </a>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('messages.Customer') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('messages.Contact') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('messages.Balance') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('receivables.last_bill') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('receivables.open_bills_count') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('messages.Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($customers as $customer)
                                @php
                                    $balanceTone = $customer->balance < 0 ? 'red' : ($customer->balance > 0 ? 'green' : 'gray');
                                    $balanceLabel = $customer->balance < 0 ? __('receivables.owes_us') : ($customer->balance > 0 ? __('receivables.credit') : __('receivables.settled'));
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ $customer->name }}</div>
                                        <div class="text-xs text-gray-500">#{{ $customer->id }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $customer->phone ?: '—' }}</td>
                                    <td class="px-4 py-3">
                                        <x-ui.badge :tone="$balanceTone">
                                            ₪{{ number_format(abs($customer->balance), 2) }} — {{ $balanceLabel }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        @if ($customer->last_bill)
                                            <div>#{{ $customer->last_bill->id }}</div>
                                            <div class="text-xs">₪{{ number_format($customer->last_bill->total_price, 2) }}</div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $customer->open_bills_count }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('customers.edit', $customer) }}"
                                                class="rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-200">
                                                {{ __('messages.Edit') }}
                                            </a>
                                            <a href="{{ route('customers.payments', $customer) }}"
                                                class="rounded-lg bg-blue-100 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-200">
                                                {{ __('messages.Payments') }}
                                            </a>
                                            <button type="button"
                                                class="rounded-lg bg-green-100 px-3 py-1.5 text-xs font-semibold text-green-700 hover:bg-green-200"
                                                onclick="openQuickPayment(@js([
                                                    'id' => $customer->id,
                                                    'name' => $customer->name,
                                                    'openBills' => $customer->open_bills->map(fn($row) => ['id' => $row['bill']->id, 'due' => $row['due']])->values(),
                                                ]))">
                                                {{ __('receivables.quick_payment') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12">
                                        <x-ui.empty :title="__('messages.Customer Management')" :text="__('messages.Add Customer')" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $customers->links('vendor.pagination.custom-light') }}
                </div>
            </x-ui.card>
        </div>
    </div>

    <dialog id="quick-payment-dialog" class="w-full max-w-xl rounded-2xl border border-gray-200 p-0 shadow-xl backdrop:bg-black/40">
        <form id="quick-payment-form" method="dialog" class="rounded-2xl bg-white p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900" id="quick-payment-title">{{ __('receivables.quick_payment') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('receivables.receive_payment') }}</p>
                </div>
                <button type="button" onclick="document.getElementById('quick-payment-dialog').close()" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('messages.Amount') }}</label>
                    <input id="quick-payment-amount" type="number" step="0.01" min="0.01"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.payment_method') }}</label>
                    <select id="quick-payment-type"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="cash">{{ __('messages.Cash') }}</option>
                        <option value="card">{{ __('messages.Card') }}</option>
                        <option value="transfer">{{ __('messages.Transfer') }}</option>
                        <option value="check">{{ __('messages.Check') }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.select_bill_optional') }}</label>
                    <select id="quick-payment-bill"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('receivables.general_account_payment') }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.note') }}</label>
                    <input id="quick-payment-note" type="text"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('quick-payment-dialog').close()"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    {{ __('messages.Cancel') }}
                </button>
                <button type="button" onclick="submitQuickPayment()"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    {{ __('receivables.receive_payment') }}
                </button>
            </div>
        </form>
    </dialog>

    @push('scripts')
        <script>
            let currentQuickCustomer = null;

            function openQuickPayment(customer) {
                currentQuickCustomer = customer;
                document.getElementById('quick-payment-title').textContent =
                    `{{ __('receivables.quick_payment') }} — ${customer.name}`;
                const billSelect = document.getElementById('quick-payment-bill');
                billSelect.innerHTML = `<option value="">{{ __('receivables.general_account_payment') }}</option>`;
                (customer.openBills || []).forEach((bill) => {
                    const option = document.createElement('option');
                    option.value = bill.id;
                    option.textContent = `#${bill.id} — ₪${Number(bill.due).toFixed(2)}`;
                    billSelect.appendChild(option);
                });
                document.getElementById('quick-payment-amount').value = '';
                document.getElementById('quick-payment-note').value = '';
                document.getElementById('quick-payment-dialog').showModal();
            }

            async function submitQuickPayment() {
                if (!currentQuickCustomer) return;

                const response = await SP.fetchJson(`/customers/${currentQuickCustomer.id}/quick-payments`, {
                    method: 'POST',
                    body: {
                        amount: document.getElementById('quick-payment-amount').value,
                        type: document.getElementById('quick-payment-type').value,
                        note: document.getElementById('quick-payment-note').value,
                        bill_id: document.getElementById('quick-payment-bill').value || null,
                    }
                });

                if (response.success) {
                    SP.toast(response.message, 'success');
                    document.getElementById('quick-payment-dialog').close();
                    window.location.reload();
                }
            }
        </script>
    @endpush
</x-app-layout>
