<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('payables.titles.edit_supplier')" :subtitle="$supplier->name">
            <a href="{{ route('suppliers.index') }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('payables.actions.back_to_suppliers') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            @php
                $balance = (float) $supplier->balance;
                $balanceTone = $balance > 0 ? 'red' : ($balance < 0 ? 'green' : 'gray');
                $balanceLabel = $balance > 0 ? __('payables.statuses.we_owe') : ($balance < 0 ? __('payables.statuses.supplier_owes') : __('payables.statuses.settled'));
            @endphp

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-ui.stat :label="__('payables.fields.balance')" :value="'₪' . number_format(abs($balance), 2)" :hint="$balanceLabel" :tone="$balanceTone" />
                <x-ui.stat :label="__('payables.sections.open_purchase_bills')" :value="$openBills->count()" />
                <x-ui.stat :label="__('payables.sections.payments')" :value="$payments->total()" />
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-1">
                    <x-ui.card :title="__('payables.sections.supplier_details')">
                        <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="space-y-4">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="name" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.name') }}
                                </label>
                                <input id="name" name="name" type="text" required value="{{ old('name', $supplier->name) }}"
                                    @disabled($supplier->system_key === \App\Services\SupplierLedger::WALK_IN_KEY)
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-100">
                            </div>

                            <div>
                                <label for="phone" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.phone') }}
                                </label>
                                <input id="phone" name="phone" type="text" value="{{ old('phone', $supplier->phone) }}"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label for="email" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.email') }}
                                </label>
                                <input id="email" name="email" type="email" value="{{ old('email', $supplier->email) }}"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label for="address" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.address') }}
                                </label>
                                <textarea id="address" name="address" rows="3"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">{{ old('address', $supplier->address) }}</textarea>
                            </div>

                            <div>
                                <label for="notes" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.notes') }}
                                </label>
                                <textarea id="notes" name="notes" rows="4"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">{{ old('notes', $supplier->notes) }}</textarea>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2">
                                @if ($supplier->system_key === \App\Services\SupplierLedger::WALK_IN_KEY)
                                    <x-ui.badge tone="blue">{{ __('payables.statuses.system_supplier') }}</x-ui.badge>
                                @endif
                                <button type="submit"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                    {{ __('payables.actions.save_supplier') }}
                                </button>
                            </div>
                        </form>
                    </x-ui.card>

                    <x-ui.card :title="__('payables.sections.payment_block')">
                        <form method="POST" action="{{ route('suppliers.payments.store', $supplier) }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="amount" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.amount') }}
                                </label>
                                <input id="amount" name="amount" type="number" step="0.01" required
                                    value="{{ old('amount') }}"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="type" class="mb-1 block text-sm font-medium text-gray-700">
                                        {{ __('payables.fields.method') }}
                                    </label>
                                    <select id="type" name="type"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                        @foreach (['cash', 'card', 'transfer', 'check'] as $method)
                                            <option value="{{ $method }}" @selected(old('type', 'cash') === $method)>
                                                {{ __('payables.methods.' . $method) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="payment_date" class="mb-1 block text-sm font-medium text-gray-700">
                                        {{ __('payables.fields.payment_date') }}
                                    </label>
                                    <input id="payment_date" name="payment_date" type="date"
                                        value="{{ old('payment_date', now()->toDateString()) }}"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>
                            <div>
                                <label for="purchase_bill_id" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.apply_to_bill') }}
                                </label>
                                <select id="purchase_bill_id" name="purchase_bill_id"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                    <option value="">{{ __('payables.placeholders.no_bill_link') }}</option>
                                    @foreach ($openBills as $openBill)
                                        <option value="{{ $openBill['bill']->id }}" @selected(old('purchase_bill_id') == $openBill['bill']->id)>
                                            #{{ $openBill['bill']->id }} — ₪{{ number_format($openBill['due'], 2) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="note" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.payment_note') }}
                                </label>
                                <textarea id="note" name="note" rows="3"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">{{ old('note') }}</textarea>
                            </div>
                            <button type="submit"
                                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                {{ __('payables.actions.record_payment') }}
                            </button>
                        </form>
                    </x-ui.card>

                    <x-ui.card :title="__('payables.sections.print_options')">
                        <form method="GET" action="{{ route('suppliers.print-report', $supplier) }}" target="_blank"
                            class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="date_from" class="mb-1 block text-sm font-medium text-gray-700">
                                        {{ __('payables.fields.date_from') }}
                                    </label>
                                    <input id="date_from" name="date_from" type="date"
                                        value="{{ request('date_from', now()->startOfMonth()->toDateString()) }}"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label for="date_to" class="mb-1 block text-sm font-medium text-gray-700">
                                        {{ __('payables.fields.date_to') }}
                                    </label>
                                    <input id="date_to" name="date_to" type="date"
                                        value="{{ request('date_to', now()->toDateString()) }}"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>
                            <div>
                                <label for="report_type" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.report_type') }}
                                </label>
                                <select id="report_type" name="report_type"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                    @foreach (['both', 'bills', 'payments'] as $reportType)
                                        <option value="{{ $reportType }}">
                                            {{ __('payables.report_types.' . $reportType) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                {{ __('payables.actions.print_statement') }}
                            </button>
                        </form>
                    </x-ui.card>
                </div>

                <div class="space-y-6 xl:col-span-2">
                    <x-ui.card :title="__('payables.sections.open_purchase_bills')">
                        @if ($openBills->isEmpty())
                            <x-ui.empty :title="__('payables.messages.no_open_bills')" />
                        @else
                            <div class="space-y-3">
                                @foreach ($openBills as $openBill)
                                    <div class="rounded-xl border border-gray-200 p-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <a href="{{ route('purchase-bills.show', $openBill['bill']) }}"
                                                        class="font-semibold text-indigo-600 hover:text-indigo-800">
                                                        #{{ $openBill['bill']->id }}
                                                    </a>
                                                    <x-ui.badge :tone="$openBill['status'] === 'paid' ? 'green' : ($openBill['status'] === 'partial' ? 'amber' : 'red')">
                                                        {{ __('payables.statuses.' . $openBill['status']) }}
                                                    </x-ui.badge>
                                                    @if ($openBill['bill']->source === 'stock_intake')
                                                        <x-ui.badge tone="blue">{{ __('payables.statuses.from_stock_entry') }}</x-ui.badge>
                                                    @endif
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">
                                                    {{ optional($openBill['bill']->purchase_date)->format('Y-m-d') }}
                                                </p>
                                            </div>
                                            <div class="grid grid-cols-3 gap-3 text-sm">
                                                <div>
                                                    <div class="text-xs text-gray-500">{{ __('payables.fields.amount') }}</div>
                                                    <div class="font-semibold text-gray-900">₪{{ number_format($openBill['total'], 2) }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-500">{{ __('payables.actions.record_payment') }}</div>
                                                    <div class="font-semibold text-gray-900">₪{{ number_format($openBill['paid'], 2) }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-500">{{ __('payables.fields.remaining') }}</div>
                                                    <div class="font-semibold text-red-600">₪{{ number_format($openBill['due'], 2) }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </x-ui.card>

                    <x-ui.card :title="__('payables.sections.recent_bills')">
                        @if ($recentBills->isEmpty())
                            <x-ui.empty :title="__('payables.messages.no_bills')" />
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.bill') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.purchase_date') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.amount') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.remaining') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        @foreach ($recentBills as $bill)
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <a href="{{ route('purchase-bills.show', $bill) }}"
                                                        class="font-semibold text-indigo-600 hover:text-indigo-800">
                                                        #{{ $bill->id }}
                                                    </a>
                                                </td>
                                                <td class="px-4 py-3 text-gray-700">{{ optional($bill->purchase_date)->format('Y-m-d') }}</td>
                                                <td class="px-4 py-3 text-gray-900">₪{{ number_format((float) $bill->total_amount, 2) }}</td>
                                                <td class="px-4 py-3 text-gray-900">₪{{ number_format($bill->payables_summary['due'], 2) }}</td>
                                                <td class="px-4 py-3">
                                                    <x-ui.badge :tone="$bill->payables_summary['status'] === 'paid' ? 'green' : ($bill->payables_summary['status'] === 'partial' ? 'amber' : 'red')">
                                                        {{ __('payables.statuses.' . $bill->payables_summary['status']) }}
                                                    </x-ui.badge>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-ui.card>

                    <x-ui.card :title="__('payables.sections.payment_history')">
                        <form method="GET" action="{{ route('suppliers.edit', $supplier) }}"
                            class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <label for="payment_kind" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.type') }}
                                </label>
                                <select id="payment_kind" name="payment_kind"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                    <option value="">{{ __('payables.placeholders.all_payment_kinds') }}</option>
                                    <option value="cash_movement" @selected(request('payment_kind') === 'cash_movement')>{{ __('payables.filters.cash_movement') }}</option>
                                    @foreach (['payment', 'bill_payment', 'opening_balance', 'refund'] as $kind)
                                        <option value="{{ $kind }}" @selected(request('payment_kind') === $kind)>
                                            {{ __('payables.kinds.' . $kind) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="filter_date_from" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.date_from') }}
                                </label>
                                <input id="filter_date_from" name="date_from" type="date" value="{{ request('date_from') }}"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="filter_date_to" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.date_to') }}
                                </label>
                                <input id="filter_date_to" name="date_to" type="date" value="{{ request('date_to') }}"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div class="flex items-end gap-2">
                                <button type="submit"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                    {{ __('payables.actions.apply') }}
                                </button>
                                <a href="{{ route('suppliers.edit', $supplier) }}"
                                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    {{ __('payables.actions.clear') }}
                                </a>
                            </div>
                        </form>

                        @if ($payments->count() === 0)
                            <x-ui.empty :title="__('payables.messages.no_payments')" />
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="px-4 py-3 text-start">{{ __('payables.statement.date') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.type') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.bill') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.amount') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.running_balance') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.notes') }}</th>
                                            <th class="px-4 py-3 text-end">{{ __('payables.actions.delete') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        @foreach ($payments as $payment)
                                            @php
                                                $kindKey = match (\App\Services\SupplierLedger::kindForRow($payment)) {
                                                    \App\Services\SupplierLedger::KIND_BILL_PAYMENT => 'bill_payment',
                                                    \App\Services\SupplierLedger::KIND_OPENING => 'opening_balance',
                                                    \App\Services\SupplierLedger::KIND_REFUND => 'refund',
                                                    default => 'payment',
                                                };
                                                $amountTone = (float) $payment->amount >= 0 ? 'red' : 'green';
                                            @endphp
                                            <tr class="align-top">
                                                <td class="px-4 py-3 text-gray-700">{{ optional($payment->payment_date)->format('Y-m-d') }}</td>
                                                <td class="px-4 py-3">
                                                    <x-ui.badge :tone="$kindKey === 'opening_balance' ? 'blue' : ($kindKey === 'refund' ? 'green' : 'indigo')">
                                                        {{ __('payables.kinds.' . $kindKey) }}
                                                    </x-ui.badge>
                                                    <div class="mt-1 text-xs text-gray-500">{{ __('payables.methods.' . $payment->type) }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-gray-700">
                                                    @if ($payment->purchaseBill)
                                                        <a href="{{ route('purchase-bills.show', $payment->purchaseBill) }}"
                                                            class="font-semibold text-indigo-600 hover:text-indigo-800">
                                                            #{{ $payment->purchaseBill->id }}
                                                        </a>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 font-semibold text-gray-900">
                                                    <x-ui.badge :tone="$amountTone">₪{{ number_format(abs((float) $payment->amount), 2) }}</x-ui.badge>
                                                </td>
                                                <td class="px-4 py-3 text-gray-900">
                                                    @if ($payment->running_balance !== null)
                                                        ₪{{ number_format(abs((float) $payment->running_balance), 2) }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-gray-600">{{ $payment->note ?: '—' }}</td>
                                                <td class="px-4 py-3 text-end">
                                                    <form method="POST" action="{{ route('supplier-payments.destroy', $payment) }}"
                                                        onsubmit="return confirm('{{ __('payables.messages.confirm_delete_payment') }}')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-800">
                                                            {{ __('payables.actions.delete') }}
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="pt-4">
                                {{ $payments->links() }}
                            </div>
                        @endif
                    </x-ui.card>

                    <x-ui.card :title="__('payables.sections.statement_preview')">
                        @if ($statementPreview->isEmpty())
                            <x-ui.empty :title="__('payables.messages.no_statement_rows')" />
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="px-4 py-3 text-start">{{ __('payables.statement.date') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.statement.description') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.statement.reference') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.statement.increase') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.statement.decrease') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.running_balance') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        @foreach ($statementPreview as $row)
                                            <tr>
                                                <td class="px-4 py-3 text-gray-700">{{ $row['date']->format('Y-m-d') }}</td>
                                                <td class="px-4 py-3 text-gray-900">{{ $row['description'] }}</td>
                                                <td class="px-4 py-3 text-gray-700">{{ $row['reference'] ?: '—' }}</td>
                                                <td class="px-4 py-3 text-green-700">
                                                    {{ $row['increase'] > 0 ? '₪' . number_format($row['increase'], 2) : '—' }}
                                                </td>
                                                <td class="px-4 py-3 text-red-700">
                                                    {{ $row['decrease'] > 0 ? '₪' . number_format($row['decrease'], 2) : '—' }}
                                                </td>
                                                <td class="px-4 py-3 font-semibold text-gray-900">₪{{ number_format(abs($row['running_balance']), 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-ui.card>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
