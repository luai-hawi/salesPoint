<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('messages.Payment History')" :subtitle="__('receivables.statement_subtitle')">
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('customers.index') }}"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    {{ __('messages.Back to Customers') }}
                </a>
                <a href="{{ route('customers.statement', $customer) . '?' . http_build_query(request()->query()) }}"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    {{ __('receivables.print_statement') }}
                </a>
            </div>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <div class="grid gap-4 md:grid-cols-4">
                <x-ui.stat :label="__('messages.Customer')" :value="$customer->name" />
                <x-ui.stat :label="__('messages.Balance')" :value="'₪' . number_format($customer->balance, 2)" />
                <x-ui.stat :label="__('messages.Total Payments')" :value="$payments->count()" />
                <x-ui.stat :label="__('receivables.open_bills')" :value="$openBills->count()" />
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-1">
                    <x-ui.card>
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ __('receivables.open_bills') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('receivables.select_bill_optional') }}</p>
                            </div>
                            <x-ui.badge tone="amber">{{ $openBills->count() }}</x-ui.badge>
                        </div>
                        <div class="mt-4 space-y-3">
                            @forelse ($openBills as $row)
                                <div class="rounded-xl border border-gray-200 p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-semibold text-gray-900">#{{ $row['bill']->id }}</div>
                                            <div class="text-xs text-gray-500">{{ $row['bill']->created_at->format('Y-m-d H:i') }}</div>
                                        </div>
                                        <button type="button"
                                            onclick="selectBillForPayment({{ $row['bill']->id }}, {{ $row['due'] }})"
                                            class="rounded-lg bg-indigo-100 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-200">
                                            {{ __('receivables.receive_payment') }}
                                        </button>
                                    </div>
                                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-gray-600">
                                        <div>{{ __('receivables.bill_total') }}<br><span class="font-semibold text-gray-900">₪{{ number_format($row['total'], 2) }}</span></div>
                                        <div>{{ __('receivables.paid') }}<br><span class="font-semibold text-green-600">₪{{ number_format($row['paid'], 2) }}</span></div>
                                        <div>{{ __('receivables.due') }}<br><span class="font-semibold text-red-600">₪{{ number_format($row['due'], 2) }}</span></div>
                                    </div>
                                </div>
                            @empty
                                <x-ui.empty :title="__('receivables.open_bills')" :text="__('receivables.no_open_bills')" />
                            @endforelse
                        </div>
                    </x-ui.card>

                    <x-ui.card>
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('messages.Add New Payment') }}</h3>
                        <form method="POST" action="{{ route('customers.payments.store', $customer) }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('messages.Amount') }}</label>
                                <input id="payment-amount" type="number" name="amount" step="0.01" required
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.select_bill_optional') }}</label>
                                <select id="payment-bill-id" name="bill_id"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('receivables.general_account_payment') }}</option>
                                    @foreach ($openBills as $row)
                                        <option value="{{ $row['bill']->id }}">#{{ $row['bill']->id }} — ₪{{ number_format($row['due'], 2) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.payment_method') }}</label>
                                    <select name="type"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="cash">{{ __('messages.Cash') }}</option>
                                        <option value="card">{{ __('messages.Card') }}</option>
                                        <option value="transfer">{{ __('messages.Transfer') }}</option>
                                        <option value="check">{{ __('messages.Check') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('messages.Date') }}</label>
                                    <input type="date" name="payment_date" value="{{ now()->toDateString() }}"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.note') }}</label>
                                <input type="text" name="note"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <button type="submit"
                                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                {{ __('receivables.receive_payment') }}
                            </button>
                        </form>
                    </x-ui.card>
                </div>

                <div class="space-y-6 xl:col-span-2">
                    <x-ui.card>
                        <form method="GET" class="grid gap-4 md:grid-cols-4">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.filter_from') }}</label>
                                <input type="date" name="from" value="{{ request('from') }}"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.filter_to') }}</label>
                                <input type="date" name="to" value="{{ request('to') }}"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.filter_type') }}</label>
                                <select name="type"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('receivables.all_statuses') }}</option>
                                    <option value="bill_charge" @selected(request('type') === 'bill_charge')>{{ __('receivables.bill_charge') }}</option>
                                    <option value="bill_payment" @selected(request('type') === 'bill_payment')>{{ __('receivables.bill_payment') }}</option>
                                    <option value="payment" @selected(request('type') === 'payment')>{{ __('receivables.general_payment') }}</option>
                                    <option value="adjustment" @selected(request('type') === 'adjustment')>{{ __('receivables.adjustment') }}</option>
                                    <option value="opening_balance" @selected(request('type') === 'opening_balance')>{{ __('receivables.opening_balance') }}</option>
                                </select>
                            </div>
                            <div class="flex items-end gap-3">
                                <button type="submit"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                    {{ __('messages.Filter') }}
                                </button>
                                <a href="{{ route('customers.payments', $customer) }}"
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
                                        <th class="px-4 py-3 text-start">{{ __('receivables.date') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('receivables.type') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('messages.Amount') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('receivables.running_balance') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('receivables.bill_reference') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('receivables.note') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('receivables.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @forelse ($payments as $payment)
                                        @php
                                            $kind = \App\Services\CustomerLedger::kindForRow($payment);
                                            $kindLabel = match ($kind) {
                                                \App\Services\CustomerLedger::KIND_BILL_CHARGE => __('receivables.bill_charge'),
                                                \App\Services\CustomerLedger::KIND_BILL_PAYMENT => __('receivables.bill_payment'),
                                                \App\Services\CustomerLedger::KIND_OPENING => __('receivables.opening_balance'),
                                                \App\Services\CustomerLedger::KIND_ADJUSTMENT => __('receivables.adjustment'),
                                                default => __('receivables.general_payment'),
                                            };
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3 text-gray-600">{{ $payment->created_at->format('Y-m-d H:i') }}</td>
                                            <td class="px-4 py-3">{{ $kindLabel }}</td>
                                            <td class="px-4 py-3 font-semibold {{ $payment->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                ₪{{ number_format($payment->amount, 2) }}
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">₪{{ number_format($runningBalance[$payment->id] ?? 0, 2) }}</td>
                                            <td class="px-4 py-3">
                                                @if ($payment->bill_id)
                                                    <button type="button" onclick="previewBill({{ $payment->bill_id }})"
                                                        class="rounded-lg bg-blue-100 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-200">
                                                        #{{ $payment->bill_id }}
                                                    </button>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">{{ $payment->note ?: '—' }}</td>
                                            <td class="px-4 py-3">
                                                <div class="space-y-2">
                                                    @if ($kind === \App\Services\CustomerLedger::KIND_BILL_CHARGE)
                                                        <p class="text-xs text-gray-500">{{ __('receivables.validation.bill_charge_cannot_be_edited') }}</p>
                                                    @else
                                                        <form method="POST" action="{{ route('payments.update', $payment) }}" class="grid gap-2 md:grid-cols-3">
                                                            @csrf
                                                            @method('PUT')
                                                            <input type="number" step="0.01" name="amount" value="{{ $payment->amount }}"
                                                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                                            <select name="type"
                                                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                                                @foreach (['cash','card','transfer','check'] as $method)
                                                                    <option value="{{ $method }}" @selected($payment->type === $method)>{{ __('messages.' . ucfirst($method)) }}</option>
                                                                @endforeach
                                                            </select>
                                                            <input type="text" name="note" value="{{ $payment->note }}"
                                                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                                            <button type="submit"
                                                                class="rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-200 md:col-span-3">
                                                                {{ __('messages.Save') }}
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="{{ route('payments.destroy', $payment) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                data-confirm="{{ __('messages.Are you sure you want to delete this payment?') }}"
                                                                class="rounded-lg bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-200">
                                                                {{ __('messages.Delete') }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-4 py-12">
                                                <x-ui.empty :title="__('messages.Payment History')" :text="__('receivables.no_open_bills')" />
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-ui.card>
                </div>
            </div>
        </div>
    </div>

    <dialog id="bill-preview-dialog" class="w-full max-w-3xl rounded-2xl border border-gray-200 p-0 shadow-xl backdrop:bg-black/40">
        <div class="rounded-2xl bg-white p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('receivables.bill_preview') }}</h3>
                <button type="button" onclick="document.getElementById('bill-preview-dialog').close()" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div id="bill-preview-content" class="mt-4 text-sm text-gray-600"></div>
        </div>
    </dialog>

    @push('scripts')
        <script>
            function selectBillForPayment(id, due) {
                document.getElementById('payment-bill-id').value = id;
                document.getElementById('payment-amount').value = Number(due).toFixed(2);
                document.getElementById('payment-amount').focus();
            }

            async function previewBill(id) {
                const response = await fetch(`/bills/${id}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                const bill = data.bill;
                const content = document.getElementById('bill-preview-content');
                content.textContent = '';

                const container = document.createElement('div');
                container.className = 'space-y-3';

                const addLine = (text, className = '') => {
                    const div = document.createElement('div');
                    if (className) div.className = className;
                    div.textContent = text;
                    container.appendChild(div);
                };

                addLine(`#${bill.id}`, 'font-semibold text-gray-900');
                addLine(`{{ __('messages.Amount') }}: ₪${Number(bill.total_price).toFixed(2)}`);
                addLine(`{{ __('receivables.paid') }}: ₪${Number(data.ledger_summary?.paid || 0).toFixed(2)}`);
                addLine(`{{ __('receivables.remaining') }}: ₪${Number(data.ledger_summary?.due || 0).toFixed(2)}`);

                const buildList = (title, rows) => {
                    const wrapper = document.createElement('div');
                    const heading = document.createElement('div');
                    heading.className = 'mb-1 font-semibold text-gray-900';
                    heading.textContent = title;
                    wrapper.appendChild(heading);
                    const list = document.createElement('ul');
                    list.className = 'list-disc space-y-1 ps-5';
                    if (rows.length === 0) {
                        const li = document.createElement('li');
                        li.textContent = '—';
                        list.appendChild(li);
                    } else {
                        rows.forEach((text) => {
                            const li = document.createElement('li');
                            li.textContent = text;
                            list.appendChild(li);
                        });
                    }
                    wrapper.appendChild(list);
                    container.appendChild(wrapper);
                };

                buildList(`{{ __('receivables.items') }}`, (data.items || []).map((item) =>
                    `${item.name} — ${item.quantity} × ₪${Number(item.selling_price).toFixed(2)}`
                ));
                buildList(`{{ __('receivables.ledger_rows') }}`, (data.ledger_rows || []).map((row) =>
                    `${row.kind_label || row.kind} — ₪${Number(row.amount).toFixed(2)} — ${row.created_at || ''}`
                ));

                const link = document.createElement('a');
                link.href = `/bills/${bill.id}`;
                link.className = 'inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700';
                link.textContent = `{{ __('receivables.open_bill') }}`;
                container.appendChild(link);
                content.appendChild(container);
                document.getElementById('bill-preview-dialog').showModal();
            }
        </script>
    @endpush
</x-app-layout>
