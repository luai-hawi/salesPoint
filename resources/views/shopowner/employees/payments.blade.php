<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('hr_owner.employee_payments_title')" :subtitle="$employee->name">
            <a href="{{ route('shopowner.employees.edit', $employee) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.edit_employee') }}</a>
            <a href="{{ route('shopowner.payroll.index', ['period' => $period]) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.payroll_title') }}</a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat :label="__('hr_owner.gross_amount')" :value="'₪' . number_format((float) $payrollSummary['gross'], 2)" tone="blue" />
                <x-ui.stat :label="__('hr_owner.already_paid')" :value="'₪' . number_format((float) $payrollSummary['already_paid'], 2)" tone="green" />
                <x-ui.stat :label="__('hr_owner.remaining_for_period')" :value="'₪' . number_format((float) $payrollSummary['net_payable'], 2)" tone="amber" />
                <x-ui.stat :label="__('hr_owner.worked_hours')" :value="number_format($payrollSummary['worked_minutes'] / 60, 2) . ' ' . __('hr_owner.hours_unit')" tone="indigo" />
            </div>
            @if ($payrollSummary['pending_approval_minutes'] > 0 || $payrollSummary['pending_approval_days'] > 0)
                <x-ui.card class="border-amber-300 bg-amber-50" :title="__('hr_owner.pending_approval_title')" :subtitle="__('hr_owner.pending_approval_payment_hint', ['amount' => number_format((float) $payrollSummary['pending_approval_amount'], 2)])">
                    <p class="text-sm text-amber-800">{{ __('hr_owner.pending_approval_hours_label') }}: {{ number_format($payrollSummary['pending_approval_minutes'] / 60, 2) }}</p>
                </x-ui.card>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <x-ui.card :title="__('hr_owner.record_payment')" :subtitle="__('hr_owner.record_payment_help')">
                    <form method="POST" action="{{ route('shopowner.employees.storePayment', $employee) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="period" value="{{ $period }}">
                        <input type="hidden" name="idempotency_key" value="{{ $paymentIdempotencyKey }}">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.amount') }}</label>
                            <input type="number" step="0.01" min="0.01" max="99999999.99" name="amount" value="{{ old('amount', max(0, (float) $payrollSummary['net_payable'])) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.payment_kind') }}</label>
                            <select name="kind" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @foreach ($paymentKinds as $key => $label)
                                    <option value="{{ $key }}" @selected(old('kind', 'advance') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.payment_method') }}</label>
                            <select name="type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @foreach ($paymentTypes as $key => $label)
                                    <option value="{{ $key }}" @selected(old('type', 'cash') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.period') }}</label>
                            <input type="month" name="period" value="{{ old('period', $period) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.payment_date') }}</label>
                            <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.notes') }}</label>
                            <textarea name="note" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{{ old('note') }}</textarea>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="allow_overpay" value="1" @checked(old('allow_overpay'))>
                            {{ __('hr_owner.allow_overpay') }}
                        </label>
                        @if ($payrollSummary['pending_approval_minutes'] > 0 || $payrollSummary['pending_approval_days'] > 0)
                            <p class="text-xs text-amber-700">{{ __('hr_owner.pending_approval_hours_label') }}: {{ number_format($payrollSummary['pending_approval_minutes'] / 60, 2) }}</p>
                            <p class="text-xs text-amber-700">{{ __('hr_owner.pending_approval_payment_hint', ['amount' => number_format((float) $payrollSummary['pending_approval_amount'], 2)]) }}</p>
                        @endif
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.record_payment') }}</button>
                    </form>
                </x-ui.card>

                <x-ui.card :title="__('hr_owner.payments_history')" :subtitle="__('hr_owner.payments_history_help')" class="lg:col-span-2" :padding="false">
                    <div class="border-b border-gray-200 px-4 py-4 sm:px-5">
                        <form method="GET" class="grid gap-4 md:grid-cols-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.from') }}</label>
                                <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.to') }}</label>
                                <input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.period') }}</label>
                                <input type="month" name="period" value="{{ $period }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            </div>
                            <div class="flex items-end gap-2">
                                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.filter') }}</button>
                                <a href="{{ route('shopowner.employees.payments', $employee) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.reset') }}</a>
                            </div>
                        </form>
                    </div>
                    @include('shopowner.employees.partials.payments_table', ['employee' => $employee, 'payments' => $payments])
                    <div class="border-t border-gray-200 px-4 py-4 sm:px-5">
                        {{ $payments->links('vendor.pagination.custom-light') }}
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>
</x-app-layout>
