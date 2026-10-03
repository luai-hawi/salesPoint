<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('hr_owner.payroll_title')" :subtitle="__('hr_owner.payroll_subtitle')">
            <a href="{{ route('shopowner.payroll.export', ['period' => $period]) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.export_csv') }}</a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card>
                <form method="GET" class="grid gap-4 md:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.period') }}</label>
                        <input type="month" name="period" value="{{ $period }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.search') }}</label>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.status') }}</label>
                        <select name="active" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">{{ __('hr_owner.all_statuses') }}</option>
                            <option value="1" @selected(($filters['active'] ?? null) === '1')>{{ __('hr_owner.active') }}</option>
                            <option value="0" @selected(($filters['active'] ?? null) === '0')>{{ __('hr_owner.inactive') }}</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.filter') }}</button>
                        <a href="{{ route('shopowner.payroll.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.reset') }}</a>
                    </div>
                </form>
            </x-ui.card>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <x-ui.stat :label="__('hr_owner.gross_amount')" :value="'₪' . number_format((float) $totals['gross'], 2)" tone="blue" />
                <x-ui.stat :label="__('hr_owner.already_paid')" :value="'₪' . number_format((float) $totals['already_paid'], 2)" tone="green" />
                <x-ui.stat :label="__('hr_owner.net_payable')" :value="'₪' . number_format((float) $totals['net_payable'], 2)" tone="amber" />
            </div>

            <x-ui.card :title="__('hr_owner.payroll_table_title')" :subtitle="$period" :padding="false">
                @if ($employees->count() === 0)
                    <div class="p-6"><x-ui.empty :title="__('hr_owner.no_employees_title')" /></div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('hr_owner.employee') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('hr_owner.breakdown') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('hr_owner.gross_amount') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('hr_owner.already_paid') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('hr_owner.net_payable') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('hr_owner.status') }}</th>
                                    <th class="px-4 py-3 text-end">{{ __('hr_owner.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach ($employees as $employee)
                                    @php($summary = $employee->payroll_summary)
                                    <tr>
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-semibold text-gray-900">{{ $employee->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $employee->job_title }}</div>
                                        </td>
                                        <td class="px-4 py-3 align-top text-gray-700">
                                            <div>{{ __('hr_owner.present_days') }}: {{ $summary['present_days'] }}</div>
                                            <div>{{ __('hr_owner.absent_days') }}: {{ $summary['absent_days'] }}</div>
                                            <div>{{ __('hr_owner.overtime_hours') }}: {{ number_format($summary['overtime_minutes'] / 60, 2) }}</div>
                                            @if ($summary['pending_approval_minutes'] > 0 || $summary['pending_approval_days'] > 0)
                                                <div class="mt-1 text-xs text-amber-700">{{ __('hr_owner.pending_approval_hours_label') }}: {{ number_format($summary['pending_approval_minutes'] / 60, 2) }}</div>
                                                <div class="mt-1 text-xs text-amber-700">{{ __('hr_owner.pending_approval_amount_label') }}: ₪{{ number_format((float) $summary['pending_approval_amount'], 2) }}</div>
                                                <div class="mt-1"><x-ui.badge tone="amber">{{ __('hr_owner.payroll_pending_approval_excluded') }}</x-ui.badge></div>
                                            @elseif ($summary['flagged'])
                                                <div class="mt-1"><x-ui.badge tone="amber">{{ __('hr_owner.payroll_counts_review_records') }}</x-ui.badge></div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 align-top font-semibold text-gray-900">₪{{ number_format((float) $summary['gross'], 2) }}</td>
                                        <td class="px-4 py-3 align-top text-gray-700">₪{{ number_format((float) $summary['already_paid'], 2) }}</td>
                                        <td class="px-4 py-3 align-top font-semibold {{ $summary['net_payable'] < 0 ? 'text-red-700' : 'text-indigo-700' }}">₪{{ number_format((float) $summary['net_payable'], 2) }}</td>
                                        <td class="px-4 py-3 align-top"><x-ui.badge :tone="$summary['status'] === 'paid' ? 'green' : ($summary['status'] === 'overpaid' ? 'red' : ($summary['status'] === 'pending_approval' || $summary['flagged'] ? 'amber' : 'blue'))">{{ __('hr_owner.payroll_statuses.' . $summary['status']) }}</x-ui.badge></td>
                                        <td class="px-4 py-3 align-top text-end">
                                            <details class="inline-block text-start">
                                                <summary class="cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.details') }}</summary>
                                                <div class="mt-3 w-[22rem] space-y-3 rounded-xl border border-gray-200 bg-white p-4 shadow-lg">
                                                    <div class="grid gap-3 md:grid-cols-2">
                                                        <form method="POST" action="{{ route('shopowner.payroll.adjustments.store', $employee) }}" class="space-y-2 rounded-lg border border-gray-200 p-3">
                                                            @csrf
                                                            <input type="hidden" name="period" value="{{ $period }}">
                                                            <div class="text-sm font-semibold text-gray-900">{{ __('hr_owner.adjustments') }}</div>
                                                            <select name="kind" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                                <option value="bonus">{{ __('hr_owner.bonus') }}</option>
                                                                <option value="deduction">{{ __('hr_owner.deduction') }}</option>
                                                                <option value="penalty">{{ __('hr_owner.penalty') }}</option>
                                                            </select>
                                                            <input type="number" step="0.01" min="0.01" max="9999999999.99" name="amount" placeholder="{{ __('hr_owner.amount') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                            <textarea name="note" rows="2" placeholder="{{ __('hr_owner.notes') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                                                            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.save') }}</button>
                                                        </form>

                                                        <form method="POST" action="{{ route('shopowner.payroll.leaves.store', $employee) }}" class="space-y-2 rounded-lg border border-gray-200 p-3">
                                                            @csrf
                                                            <div class="text-sm font-semibold text-gray-900">{{ __('hr_owner.leaves') }}</div>
                                                            <input type="date" name="date_from" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                            <input type="date" name="date_to" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                            <select name="type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                                @foreach ($leaveTypes as $key => $label)
                                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                            <textarea name="note" rows="2" placeholder="{{ __('hr_owner.notes') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                                                            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.save') }}</button>
                                                        </form>
                                                    </div>

                                                    <form method="POST" action="{{ route('shopowner.payroll.pay', $employee) }}" class="space-y-2 rounded-lg border border-gray-200 p-3">
                                                        @csrf
                                                        <input type="hidden" name="period" value="{{ $period }}">
                                                        <input type="hidden" name="idempotency_key" value="{{ $paymentIdempotencyTokens[$employee->id] }}">
                                                        <div class="text-sm font-semibold text-gray-900">{{ __('hr_owner.pay_salary') }}</div>
                                                        <input type="number" step="0.01" min="0.01" max="99999999.99" name="amount" value="{{ max(0, (float) $summary['net_payable']) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                        <select name="type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                            @foreach ($paymentTypes as $key => $label)
                                                                <option value="{{ $key }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                        <input type="date" name="payment_date" value="{{ now()->toDateString() }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                        <textarea name="note" rows="2" placeholder="{{ __('hr_owner.notes') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                                                        <label class="flex items-center gap-2 text-xs text-gray-700">
                                                            <input type="checkbox" name="allow_overpay" value="1">
                                                            <span>{{ __('hr_owner.allow_overpay') }}</span>
                                                        </label>
                                                        @if ($summary['pending_approval_minutes'] > 0)
                                                            <p class="text-xs text-amber-700">{{ __('hr_owner.pending_approval_hours_label') }}: {{ number_format($summary['pending_approval_minutes'] / 60, 2) }}</p>
                                                            <p class="text-xs text-amber-700">{{ __('hr_owner.pending_approval_payment_hint', ['amount' => number_format((float) $summary['pending_approval_amount'], 2)]) }}</p>
                                                        @endif
                                                        <div class="flex flex-wrap gap-2">
                                                            <button type="submit" class="rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700">{{ __('hr_owner.pay_salary') }}</button>
                                                            <a href="{{ route('shopowner.payroll.payslip', ['employee' => $employee, 'period' => $period]) }}" target="_blank" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.print_payslip') }}</a>
                                                        </div>
                                                    </form>

                                                    @if ($summary['adjustments']->count())
                                                        <div class="rounded-lg border border-gray-200 p-3">
                                                            <div class="mb-2 text-sm font-semibold text-gray-900">{{ __('hr_owner.adjustments') }}</div>
                                                            <div class="space-y-2 text-xs text-gray-700">
                                                                @foreach ($summary['adjustments'] as $adjustment)
                                                                    <div class="flex items-center justify-between gap-2">
                                                                        <span>{{ __('hr_owner.adjustment_kinds.' . $adjustment->kind) }} — ₪{{ number_format((float) $adjustment->amount, 2) }}</span>
                                                                        <form method="POST" action="{{ route('shopowner.payroll.adjustments.destroy', $adjustment) }}">@csrf @method('DELETE')<button type="submit" class="text-red-700">{{ __('hr_owner.remove') }}</button></form>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if ($summary['leaves']->count())
                                                        <div class="rounded-lg border border-gray-200 p-3">
                                                            <div class="mb-2 text-sm font-semibold text-gray-900">{{ __('hr_owner.leaves') }}</div>
                                                            <div class="space-y-2 text-xs text-gray-700">
                                                                @foreach ($summary['leaves'] as $leave)
                                                                    <div class="flex items-center justify-between gap-2">
                                                                        <span>{{ $leave->date_from->toDateString() }} → {{ $leave->date_to->toDateString() }} • {{ __('hr_owner.leave_types.' . $leave->type) }}</span>
                                                                        <form method="POST" action="{{ route('shopowner.payroll.leaves.destroy', $leave) }}">@csrf @method('DELETE')<button type="submit" class="text-red-700">{{ __('hr_owner.remove') }}</button></form>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-4">
                        {{ $employees->links('vendor.pagination.custom-light') }}
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
