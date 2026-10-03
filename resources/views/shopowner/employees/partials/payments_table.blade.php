@if ($payments->count() === 0)
    <div class="p-6">
        <x-ui.empty :title="__('hr_owner.no_payments_title')" :text="__('hr_owner.no_payments_text')" />
    </div>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('hr_owner.payment_date') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('hr_owner.payment_kind') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('hr_owner.payment_method') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('hr_owner.period') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('hr_owner.notes') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('hr_owner.amount') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('hr_owner.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @foreach ($payments as $payment)
                    <tr>
                        <td class="px-4 py-3 text-gray-700">{{ optional($payment->payment_date)->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ __('hr_owner.payment_kinds.' . ($payment->kind ?: 'other')) }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ __('hr_owner.payment_types.' . ($payment->type ?: 'cash')) }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $payment->period ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $payment->note ?: '—' }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">₪{{ number_format((float) $payment->amount, 2) }}</td>
                        <td class="px-4 py-3 text-end">
                            <form method="POST" action="{{ route('shopowner.employees.destroyPayment', $payment) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">{{ __('hr_owner.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
