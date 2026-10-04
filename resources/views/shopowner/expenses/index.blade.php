@php
    $supplierShortcut = Route::has('payments-receipts.index') ? route('payments-receipts.index') : (Route::has('suppliers.index') ? route('suppliers.index') : null);
    $staffShortcut = Route::has('shopowner.employees.index') && auth()->user()->canAccessFeature('hr') ? route('shopowner.employees.index') : null;
    $categoryKeys = ['rent', 'utilities', 'transport', 'marketing', 'maintenance', 'supplies', 'taxes', 'other'];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('finance.expenses.title')" :subtitle="__('finance.expenses.subtitle')">
            <a href="{{ route('shopowner.expenses.export', request()->query()) }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('finance.common.export') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <div class="font-semibold">{{ __('finance.expenses.guardrail_title') }}</div>
                    <p class="mt-1">{{ __('finance.expenses.guardrail_supplier') }}</p>
                    <p class="mt-1">{{ __('finance.expenses.guardrail_salary') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($supplierShortcut)
                            <a href="{{ $supplierShortcut }}" class="rounded-lg border border-amber-300 bg-white px-3 py-2 font-semibold text-amber-900 hover:bg-amber-100">
                                {{ __('finance.expenses.pay_supplier') }}
                            </a>
                        @endif
                        @if ($staffShortcut)
                            <a href="{{ $staffShortcut }}" class="rounded-lg border border-amber-300 bg-white px-3 py-2 font-semibold text-amber-900 hover:bg-amber-100">
                                {{ __('finance.expenses.staff_payments_shortcut') }}
                            </a>
                        @endif
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card>
                <form method="GET" action="{{ route('shopowner.expenses.index') }}" class="grid gap-4 lg:grid-cols-5">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.from') }}</label>
                        <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.to') }}</label>
                        <input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.category') }}</label>
                        <select name="category" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">{{ __('finance.common.all') }}</option>
                            <option value="uncategorised" @selected(request('category') === 'uncategorised')>{{ __('finance.expenses.uncategorised') }}</option>
                            @foreach ($categoryKeys as $key)
                                <option value="{{ $key }}" @selected(request('category') === $key)>{{ __('finance.expenses.categories.' . $key) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.search') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}" class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('finance.common.filter') }}</button>
                        <a href="{{ route('shopowner.expenses.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('finance.common.all') }}</a>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card :title="$editExpense ? __('finance.common.update') : __('finance.common.save')">
                <form method="POST" action="{{ $editExpense ? route('shopowner.expenses.update', $editExpense) : route('shopowner.expenses.store') }}"
                    x-data="expenseGuardrails(@js($supplierNames), @js($recentSupplierPaymentAmounts), @js($outstandingSupplierBalances), @js($supplierShortcut), @js($staffShortcut))"
                    class="space-y-4">
                    @csrf
                    @if ($editExpense)
                        @method('PUT')
                    @endif
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.title') }}</label>
                            <input type="text" name="title" x-model="title" value="{{ old('title', $editExpense?->title) }}" required class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.category') }}</label>
                            <select name="category" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">{{ __('finance.expenses.uncategorised') }}</option>
                                @foreach ($categoryKeys as $key)
                                    <option value="{{ $key }}" @selected(old('category', $editExpense?->category) === $key)>{{ __('finance.expenses.categories.' . $key) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.amount') }}</label>
                            <input type="number" step="0.01" min="0" name="amount" x-model="amount" value="{{ old('amount', $editExpense?->amount) }}" required class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.date') }}</label>
                            <input type="date" name="expense_date" value="{{ old('expense_date', optional($editExpense?->expense_date)->format('Y-m-d') ?? now()->toDateString()) }}" required class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.notes') }}</label>
                            <textarea name="notes" rows="3" class="w-full rounded-lg border-gray-300 text-sm">{{ old('notes', $editExpense?->notes) }}</textarea>
                        </div>
                    </div>

                    <div class="space-y-2" data-warning-supplier="{{ __('finance.expenses.warning_supplier_match') }}" data-warning-amount="{{ __('finance.expenses.warning_amount_match') }}" data-warning-salary="{{ __('finance.expenses.warning_salary_match') }}">
                        <template x-if="supplierWarning">
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                <span x-text="supplierWarning"></span>
                                <template x-if="supplierShortcut"><a :href="supplierShortcut" class="ms-2 font-semibold underline">{{ __('finance.expenses.pay_supplier') }}</a></template>
                            </div>
                        </template>
                        <template x-if="amountWarning">
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" x-text="amountWarning"></div>
                        </template>
                        <template x-if="salaryWarning">
                            <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                                <span x-text="salaryWarning"></span>
                                <template x-if="staffShortcut"><a :href="staffShortcut" class="ms-2 font-semibold underline">{{ __('finance.expenses.staff_payments_shortcut') }}</a></template>
                            </div>
                        </template>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ $editExpense ? __('finance.common.update') : __('finance.common.save') }}</button>
                        @if ($editExpense)
                            <a href="{{ route('shopowner.expenses.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('finance.common.all') }}</a>
                        @endif
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('finance.expenses.monthly_totals')">
                <div class="space-y-3">
                    @forelse ($monthlyTotals->groupBy('month_key')->take(6) as $monthKey => $rows)
                        <div>
                            <div class="mb-2 text-sm font-semibold text-gray-800">{{ $monthKey }}</div>
                            <div class="space-y-2">
                                @foreach ($rows as $row)
                                    <div>
                                        <div class="mb-1 flex justify-between text-sm">
                                            <span>{{ $row->category ? __('finance.expenses.categories.' . $row->category) : __('finance.expenses.uncategorised') }}</span>
                                            <strong>₪{{ number_format((float) $row->total, 2) }}</strong>
                                        </div>
                                        <div class="h-2 rounded-full bg-gray-100">
                                            <div class="h-2 rounded-full bg-indigo-500" style="width: {{ max(6, min(100, $rows->sum('total') > 0 ? ((float) $row->total / (float) $rows->sum('total')) * 100 : 0)) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <x-ui.empty :title="__('finance.common.no_data')" />
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card :title="__('finance.expenses.title')">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ __('finance.common.date') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.common.title') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.common.category') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.common.amount') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.common.notes') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($expenses as $expense)
                                <tr>
                                    <td class="px-3 py-2">{{ optional($expense->expense_date)->format('Y-m-d') }}</td>
                                    <td class="px-3 py-2">{{ $expense->title }}</td>
                                    <td class="px-3 py-2">{{ $expense->category ? __('finance.expenses.categories.' . $expense->category) : __('finance.expenses.uncategorised') }}</td>
                                    <td class="px-3 py-2">₪{{ number_format((float) $expense->amount, 2) }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $expense->notes }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap gap-2">
                                            @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('edit_expenses'))
                                                <a href="{{ route('shopowner.expenses.index', array_merge(request()->query(), ['edit' => $expense->id])) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('finance.common.update') }}</a>
                                            @endif
                                            @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('delete_expenses'))
                                                <form method="POST" action="{{ route('shopowner.expenses.destroy', $expense) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" data-confirm="{{ __('finance.common.delete') }}" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">{{ __('finance.common.delete') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">{{ __('finance.common.no_data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $expenses->links() }}</div>
            </x-ui.card>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('expenseGuardrails', (supplierNames, recentSupplierPaymentAmounts, outstandingSupplierBalances, supplierShortcut, staffShortcut) => ({
                    title: @js(old('title', $editExpense?->title)),
                    amount: @js((string) old('amount', $editExpense?->amount)),
                    supplierNames,
                    recentSupplierPaymentAmounts,
                    outstandingSupplierBalances,
                    supplierShortcut,
                    staffShortcut,
                    get supplierWarning() {
                        const value = (this.title || '').toLowerCase();
                        const keywords = ['supplier', 'dealer', 'purchase', 'مورد', 'تاجر', 'بضاعة'];
                        const match = keywords.some((word) => value.includes(word)) || this.supplierNames.some((name) => value.includes(String(name).toLowerCase()));
                        return match ? @js(__('finance.expenses.warning_supplier_match')) : '';
                    },
                    get amountWarning() {
                        const amount = Number(this.amount || 0).toFixed(2);
                        if (!amount || amount === '0.00') return '';
                        const matchRecent = this.recentSupplierPaymentAmounts.some((value) => Number(value).toFixed(2) === amount);
                        const matchBalance = this.outstandingSupplierBalances.some((value) => Number(value).toFixed(2) === amount);
                        return matchRecent || matchBalance ? @js(__('finance.expenses.warning_amount_match')) : '';
                    },
                    get salaryWarning() {
                        const value = (this.title || '').toLowerCase();
                        return value.includes('salary') || value.includes('payroll') || value.includes('راتب') || value.includes('أجر')
                            ? @js(__('finance.expenses.warning_salary_match'))
                            : '';
                    },
                }))
            })
        </script>
    @endpush
</x-app-layout>
