@php
    $isRTL = app()->getLocale() === 'ar';
    $dir = $isRTL ? 'rtl' : 'ltr';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                {{ __('messages.Installment Plans') }}
            </h2>
            @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('create_installments'))
                <button onclick="document.getElementById('modal-new-plan').classList.remove('hidden')"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors shadow">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('messages.New Installment Plan') }}
                </button>
            @endif
        </div>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto" dir="{{ $dir }}">

        {{-- Flash message --}}
        @if (session('success'))
            <div id="flash-success"
                class="mb-4 bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded-xl flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <button onclick="document.getElementById('flash-success').remove()"
                    class="text-green-600 hover:text-green-800">✕</button>
            </div>
        @endif

        {{-- ── Due Today Notification Strip ─────────────────────────────── --}}
        @if ($dueToday->count() > 0)
            <div x-data="{ open: true }" x-show="open"
                class="mb-6 bg-amber-50 border border-amber-300 rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3 bg-amber-100 border-b border-amber-200">
                    <div class="flex items-center gap-2 font-semibold text-amber-900">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        {{ $dueToday->count() }} {{ __('messages.payments due today') }}
                    </div>
                    <div class="flex items-center gap-3">
                        @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('dismiss_installment_notifications'))
                            <button onclick="dismissAll()"
                                class="text-xs bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 rounded-lg font-medium transition-colors">
                                {{ __('messages.Dismiss all due today') }}
                            </button>
                        @endif
                        <button @click="open = false"
                            class="text-amber-600 hover:text-amber-800 font-bold text-lg leading-none">✕</button>
                    </div>
                </div>
                <div class="divide-y divide-amber-100 max-h-72 overflow-y-auto">
                    @foreach ($dueToday as $dp)
                        <div class="flex items-center justify-between px-5 py-3 hover:bg-amber-50 transition-colors due-row"
                            id="due-row-{{ $dp->id }}">
                            <div class="flex flex-col">
                                <span class="font-medium text-gray-900">
                                    {{ $dp->plan->debtor_name }}
                                </span>
                                <span class="text-xs text-gray-500">
                                    {{ __('messages.Due Date') }}: {{ $dp->due_date->format('Y-m-d') }}
                                    @if ($dp->due_date->isPast() && !$dp->due_date->isToday())
                                        <span class="text-red-600 font-semibold">({{ __('messages.Overdue') }})</span>
                                    @endif
                                    @if ($dp->plan->bill_id)
                                        &bull; {{ __('messages.Linked Bill') }} #{{ $dp->plan->bill_id }}
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-bold text-gray-900">{{ number_format($dp->amount, 2) }}</span>
                                <div class="flex gap-2">
                                    <button onclick="markPaid({{ $dp->id }}, {{ $dp->installment_plan_id }})"
                                        class="text-xs bg-green-600 hover:bg-green-700 text-white px-2.5 py-1 rounded-lg transition-colors">
                                        {{ __('messages.Mark as Paid') }}
                                    </button>
                                    @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('dismiss_installment_notifications'))
                                        <button onclick="dismissOne({{ $dp->id }})"
                                            class="text-xs bg-gray-500 hover:bg-gray-600 text-white px-2.5 py-1 rounded-lg transition-colors">
                                            {{ __('messages.Dismiss Notification') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── Search & Filter ──────────────────────────────────────────── --}}
        <form method="GET" action="{{ route('installments.index') }}"
            class="mb-6 bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('messages.Search') }}</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="{{ __('messages.Search by name, note, plan ID') }}..."
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('messages.From Date') }}</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('messages.To Date') }}</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
            </div>
            <div class="flex items-center gap-2 mt-3 flex-wrap">
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                    {{ __('messages.Search') }}
                </button>
                <a href="{{ route('installments.index') }}"
                    class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                    {{ __('messages.Reset') }}
                </a>
            </div>
        </form>

        {{-- ── Status Tabs ──────────────────────────────────────────────── --}}
        <div class="flex gap-1 mb-5 flex-wrap">
            @foreach ([
        'all' => __('messages.All Plans'),
        'due_today' => __('messages.Due Today'),
        'overdue' => __('messages.Overdue'),
        'upcoming' => __('messages.Upcoming'),
        'paid' => __('messages.Paid'),
        'standalone' => __('messages.Standalone Plan'),
    ] as $key => $label)
                <a href="{{ route('installments.index', array_merge(request()->except('status', 'page'), ['status' => $key])) }}"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                       {{ $status === $key
                           ? 'bg-indigo-600 text-white shadow'
                           : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- ── Plans ───────────────────────────────────────────────────── --}}
        @forelse ($plans as $plan)
            @php
                $paidAmt = $plan->paid_amount;
                $remaining = $plan->remaining_amount;
                $progress = $plan->total_amount > 0 ? min(100, round(($paidAmt / $plan->total_amount) * 100)) : 0;
            @endphp
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm mb-4 overflow-hidden"
                x-data="{ expanded: false }" id="plan-{{ $plan->id }}">

                {{-- Plan header --}}
                <div class="flex items-center justify-between px-5 py-4 cursor-pointer hover:bg-gray-50 transition-colors"
                    @click="expanded = !expanded">
                    <div class="flex items-center gap-4 min-w-0 flex-1">
                        <div
                            class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                            @if ($plan->is_standalone)
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            @else
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-gray-900">{{ $plan->debtor_name }}</span>
                                @if ($plan->is_standalone)
                                    <span
                                        class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium">{{ __('messages.Standalone Plan') }}</span>
                                @endif
                                @if ($plan->bill_id)
                                    <span
                                        class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">{{ __('messages.Linked Bill') }}
                                        #{{ $plan->bill_id }}</span>
                                @endif
                                <span class="text-xs text-gray-400">#{{ $plan->id }}</span>
                            </div>
                            @if ($plan->note)
                                <p class="text-xs text-gray-500 truncate mt-0.5">{{ $plan->note }}</p>
                            @endif
                            <div class="mt-1.5 flex items-center gap-2">
                                <div class="flex-1 bg-gray-200 rounded-full h-1.5" style="max-width:160px">
                                    <div class="h-1.5 rounded-full {{ $progress >= 100 ? 'bg-green-500' : 'bg-indigo-500' }}"
                                        style="width:{{ $progress }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500">{{ $progress }}%</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 flex-shrink-0 ms-4">
                        <div class="text-right">
                            <div class="text-sm font-bold text-gray-900">{{ number_format($plan->total_amount, 2) }}
                            </div>
                            <div class="text-xs text-gray-500">
                                <span class="text-green-600">{{ number_format($paidAmt, 2) }}</span> /
                                <span class="text-red-500">{{ number_format($remaining, 2) }}
                                    {{ __('messages.Remaining Amount') }}</span>
                            </div>
                        </div>
                        <div class="flex gap-1.5">
                            @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('create_installments'))
                                <button
                                    onclick="event.stopPropagation(); openEditPlan({{ $plan->id }}, {{ $plan->total_amount }}, {{ $plan->initial_payment }}, @js($plan->customer_name_override ?? ''), @js($plan->note ?? ''))"
                                    class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                                    title="{{ __('messages.Edit') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                            @endif
                            @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('delete_installments'))
                                <button onclick="event.stopPropagation(); deletePlan({{ $plan->id }})"
                                    class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                    title="{{ __('messages.Delete') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            @endif
                            <svg :class="expanded ? 'rotate-180' : ''"
                                class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Installment payments table (expanded) --}}
                <div x-show="expanded" x-cloak x-transition class="border-t border-gray-100">
                    @if ($plan->payments->isEmpty())
                        <p class="text-sm text-gray-400 text-center py-6">{{ __('messages.No installments yet') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2.5 text-start font-medium text-gray-500 text-xs">#</th>
                                        <th class="px-4 py-2.5 text-start font-medium text-gray-500 text-xs">
                                            {{ __('messages.Due Date') }}</th>
                                        <th class="px-4 py-2.5 text-start font-medium text-gray-500 text-xs">
                                            {{ __('messages.Amount') }}</th>
                                        <th class="px-4 py-2.5 text-start font-medium text-gray-500 text-xs">
                                            {{ __('messages.Status') }}</th>
                                        <th class="px-4 py-2.5 text-start font-medium text-gray-500 text-xs">
                                            {{ __('messages.Note') }}</th>
                                        <th class="px-4 py-2.5 text-end font-medium text-gray-500 text-xs">
                                            {{ __('messages.Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50" id="payments-tbody-{{ $plan->id }}">
                                    @foreach ($plan->payments as $i => $pmt)
                                        @php
                                            $s = $pmt->status;
                                            $badge = match ($s) {
                                                'paid' => 'bg-green-100 text-green-700',
                                                'due_today' => 'bg-amber-100 text-amber-700',
                                                'overdue' => 'bg-red-100 text-red-700',
                                                default => 'bg-blue-50 text-blue-700',
                                            };
                                            $label = match ($s) {
                                                'paid' => __('messages.Paid'),
                                                'due_today' => __('messages.Due Today'),
                                                'overdue' => __('messages.Overdue'),
                                                default => __('messages.Upcoming'),
                                            };
                                        @endphp
                                        <tr class="hover:bg-gray-50 transition-colors payment-row"
                                            id="pmt-row-{{ $pmt->id }}" x-data="{ editing: false }">
                                            <td class="px-4 py-2.5 text-gray-400">{{ $i + 1 }}</td>
                                            <td class="px-4 py-2.5">
                                                <span x-show="!editing">{{ $pmt->due_date->format('Y-m-d') }}</span>
                                                <input x-show="editing" type="date"
                                                    class="border border-gray-300 rounded px-2 py-0.5 text-xs w-32"
                                                    value="{{ $pmt->due_date->format('Y-m-d') }}"
                                                    id="edit-date-{{ $pmt->id }}">
                                            </td>
                                            <td class="px-4 py-2.5 font-semibold text-gray-900">
                                                <span x-show="!editing">{{ number_format($pmt->amount, 2) }}</span>
                                                <input x-show="editing" type="number" step="0.01" min="0.01"
                                                    class="border border-gray-300 rounded px-2 py-0.5 text-xs w-24"
                                                    value="{{ $pmt->amount }}"
                                                    id="edit-amount-{{ $pmt->id }}">
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <span
                                                    class="text-xs px-2 py-0.5 rounded-full font-medium {{ $badge }}">{{ $label }}</span>
                                                @if ($pmt->is_paid && $pmt->paid_at)
                                                    <span
                                                        class="text-xs text-gray-400 ms-1">{{ $pmt->paid_at->format('Y-m-d') }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-gray-500 text-xs">
                                                <span x-show="!editing">{{ $pmt->note ?? '—' }}</span>
                                                <input x-show="editing" type="text"
                                                    class="border border-gray-300 rounded px-2 py-0.5 text-xs w-32"
                                                    value="{{ $pmt->note ?? '' }}"
                                                    id="edit-note-{{ $pmt->id }}"
                                                    placeholder="{{ __('messages.Note') }}">
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <div class="flex items-center justify-end gap-1.5 flex-wrap"
                                                    x-show="!editing">
                                                    @if (!$pmt->is_paid)
                                                        <button
                                                            onclick="markPaid({{ $pmt->id }}, {{ $plan->id }})"
                                                            class="text-xs bg-green-100 hover:bg-green-200 text-green-700 px-2.5 py-1 rounded-lg transition-colors font-medium">
                                                            {{ __('messages.Mark as Paid') }}
                                                        </button>
                                                    @endif
                                                    @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('create_installments'))
                                                        <button @click="editing = true"
                                                            class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-2.5 py-1 rounded-lg transition-colors">
                                                            {{ __('messages.Edit') }}
                                                        </button>
                                                    @endif
                                                    @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('delete_installments'))
                                                        <button onclick="deletePayment({{ $pmt->id }})"
                                                            class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-2.5 py-1 rounded-lg transition-colors">
                                                            {{ __('messages.Delete') }}
                                                        </button>
                                                    @endif
                                                </div>
                                                <div class="flex items-center justify-end gap-1.5" x-show="editing"
                                                    x-cloak>
                                                    <button @click="savePaymentEdit({{ $pmt->id }}, $el)"
                                                        class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1 rounded-lg transition-colors">
                                                        {{ __('messages.Save') }}
                                                    </button>
                                                    <button @click="editing = false"
                                                        class="text-xs bg-gray-200 hover:bg-gray-300 text-gray-700 px-2.5 py-1 rounded-lg transition-colors">
                                                        {{ __('messages.Cancel') }}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    <div
                        class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-500 flex flex-wrap gap-4">
                        <span>{{ __('messages.Total Debt') }}:
                            <strong>{{ number_format($plan->total_amount, 2) }}</strong></span>
                        <span>{{ __('messages.Initial Payment') }}:
                            <strong>{{ number_format($plan->initial_payment, 2) }}</strong></span>
                        <span>{{ __('messages.Paid Amount') }}: <strong
                                class="text-green-600">{{ number_format($paidAmt, 2) }}</strong></span>
                        <span>{{ __('messages.Remaining Amount') }}: <strong
                                class="text-red-600">{{ number_format($remaining, 2) }}</strong></span>
                        <span>{{ __('messages.Created') }}:
                            <strong>{{ $plan->created_at->format('Y-m-d') }}</strong></span>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-20 bg-white rounded-2xl border border-gray-200 shadow-sm">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="text-gray-500 font-medium">{{ __('messages.No installment plans found') }}</p>
                @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('create_installments'))
                    <button onclick="document.getElementById('modal-new-plan').classList.remove('hidden')"
                        class="mt-4 inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                        {{ __('messages.New Installment Plan') }}
                    </button>
                @endif
            </div>
        @endforelse

        {{-- Pagination --}}
        <div class="mt-4">{{ $plans->links() }}</div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         MODAL: New Standalone Installment Plan
    ═══════════════════════════════════════════════════════════════ --}}
    <div id="modal-new-plan" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" onclick="closeNewPlanModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] overflow-y-auto"
            dir="{{ $dir }}">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">{{ __('messages.New Installment Plan') }}</h3>
                <button onclick="closeNewPlanModal()"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <form action="{{ route('installments.store') }}" method="POST" id="form-new-plan"
                class="p-6 space-y-5">
                @csrf
                {{-- Customer or name --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Customer') }}</label>
                        <select name="customer_id" id="np-customer" onchange="toggleNameOverride()"
                            class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                            <option value="">— {{ __('messages.External (not in system)') }} —</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="name-override-wrap">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Debtor Name') }}
                            *</label>
                        <input type="text" name="customer_name_override" id="np-name"
                            class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                            placeholder="{{ __('messages.Enter debtor name') }}">
                    </div>
                </div>

                {{-- Total / Initial --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Total Debt') }}
                            *</label>
                        <input type="number" step="0.01" min="0.01" name="total_amount" id="np-total"
                            oninput="updateLastRow()" required
                            class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                            placeholder="0.00">
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Initial Payment') }}</label>
                        <input type="number" step="0.01" min="0" name="initial_payment" id="np-initial"
                            value="0" oninput="updateLastRow()"
                            class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                            placeholder="0.00">
                    </div>
                </div>

                {{-- Note --}}
                <div>
                    <label
                        class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Plan Note') }}</label>
                    <textarea name="note" rows="2"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        placeholder="{{ __('messages.Optional note') }}"></textarea>
                </div>

                {{-- Schedule builder --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label
                            class="block text-sm font-semibold text-gray-800">{{ __('messages.Schedule Builder') }}</label>
                        <button type="button" onclick="addPaymentRow()"
                            class="text-xs bg-indigo-100 hover:bg-indigo-200 text-indigo-700 px-3 py-1.5 rounded-lg font-medium transition-colors">
                            + {{ __('messages.Add Installment Row') }}
                        </button>
                    </div>

                    {{-- Auto-generate bar --}}
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-3 mb-3 flex flex-wrap items-end gap-3">
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">{{ __('messages.Frequency') }}</label>
                            <select id="gen-freq" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                <option value="monthly">{{ __('messages.Every Month') }}</option>
                                <option value="weekly">{{ __('messages.Every Week') }}</option>
                                <option value="custom">{{ __('messages.Every N Days') }}</option>
                            </select>
                        </div>
                        <div id="gen-days-wrap" class="hidden">
                            <label class="text-xs text-gray-500 block mb-1">{{ __('messages.Days') }}</label>
                            <input type="number" id="gen-days" value="30" min="1"
                                class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-20">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">{{ __('messages.Count') }}</label>
                            <input type="number" id="gen-count" value="3" min="1" max="60"
                                class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-20">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">{{ __('messages.Start Date') }}</label>
                            <input type="date" id="gen-start"
                                class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm"
                                value="{{ now()->addMonth()->format('Y-m-d') }}">
                        </div>
                        <button type="button" onclick="generateSchedule()"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
                            {{ __('messages.Generate Schedule') }}
                        </button>
                    </div>

                    <div id="payment-rows-container" class="space-y-2">
                        {{-- rows injected by JS --}}
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeNewPlanModal()"
                        class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-5 py-2 rounded-xl text-sm font-medium transition-colors">
                        {{ __('messages.Cancel') }}
                    </button>
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-sm font-medium transition-colors">
                        {{ __('messages.Save Plan') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         MODAL: Edit Plan
    ═══════════════════════════════════════════════════════════════ --}}
    <div id="modal-edit-plan" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50"
            onclick="document.getElementById('modal-edit-plan').classList.add('hidden')"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md" dir="{{ $dir }}">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">{{ __('messages.Edit Plan') }}</h3>
                <button onclick="document.getElementById('modal-edit-plan').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label
                        class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Debtor Name') }}</label>
                    <input type="text" id="ep-name"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <div>
                    <label
                        class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Total Debt') }}</label>
                    <input type="number" step="0.01" id="ep-total"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <div>
                    <label
                        class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Initial Payment') }}</label>
                    <input type="number" step="0.01" id="ep-initial"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <div>
                    <label
                        class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Plan Note') }}</label>
                    <textarea id="ep-note" rows="2"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button onclick="document.getElementById('modal-edit-plan').classList.add('hidden')"
                        class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-5 py-2 rounded-xl text-sm font-medium transition-colors">
                        {{ __('messages.Cancel') }}
                    </button>
                    <button id="ep-save-btn"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-sm font-medium transition-colors">
                        {{ __('messages.Save') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // ── New plan modal ────────────────────────────────────────────────────
            function closeNewPlanModal() {
                document.getElementById('modal-new-plan').classList.add('hidden');
            }

            function toggleNameOverride() {
                const cid = document.getElementById('np-customer').value;
                document.getElementById('name-override-wrap').style.display = cid ? 'none' : '';
            }
            toggleNameOverride();

            // ── Payment rows ─────────────────────────────────────────────────────
            let rowIdx = 0;

            function addPaymentRow(date = '', amount = '', note = '') {
                rowIdx++;
                const container = document.getElementById('payment-rows-container');
                const div = document.createElement('div');
                div.className = 'flex items-center gap-2 flex-wrap';
                div.id = 'pr-' + rowIdx;
                div.innerHTML = `
            <input type="date" name="payments[${rowIdx}][due_date]" value="${date}"
                class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm flex-1 min-w-[130px] focus:outline-none focus:ring-1 focus:ring-indigo-400">
            <input type="number" step="0.01" name="payments[${rowIdx}][amount]" value="${amount}"
                placeholder="{{ __('messages.Amount') }}"
                oninput="updateLastRow()"
                class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-28 focus:outline-none focus:ring-1 focus:ring-indigo-400">
            <input type="text" name="payments[${rowIdx}][note]" value="${note}"
                placeholder="{{ __('messages.Note') }} ({{ __('messages.Optional') }})"
                class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm flex-1 min-w-[120px] focus:outline-none focus:ring-1 focus:ring-indigo-400">
            <button type="button" onclick="document.getElementById('pr-${rowIdx}').remove(); updateLastRow();"
                class="text-red-400 hover:text-red-600 text-lg leading-none flex-shrink-0">&times;</button>
        `;
                container.appendChild(div);
                updateLastRow();
            }

            function updateLastRow() {
                const rows = document.querySelectorAll('#payment-rows-container [name$="[amount]"]');
                if (rows.length === 0) return;
                const total = parseFloat(document.getElementById('np-total')?.value) || 0;
                const initial = parseFloat(document.getElementById('np-initial')?.value) || 0;
                let sumPrev = 0;
                rows.forEach((r, i) => {
                    if (i < rows.length - 1) sumPrev += parseFloat(r.value) || 0;
                });
                const remaining = Math.max(0, total - initial - sumPrev);
                rows[rows.length - 1].value = remaining > 0 ? remaining.toFixed(2) : '';
            }

            function generateSchedule() {
                document.getElementById('payment-rows-container').innerHTML = '';
                rowIdx = 0;
                const freq = document.getElementById('gen-freq').value;
                const count = parseInt(document.getElementById('gen-count').value) || 1;
                const start = document.getElementById('gen-start').value;
                const days = parseInt(document.getElementById('gen-days')?.value) || 30;

                if (!start) {
                    alert('{{ __('messages.Please set a start date') }}');
                    return;
                }

                const base = new Date(start);
                for (let i = 0; i < count; i++) {
                    const d = new Date(base);
                    if (freq === 'monthly') d.setMonth(d.getMonth() + i);
                    else if (freq === 'weekly') d.setDate(d.getDate() + i * 7);
                    else d.setDate(d.getDate() + i * days);

                    addPaymentRow(d.toISOString().split('T')[0]);
                }
                updateLastRow();
            }

            document.getElementById('gen-freq').addEventListener('change', function() {
                document.getElementById('gen-days-wrap').classList.toggle('hidden', this.value !== 'custom');
            });

            // ── Mark paid ────────────────────────────────────────────────────────
            function markPaid(paymentId, planId) {
                // Show custom confirmation modal instead of plain confirm()
                const modal = document.createElement('div');
                modal.id = 'mark-paid-confirm-modal';
                modal.className = 'fixed inset-0 z-[300] flex items-center justify-center p-4';
                modal.innerHTML = `
            <div class="absolute inset-0 bg-black/50"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4"
                 dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">{{ __('messages.Mark as Paid') }}</h3>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800 space-y-1">
                    <p class="font-semibold">{{ __('messages.Please note') }}:</p>
                    <p>{{ __('messages.mark_paid_cash_note') }}</p>
                    <a href="{{ route('payments-receipts.index') }}"
                       target="_blank"
                       class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-medium mt-1 underline underline-offset-2">
                        {{ __('messages.Go to Customer Payments page') }}
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
                <div class="flex justify-end gap-3 pt-1">
                    <button id="mark-paid-cancel"
                        class="border border-gray-300 hover:bg-gray-50 text-gray-600 px-5 py-2.5 rounded-xl text-sm font-medium transition-colors">
                        {{ __('messages.Cancel') }}
                    </button>
                    <button id="mark-paid-confirm"
                        class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ __('messages.Confirm & Mark Paid') }}
                    </button>
                </div>
            </div>
        `;
                document.body.appendChild(modal);

                document.getElementById('mark-paid-cancel').addEventListener('click', () => modal.remove());
                document.getElementById('mark-paid-confirm').addEventListener('click', () => {
                    modal.remove();
                    fetch(`/installments/payments/${paymentId}/mark-paid`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        })
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                location.reload();
                            } else {
                                alert(d.error || 'Error');
                            }
                        });
                });
            }

            // ── Dismiss one ──────────────────────────────────────────────────────
            function dismissOne(paymentId) {
                fetch(`/installments/payments/${paymentId}/dismiss`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            const row = document.getElementById('due-row-' + paymentId);
                            if (row) row.remove();
                        }
                    });
            }

            // ── Dismiss all ──────────────────────────────────────────────────────
            function dismissAll() {
                fetch('/installments/dismiss-all-today', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) location.reload();
                    });
            }

            // ── Delete plan ──────────────────────────────────────────────────────
            function deletePlan(planId) {
                if (!confirm('{{ __('messages.Delete this installment plan and all its payments?') }}')) return;
                fetch(`/installments/${planId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            const el = document.getElementById('plan-' + planId);
                            if (el) el.remove();
                        }
                    });
            }

            // ── Delete single payment ────────────────────────────────────────────
            function deletePayment(paymentId) {
                if (!confirm('{{ __('messages.Delete this installment payment?') }}')) return;
                fetch(`/installments/payments/${paymentId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            const row = document.getElementById('pmt-row-' + paymentId);
                            if (row) row.remove();
                        }
                    });
            }

            // ── Save payment edit ────────────────────────────────────────────────
            function savePaymentEdit(paymentId, btn) {
                const dueDate = document.getElementById('edit-date-' + paymentId).value;
                const amount = document.getElementById('edit-amount-' + paymentId).value;
                const note = document.getElementById('edit-note-' + paymentId).value;

                fetch(`/installments/payments/${paymentId}`, {
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            due_date: dueDate,
                            amount: amount,
                            note: note
                        })
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) location.reload();
                    });
            }

            // ── Edit plan modal ──────────────────────────────────────────────────
            let currentEditPlanId = null;

            function openEditPlan(planId, total, initial, nameOverride, note) {
                currentEditPlanId = planId;
                document.getElementById('ep-name').value = nameOverride || '';
                document.getElementById('ep-total').value = total;
                document.getElementById('ep-initial').value = initial;
                document.getElementById('ep-note').value = note || '';
                document.getElementById('modal-edit-plan').classList.remove('hidden');
            }

            document.getElementById('ep-save-btn').addEventListener('click', function() {
                if (!currentEditPlanId) return;
                const payload = {
                    customer_name_override: document.getElementById('ep-name').value,
                    total_amount: document.getElementById('ep-total').value,
                    initial_payment: document.getElementById('ep-initial').value,
                    note: document.getElementById('ep-note').value,
                };
                fetch(`/installments/${currentEditPlanId}`, {
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) location.reload();
                    });
            });

            // Seed at least one row when new-plan modal opens
            document.querySelector('[onclick="document.getElementById(\'modal-new-plan\').classList.remove(\'hidden\')"]')
                ?.addEventListener('click', () => {
                    if (document.getElementById('payment-rows-container').children.length === 0) {
                        addPaymentRow('', '', '');
                    }
                });
        </script>
    @endpush
</x-app-layout>
