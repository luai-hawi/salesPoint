<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('payables.titles.purchase_bill', ['id' => $purchaseBill->id])" :subtitle="__('payables.subtitles.purchase_bill')">
            <a href="{{ route('purchase-bills.index') }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('payables.actions.back_to_bills') }}
            </a>
            <a href="{{ route('purchase-bills.edit', $purchaseBill) }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('payables.actions.edit') }}
            </a>
            <a href="{{ route('purchase-bills.print', $purchaseBill) }}" target="_blank"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ __('payables.actions.print_bill') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            @php
                $statusTone = $summary['status'] === 'paid' ? 'green' : ($summary['status'] === 'partial' ? 'amber' : 'red');
            @endphp

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <x-ui.stat :label="__('payables.fields.amount')" :value="'₪' . number_format($summary['total'], 2)" tone="blue" />
                <x-ui.stat :label="__('payables.actions.record_payment')" :value="'₪' . number_format($summary['paid'], 2)" tone="green" />
                <x-ui.stat :label="__('payables.fields.remaining')" :value="'₪' . number_format($summary['due'], 2)" tone="red" />
                <x-ui.stat :label="__('payables.fields.status')" :value="__('payables.statuses.' . $summary['status'])" :hint="$summary['overpaid'] > 0 ? __('payables.messages.overpaid_credit') . ': ₪' . number_format($summary['overpaid'], 2) : null" :tone="$statusTone" />
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    <x-ui.card :title="__('payables.sections.bill_information')">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="rounded-lg bg-gray-50 p-4">
                                <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('payables.fields.supplier') }}</div>
                                <div class="mt-1 text-lg font-semibold text-gray-900">{{ $purchaseBill->supplier?->name }}</div>
                                @if ($purchaseBill->supplier?->phone)
                                    <div class="mt-1 text-sm text-gray-600">{{ $purchaseBill->supplier->phone }}</div>
                                @endif
                            </div>
                            <div class="rounded-lg bg-gray-50 p-4">
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('payables.fields.purchase_date') }}</div>
                                        <div class="mt-1 font-semibold text-gray-900">{{ optional($purchaseBill->purchase_date)->format('Y-m-d') }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('payables.fields.created_by') }}</div>
                                        <div class="mt-1 font-semibold text-gray-900">{{ $purchaseBill->creator?->name ?: '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('payables.fields.reference_number') }}</div>
                                        <div class="mt-1 font-semibold text-gray-900">{{ $purchaseBill->reference_number ?: '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('payables.fields.source') }}</div>
                                        <div class="mt-1">
                                            @if ($purchaseBill->source === 'stock_intake')
                                                <x-ui.badge tone="blue">{{ __('payables.statuses.from_stock_entry') }}</x-ui.badge>
                                            @else
                                                <x-ui.badge tone="gray">—</x-ui.badge>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($purchaseBill->notes)
                            <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700">
                                {{ $purchaseBill->notes }}
                            </div>
                        @endif
                    </x-ui.card>

                    <x-ui.card :title="__('payables.sections.products')">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3 text-start">{{ __('payables.fields.products') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('messages.Quantity') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('messages.Unit Cost') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('payables.fields.amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach ($purchaseBill->products as $product)
                                        <tr>
                                            <td class="px-4 py-3 font-medium text-gray-900">{{ $product->name }}</td>
                                            <td class="px-4 py-3 text-gray-700">{{ number_format((float) $product->pivot->quantity, 2) }}</td>
                                            <td class="px-4 py-3 text-gray-700">₪{{ number_format((float) $product->pivot->unit_cost, 2) }}</td>
                                            <td class="px-4 py-3 font-semibold text-gray-900">₪{{ number_format((float) $product->pivot->total_cost, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-ui.card>

                    <x-ui.card :title="__('payables.sections.bill_payments')">
                        @if ($purchaseBill->payments->isEmpty())
                            <x-ui.empty :title="__('payables.messages.no_payments')" />
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.payment_date') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.method') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.amount') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('payables.fields.notes') }}</th>
                                            <th class="px-4 py-3 text-end">{{ __('payables.actions.delete') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        @foreach ($purchaseBill->payments as $payment)
                                            <tr>
                                                <td class="px-4 py-3 text-gray-700">{{ optional($payment->payment_date)->format('Y-m-d') }}</td>
                                                <td class="px-4 py-3 text-gray-700">{{ __('payables.methods.' . $payment->type) }}</td>
                                                <td class="px-4 py-3 font-semibold text-gray-900">₪{{ number_format((float) $payment->amount, 2) }}</td>
                                                <td class="px-4 py-3 text-gray-600">{{ $payment->note ?: '—' }}</td>
                                                <td class="px-4 py-3 text-end">
                                                    <form method="POST"
                                                        action="{{ route('purchase-bills.payments.destroy', [$purchaseBill, $payment]) }}"
                                                        onsubmit="return confirm('{{ __('payables.messages.confirm_delete_bill_payment') }}')">
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
                        @endif
                    </x-ui.card>
                </div>

                <div class="space-y-6 xl:col-span-1">
                    <x-ui.card :title="__('payables.sections.payment_block')">
                        <form method="POST" action="{{ route('purchase-bills.payments.store', $purchaseBill) }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="amount" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.amount') }}
                                </label>
                                <input id="amount" name="amount" type="number" step="0.01" min="0.01" required
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
                                        value="{{ old('payment_date', optional($purchaseBill->purchase_date)->format('Y-m-d') ?? now()->toDateString()) }}"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>
                            <div>
                                <label for="note" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('payables.fields.payment_note') }}
                                </label>
                                <textarea id="note" name="note" rows="3"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">{{ old('note') }}</textarea>
                            </div>
                            <button type="submit" @disabled($summary['due'] <= 0)
                                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-300">
                                {{ __('payables.actions.pay_bill') }}
                            </button>
                        </form>
                    </x-ui.card>

                    <x-ui.card :title="__('payables.sections.bill_summary')">
                        <div class="space-y-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">{{ __('payables.fields.amount') }}</span>
                                <span class="font-semibold text-gray-900">₪{{ number_format($summary['total'], 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">{{ __('payables.actions.record_payment') }}</span>
                                <span class="font-semibold text-gray-900">₪{{ number_format($summary['paid'], 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">{{ __('payables.fields.remaining') }}</span>
                                <span class="font-semibold text-red-600">₪{{ number_format($summary['due'], 2) }}</span>
                            </div>
                            @if ($summary['overpaid'] > 0)
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">{{ __('payables.messages.overpaid_credit') }}</span>
                                    <span class="font-semibold text-green-600">₪{{ number_format($summary['overpaid'], 2) }}</span>
                                </div>
                            @endif
                            <div class="pt-2">
                                <x-ui.badge :tone="$statusTone">{{ __('payables.statuses.' . $summary['status']) }}</x-ui.badge>
                            </div>
                        </div>
                    </x-ui.card>

                    <x-ui.card>
                        <div class="space-y-3">
                            <a href="{{ route('purchase-bills.create', ['duplicate' => $purchaseBill->id]) }}"
                                class="block rounded-lg border border-gray-300 bg-white px-4 py-2 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                {{ __('payables.actions.duplicate') }}
                            </a>
                            <form method="POST" action="{{ route('purchase-bills.destroy', $purchaseBill) }}"
                                onsubmit="return confirm('{{ __('payables.messages.confirm_delete_bill') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                    {{ __('payables.actions.delete_bill') }}
                                </button>
                            </form>
                        </div>
                    </x-ui.card>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
