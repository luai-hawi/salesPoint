@php
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
    $isRTL = app()->getLocale() === 'ar';

    // ── Report category / type definitions ───────────────────────────────
    $reportCategories = [
        [
            'id'    => 'sales',
            'label' => __('messages.Sales Reports'),
            'color' => 'indigo',
            'reports' => [
                ['type' => 'sale_bills',         'label' => __('messages.All Sale Bills'),       'icon' => 'receipt'],
                ['type' => 'customer_bills',     'label' => __('messages.Customer Bills'),       'icon' => 'user-doc'],
                ['type' => 'customer_statement', 'label' => __('messages.Customer Statement'),   'icon' => 'statement'],
            ],
        ],
        [
            'id'    => 'customers',
            'label' => __('messages.Customer Reports'),
            'color' => 'emerald',
            'reports' => [
                ['type' => 'customer_payments', 'label' => __('messages.Customer Payments'), 'icon' => 'cash'],
                ['type' => 'customer_balances', 'label' => __('messages.Customer Balances'), 'icon' => 'balance'],
            ],
        ],
        [
            'id'    => 'purchases',
            'label' => __('messages.Purchase Reports'),
            'color' => 'amber',
            'reports' => [
                ['type' => 'all_purchase_bills',      'label' => __('messages.All Purchase Bills'),      'icon' => 'truck'],
                ['type' => 'supplier_purchase_bills', 'label' => __('messages.Supplier Purchase Bills'), 'icon' => 'store'],
            ],
        ],
        [
            'id'    => 'suppliers',
            'label' => __('messages.Supplier Reports'),
            'color' => 'violet',
            'reports' => [
                ['type' => 'supplier_payments', 'label' => __('messages.Supplier Payments'), 'icon' => 'cash'],
                ['type' => 'supplier_balances', 'label' => __('messages.Supplier Balances'), 'icon' => 'balance'],
            ],
        ],
        [
            'id'    => 'employees',
            'label' => __('messages.Employee Reports'),
            'color' => 'blue',
            'reports' => [
                ['type' => 'employee_payments', 'label' => __('messages.Employee Salary Payments'), 'icon' => 'salary'],
                ['type' => 'employee_work',     'label' => __('messages.Employee Work Report'),     'icon' => 'work'],
            ],
        ],
        [
            'id'    => 'expenses',
            'label' => __('messages.Financial Reports'),
            'color' => 'rose',
            'reports' => [
                ['type' => 'expenses', 'label' => __('messages.Expenses Report'), 'icon' => 'expense'],
            ],
        ],
    ];

    // Column definitions per report type
    $columnDefs = [
        'sale_bills' => [
            ['key'=>'id','label'=>__('messages.Bill #')],
            ['key'=>'created_at','label'=>__('messages.Date'),'type'=>'date'],
            ['key'=>'customer_name','label'=>__('messages.Customer')],
            ['key'=>'creator_name','label'=>__('messages.Created By')],
            ['key'=>'total_price','label'=>__('messages.Total'),'type'=>'currency'],
            ['key'=>'profit','label'=>__('messages.Profit'),'type'=>'currency'],
            ['key'=>'is_damaged','label'=>__('messages.Damaged'),'type'=>'bool_damage'],
            ['key'=>'is_returned','label'=>__('messages.Returned'),'type'=>'bool_return'],
            ['key'=>'note','label'=>__('messages.Note'),'type'=>'note'],
        ],
        'customer_bills' => [
            ['key'=>'id','label'=>__('messages.Bill #')],
            ['key'=>'created_at','label'=>__('messages.Date'),'type'=>'date'],
            ['key'=>'customer_name','label'=>__('messages.Customer')],
            ['key'=>'phone','label'=>__('messages.Phone')],
            ['key'=>'total_price','label'=>__('messages.Total'),'type'=>'currency'],
            ['key'=>'profit','label'=>__('messages.Profit'),'type'=>'currency'],
            ['key'=>'is_damaged','label'=>__('messages.Damaged'),'type'=>'bool_damage'],
            ['key'=>'is_returned','label'=>__('messages.Returned'),'type'=>'bool_return'],
            ['key'=>'note','label'=>__('messages.Note'),'type'=>'note'],
        ],
        'customer_statement' => [
            ['key'=>'id','label'=>__('messages.Bill #')],
            ['key'=>'created_at','label'=>__('messages.Date'),'type'=>'date'],
            ['key'=>'total_price','label'=>__('messages.Total'),'type'=>'currency'],
            ['key'=>'is_damaged','label'=>__('messages.Damaged'),'type'=>'bool_damage'],
            ['key'=>'is_returned','label'=>__('messages.Returned'),'type'=>'bool_return'],
            ['key'=>'note','label'=>__('messages.Note'),'type'=>'note'],
        ],
        'customer_payments' => [
            ['key'=>'id','label'=>'#'],
            ['key'=>'created_at','label'=>__('messages.Date'),'type'=>'date'],
            ['key'=>'customer_name','label'=>__('messages.Customer')],
            ['key'=>'phone','label'=>__('messages.Phone')],
            ['key'=>'amount','label'=>__('messages.Amount'),'type'=>'currency'],
            ['key'=>'type','label'=>__('messages.Payment Type')],
            ['key'=>'note','label'=>__('messages.Note'),'type'=>'note'],
        ],
        'customer_balances' => [
            ['key'=>'id','label'=>'#'],
            ['key'=>'name','label'=>__('messages.Customer')],
            ['key'=>'phone','label'=>__('messages.Phone')],
            ['key'=>'balance','label'=>__('messages.Balance'),'type'=>'balance'],
        ],
        'all_purchase_bills' => [
            ['key'=>'id','label'=>__('messages.Bill #')],
            ['key'=>'purchase_date','label'=>__('messages.Date'),'type'=>'date_only'],
            ['key'=>'supplier_name','label'=>__('messages.Supplier')],
            ['key'=>'creator_name','label'=>__('messages.Created By')],
            ['key'=>'reference_number','label'=>__('messages.Reference')],
            ['key'=>'total_amount','label'=>__('messages.Total'),'type'=>'currency'],
            ['key'=>'notes','label'=>__('messages.Note'),'type'=>'note'],
        ],
        'supplier_purchase_bills' => [
            ['key'=>'id','label'=>__('messages.Bill #')],
            ['key'=>'purchase_date','label'=>__('messages.Date'),'type'=>'date_only'],
            ['key'=>'supplier_name','label'=>__('messages.Supplier')],
            ['key'=>'reference_number','label'=>__('messages.Reference')],
            ['key'=>'total_amount','label'=>__('messages.Total'),'type'=>'currency'],
            ['key'=>'notes','label'=>__('messages.Note'),'type'=>'note'],
        ],
        'supplier_payments' => [
            ['key'=>'id','label'=>'#'],
            ['key'=>'payment_date','label'=>__('messages.Date'),'type'=>'date_only'],
            ['key'=>'supplier_name','label'=>__('messages.Supplier')],
            ['key'=>'phone','label'=>__('messages.Phone')],
            ['key'=>'amount','label'=>__('messages.Amount'),'type'=>'currency'],
            ['key'=>'type','label'=>__('messages.Payment Type')],
            ['key'=>'note','label'=>__('messages.Note'),'type'=>'note'],
        ],
        'supplier_balances' => [
            ['key'=>'id','label'=>'#'],
            ['key'=>'name','label'=>__('messages.Supplier')],
            ['key'=>'phone','label'=>__('messages.Phone')],
            ['key'=>'email','label'=>__('messages.Email')],
            ['key'=>'balance','label'=>__('messages.Balance'),'type'=>'balance'],
        ],
        'employee_payments' => [
            ['key'=>'id','label'=>'#'],
            ['key'=>'payment_date','label'=>__('messages.Date'),'type'=>'date_only'],
            ['key'=>'employee_name','label'=>__('messages.Employee')],
            ['key'=>'job_title','label'=>__('messages.Job Title')],
            ['key'=>'amount','label'=>__('messages.Amount'),'type'=>'currency'],
            ['key'=>'type','label'=>__('messages.Payment Type')],
            ['key'=>'note','label'=>__('messages.Note'),'type'=>'note'],
        ],
        'employee_work' => [
            ['key'=>'id','label'=>__('messages.Bill #')],
            ['key'=>'created_at','label'=>__('messages.Date'),'type'=>'date'],
            ['key'=>'creator_name','label'=>__('messages.Employee')],
            ['key'=>'customer_name','label'=>__('messages.Customer')],
            ['key'=>'total_price','label'=>__('messages.Total'),'type'=>'currency'],
            ['key'=>'profit','label'=>__('messages.Profit'),'type'=>'currency'],
            ['key'=>'is_damaged','label'=>__('messages.Damaged'),'type'=>'bool_damage'],
            ['key'=>'is_returned','label'=>__('messages.Returned'),'type'=>'bool_return'],
            ['key'=>'note','label'=>__('messages.Note'),'type'=>'note'],
        ],
        'expenses' => [
            ['key'=>'id','label'=>'#'],
            ['key'=>'expense_date','label'=>__('messages.Date'),'type'=>'date_only'],
            ['key'=>'title','label'=>__('messages.Title')],
            ['key'=>'amount','label'=>__('messages.Amount'),'type'=>'currency'],
            ['key'=>'notes','label'=>__('messages.Note'),'type'=>'note'],
        ],
    ];

    // Which form fields each report type needs
    $reportFields = [
        'sale_bills'             => ['date', 'customer_optional'],
        'customer_bills'         => ['date', 'customer_required'],
        'customer_statement'     => ['date', 'customer_required'],
        'customer_payments'      => ['date', 'customer_optional'],
        'customer_balances'      => [],
        'all_purchase_bills'     => ['date', 'supplier_optional'],
        'supplier_purchase_bills'=> ['date', 'supplier_required'],
        'supplier_payments'      => ['date', 'supplier_optional'],
        'supplier_balances'      => [],
        'employee_payments'      => ['date', 'employee_required'],
        'employee_work'          => ['date', 'employee_user_optional'],
        'expenses'               => ['date'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center gap-3">
                <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {{ __('messages.Reports Center') }}
            </h2>
        </div>
    </x-slot>

    {{-- ── Pass PHP data to JS ────────────────────────────────────────── --}}
    <script>
        const _reportColumnDefs = @json($columnDefs);
        const _reportFields     = @json($reportFields);
        const _trans = {
            generate:        @js(__('messages.Generate Report')),
            generating:      @js(__('messages.Generating...')),
            noData:          @js(__('messages.No records found')),
            print:           @js(__('messages.Print Report')),
            totalRecords:    @js(__('messages.Total Records')),
            totalAmount:     @js(__('messages.Total Amount')),
            totalSales:      @js(__('messages.Total Sales')),
            totalProfit:     @js(__('messages.Total Profit')),
            totalOwing:      @js(__('messages.Total Balance Owed')),
            totalCredit:     @js(__('messages.Total Credit Balance')),
            maxRowsWarning:  @js(__('messages.report_max_rows_warning')),
            yes:             @js(__('messages.Yes')),
            damaged:         @js(__('messages.Damaged')),
            returned:        @js(__('messages.Returned')),
        };
    </script>

    <div
        x-data="reportsApp()"
        class="flex h-[calc(100vh-4rem)] overflow-hidden bg-gray-50"
        dir="{{ $isRTL ? 'rtl' : 'ltr' }}"
    >

        {{-- ══ LEFT SIDEBAR ════════════════════════════════════════════════ --}}
        <aside class="w-64 flex-shrink-0 bg-white border-{{ $isRTL ? 'l' : 'r' }} border-gray-200 overflow-y-auto no-print">
            <div class="p-3">
                @foreach ($reportCategories as $cat)
                    @php
                        $colorMap = [
                            'indigo' => ['bg'=>'bg-indigo-50','text'=>'text-indigo-700','dot'=>'bg-indigo-500','active'=>'bg-indigo-50 text-indigo-700 font-semibold','hover'=>'hover:bg-indigo-50 hover:text-indigo-700'],
                            'emerald'=> ['bg'=>'bg-emerald-50','text'=>'text-emerald-700','dot'=>'bg-emerald-500','active'=>'bg-emerald-50 text-emerald-700 font-semibold','hover'=>'hover:bg-emerald-50 hover:text-emerald-700'],
                            'amber'  => ['bg'=>'bg-amber-50','text'=>'text-amber-700','dot'=>'bg-amber-500','active'=>'bg-amber-50 text-amber-700 font-semibold','hover'=>'hover:bg-amber-50 hover:text-amber-700'],
                            'violet' => ['bg'=>'bg-violet-50','text'=>'text-violet-700','dot'=>'bg-violet-500','active'=>'bg-violet-50 text-violet-700 font-semibold','hover'=>'hover:bg-violet-50 hover:text-violet-700'],
                            'blue'   => ['bg'=>'bg-blue-50','text'=>'text-blue-700','dot'=>'bg-blue-500','active'=>'bg-blue-50 text-blue-700 font-semibold','hover'=>'hover:bg-blue-50 hover:text-blue-700'],
                            'rose'   => ['bg'=>'bg-rose-50','text'=>'text-rose-700','dot'=>'bg-rose-500','active'=>'bg-rose-50 text-rose-700 font-semibold','hover'=>'hover:bg-rose-50 hover:text-rose-700'],
                        ];
                        $c = $colorMap[$cat['color']];
                    @endphp
                    <div class="mb-3">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-2 mb-1">
                            {{ $cat['label'] }}
                        </p>
                        @foreach ($cat['reports'] as $rpt)
                            <button
                                @click="selectReport('{{ $rpt['type'] }}')"
                                :class="activeType === '{{ $rpt['type'] }}' ? '{{ $c['active'] }}' : 'text-gray-600 {{ $c['hover'] }}'"
                                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-all duration-150 text-{{ $isRTL ? 'right' : 'left' }}"
                            >
                                <span :class="activeType === '{{ $rpt['type'] }}' ? '{{ $c['dot'] }}' : 'bg-gray-300'"
                                      class="w-2 h-2 rounded-full flex-shrink-0 transition-colors"></span>
                                {{ $rpt['label'] }}
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </aside>

        {{-- ══ MAIN CONTENT ════════════════════════════════════════════════ --}}
        <main class="flex-1 overflow-y-auto">

            {{-- ── Empty state ── --}}
            <template x-if="!activeType">
                <div class="flex flex-col items-center justify-center h-full text-center p-8">
                    <div class="w-24 h-24 bg-indigo-50 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-12 h-12 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">{{ __('messages.Select a Report') }}</h3>
                    <p class="text-gray-400 text-sm max-w-xs">{{ __('messages.select_report_hint') }}</p>
                </div>
            </template>

            {{-- ── Report workspace ── --}}
            <template x-if="activeType">
                <div class="p-6 space-y-5">

                    {{-- Print-only header --}}
                    <div class="print-only hidden print:block mb-6 border-b-2 border-gray-800 pb-4">
                        {{-- Customer Statement: special customer-facing header --}}
                        <template x-if="activeType === 'customer_statement' && results && results.meta">
                            <div class="mb-4">
                                <h1 class="text-2xl font-bold text-gray-900">{{ __('messages.Customer Statement') }}</h1>
                                <div class="mt-2 text-sm text-gray-700 space-y-0.5">
                                    <p><strong>{{ __('messages.Customer') }}:</strong> <span x-text="results.meta.name"></span></p>
                                    <p x-show="results.meta.phone"><strong>{{ __('messages.Phone') }}:</strong> <span x-text="results.meta.phone"></span></p>
                                    <p x-show="results.meta.balance != 0">
                                        <strong>{{ __('messages.Current Balance') }}:</strong>
                                        <span x-text="fmtCurrency(Math.abs(results.meta.balance))"
                                              :class="results.meta.balance > 0 ? 'text-rose-700' : 'text-emerald-700'"></span>
                                        <span x-text="results.meta.balance > 0 ? ' ({{ __('messages.owes') }})' : ' ({{ __('messages.credit') }})'"></span>
                                    </p>
                                </div>
                                <p class="text-gray-500 text-xs mt-2" x-text="printSubtitle"></p>
                                <p class="text-gray-400 text-xs">{{ __('messages.Generated at') }}: {{ now()->format('Y-m-d H:i') }}</p>
                            </div>
                        </template>
                        {{-- All other reports --}}
                        <template x-if="activeType !== 'customer_statement'">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900" x-text="activeLabel"></h1>
                                <p class="text-gray-500 text-sm mt-1" x-text="printSubtitle"></p>
                                <p class="text-gray-400 text-xs mt-1">{{ __('messages.Generated at') }}: {{ now()->format('Y-m-d H:i') }}</p>
                            </div>
                        </template>
                    </div>

                    {{-- ── Filter panel (no-print) ── --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 no-print">
                        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"/>
                            </svg>
                            <h3 class="text-sm font-semibold text-gray-700">{{ __('messages.Filters') }}</h3>
                        </div>
                        <div class="p-5">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">

                                {{-- Date From --}}
                                <template x-if="hasField('date')">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('messages.Date From') }}</label>
                                        <input type="date" x-model="form.from"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none transition">
                                    </div>
                                </template>

                                {{-- Date To --}}
                                <template x-if="hasField('date')">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('messages.Date To') }}</label>
                                        <input type="date" x-model="form.to"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none transition">
                                    </div>
                                </template>

                                {{-- Customer (optional) --}}
                                <template x-if="hasField('customer_optional') || hasField('customer_required')">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">
                                            {{ __('messages.Customer') }}
                                            <template x-if="hasField('customer_optional')">
                                                <span class="text-gray-400">({{ __('messages.Optional') }})</span>
                                            </template>
                                        </label>
                                        <select x-model="form.customer_id"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none transition">
                                            <option value="">— {{ __('messages.All Customers') }} —</option>
                                            @foreach ($customers as $customer)
                                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </template>

                                {{-- Supplier (optional) --}}
                                <template x-if="hasField('supplier_optional') || hasField('supplier_required')">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">
                                            {{ __('messages.Supplier') }}
                                            <template x-if="hasField('supplier_optional')">
                                                <span class="text-gray-400">({{ __('messages.Optional') }})</span>
                                            </template>
                                        </label>
                                        <select x-model="form.supplier_id"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none transition">
                                            <option value="">— {{ __('messages.All Suppliers') }} —</option>
                                            @foreach ($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </template>

                                {{-- Employee (salary reports) --}}
                                <template x-if="hasField('employee_required')">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">
                                            {{ __('messages.Employee') }}
                                            <span class="text-gray-400">({{ __('messages.Optional') }})</span>
                                        </label>
                                        <select x-model="form.employee_id"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none transition">
                                            <option value="">— {{ __('messages.All Employees') }} —</option>
                                            @foreach ($employees as $emp)
                                                <option value="{{ $emp->id }}">{{ $emp->name }} ({{ $emp->job_title }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </template>

                                {{-- Employee user (work reports) --}}
                                <template x-if="hasField('employee_user_optional')">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">
                                            {{ __('messages.Employee Account') }}
                                            <span class="text-gray-400">({{ __('messages.Optional') }})</span>
                                        </label>
                                        <select x-model="form.employee_user_id"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none transition">
                                            <option value="">— {{ __('messages.All Employees') }} —</option>
                                            @foreach ($employeeUsers as $eu)
                                                <option value="{{ $eu->id }}">{{ $eu->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </template>

                            </div>

                            <div class="mt-4 flex items-center gap-3">
                                <button @click="generate()"
                                    :disabled="loading"
                                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors">
                                    <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                    </svg>
                                    <span x-text="loading ? _trans.generating : _trans.generate"></span>
                                </button>

                                <button x-show="results" @click="printReport()"
                                    class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 px-5 py-2 rounded-lg text-sm font-medium transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                    </svg>
                                    {{ __('messages.Print Report') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- ── Error message ── --}}
                    <template x-if="error">
                        <div class="bg-red-50 border border-red-200 rounded-xl px-5 py-4 text-red-700 text-sm flex items-center gap-2 no-print">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span x-text="error"></span>
                        </div>
                    </template>

                    {{-- ── Results ── --}}
                    <template x-if="results">
                        <div class="space-y-4" id="report-results">

                            {{-- Summary cards --}}
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 no-print">
                                <template x-for="card in summaryCards" :key="card.label">
                                    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 shadow-sm">
                                        <p class="text-xs text-gray-500 mb-0.5" x-text="card.label"></p>
                                        <p class="text-lg font-bold" :class="card.color" x-text="card.value"></p>
                                    </div>
                                </template>
                            </div>

                            {{-- Print-only summary --}}
                            <div class="print-only hidden print:flex gap-6 mb-4">
                                <template x-for="card in summaryCards" :key="card.label">
                                    <div class="border border-gray-300 rounded px-3 py-2">
                                        <p class="text-xs text-gray-500" x-text="card.label"></p>
                                        <p class="text-base font-bold text-gray-900" x-text="card.value"></p>
                                    </div>
                                </template>
                            </div>

                            {{-- Max rows warning --}}
                            <template x-if="results.rows.length >= 1000">
                                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-2 text-amber-700 text-xs no-print">
                                    ⚠️ <span x-text="_trans.maxRowsWarning"></span>
                                </div>
                            </template>

                            {{-- Table --}}
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50 border-b border-gray-200">
                                                <template x-for="col in activeColumns" :key="col.key">
                                                    <th class="px-3 py-2.5 text-{{ $isRTL ? 'right' : 'left' }} text-xs font-semibold text-gray-600 whitespace-nowrap"
                                                        x-text="col.label"></th>
                                                </template>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-if="results.rows.length === 0">
                                                <tr>
                                                    <td :colspan="activeColumns.length" class="px-4 py-10 text-center text-gray-400 text-sm">
                                                        <svg class="w-10 h-10 mx-auto mb-2 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                        <span x-text="_trans.noData"></span>
                                                    </td>
                                                </tr>
                                            </template>
                                            <template x-for="(row, idx) in results.rows" :key="row.id ?? idx">
                                                <tr :class="idx % 2 === 0 ? '' : 'bg-gray-50/60'"
                                                    class="border-b border-gray-100 hover:bg-indigo-50/40 transition-colors">
                                                    <template x-for="col in activeColumns" :key="col.key">
                                                        <td class="px-3 py-2 whitespace-nowrap" x-html="formatCell(row[col.key], col.type ?? 'text', row)"></td>
                                                    </template>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

        </main>
    </div>

    {{-- ── Print styles ──────────────────────────────────────────────────── --}}
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            #app-layout-nav,
            nav,
            aside { display: none !important; }
            body { background: white !important; }
            table { border-collapse: collapse; width: 100%; font-size: 11px; }
            th, td { border: 1px solid #d1d5db; padding: 4px 8px; }
            thead tr { background: #f3f4f6 !important; }
            tr:nth-child(even) { background: #f9fafb; }
            .print-only.hidden { display: flex !important; }
        }
        @media screen {
            .print-only { display: none !important; }
        }
    </style>

    @push('scripts')
    <script>
    function reportsApp() {
        return {
            activeType:   null,
            activeLabel:  '',
            loading:      false,
            results:      null,
            error:        null,
            activeColumns: [],
            summaryCards:  [],
            printSubtitle: '',

            form: {
                from:             '{{ now()->subDays(30)->format('Y-m-d') }}',
                to:               '{{ now()->format('Y-m-d') }}',
                customer_id:      '',
                supplier_id:      '',
                employee_id:      '',
                employee_user_id: '',
            },

            selectReport(type) {
                this.activeType    = type;
                this.results       = null;
                this.error         = null;
                this.activeColumns = _reportColumnDefs[type] ?? [];
                this.summaryCards  = [];
                this.activeLabel   = this.getLabelForType(type);
            },

            getLabelForType(type) {
                // Will be overridden by sidebar labels; fallback to type
                return type.replace(/_/g, ' ');
            },

            hasField(field) {
                const fields = _reportFields[this.activeType] ?? [];
                return fields.includes(field);
            },

            async generate() {
                if (!this.activeType) return;
                this.loading = true;
                this.error   = null;
                this.results = null;
                this.summaryCards = [];

                const params = new URLSearchParams({ type: this.activeType });
                if (this.hasField('date')) {
                    if (this.form.from) params.set('from', this.form.from);
                    if (this.form.to)   params.set('to',   this.form.to);
                }
                if (this.hasField('customer_optional') || this.hasField('customer_required')) {
                    if (this.form.customer_id) params.set('customer_id', this.form.customer_id);
                }
                if (this.hasField('supplier_optional') || this.hasField('supplier_required')) {
                    if (this.form.supplier_id) params.set('supplier_id', this.form.supplier_id);
                }
                if (this.hasField('employee_required')) {
                    if (this.form.employee_id) params.set('employee_id', this.form.employee_id);
                }
                if (this.hasField('employee_user_optional')) {
                    if (this.form.employee_user_id) params.set('employee_user_id', this.form.employee_user_id);
                }

                try {
                    const res  = await fetch(`{{ route('reports.generate') }}?${params}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        }
                    });
                    const data = await res.json();

                    if (!res.ok || !data.success) {
                        this.error = data.message ?? 'Error generating report';
                    } else {
                        this.results       = data;
                        this.summaryCards  = this.buildSummaryCards(data.summary);
                        this.printSubtitle = this.buildPrintSubtitle();
                    }
                } catch (e) {
                    this.error = 'Network error. Please try again.';
                } finally {
                    this.loading = false;
                }
            },

            buildSummaryCards(summary) {
                const cards = [];
                if (summary.count !== undefined) {
                    cards.push({ label: _trans.totalRecords, value: summary.count, color: 'text-gray-800' });
                }
                if (summary.total !== undefined) {
                    cards.push({ label: _trans.totalAmount, value: this.fmtCurrency(summary.total), color: 'text-indigo-700' });
                }
                if (summary.total_price !== undefined) {
                    cards.push({ label: _trans.totalSales, value: this.fmtCurrency(summary.total_price), color: 'text-indigo-700' });
                }
                if (summary.profit !== undefined) {
                    cards.push({ label: _trans.totalProfit, value: this.fmtCurrency(summary.profit), color: 'text-emerald-700' });
                }
                if (summary.total_owing !== undefined) {
                    cards.push({ label: _trans.totalOwing, value: this.fmtCurrency(summary.total_owing), color: 'text-rose-700' });
                }
                if (summary.total_credit !== undefined) {
                    cards.push({ label: _trans.totalCredit, value: this.fmtCurrency(summary.total_credit), color: 'text-blue-700' });
                }
                return cards;
            },

            buildPrintSubtitle() {
                const parts = [];
                if (this.hasField('date') && this.form.from) parts.push(`{{ __('messages.From') }}: ${this.form.from}`);
                if (this.hasField('date') && this.form.to)   parts.push(`{{ __('messages.To') }}: ${this.form.to}`);
                return parts.join('   |   ');
            },

            printReport() {
                window.print();
            },

            // ── Cell formatters ───────────────────────────────────────────────

            formatCell(value, type, row) {
                if (value === null || value === undefined || value === '') return '<span class="text-gray-300">—</span>';

                switch (type) {
                    case 'date':
                        return `<span class="text-gray-700">${this.fmtDatetime(value)}</span>`;
                    case 'date_only':
                        return `<span class="text-gray-700">${this.fmtDate(value)}</span>`;
                    case 'currency':
                        return `<span class="font-medium text-gray-800">${this.fmtCurrency(value)}</span>`;
                    case 'bool_damage':
                        return value ? `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">${_trans.damaged}</span>` : '';
                    case 'bool_return':
                        return value ? `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700">${_trans.returned}</span>` : '';
                    case 'balance':
                        const n = parseFloat(value);
                        if (n > 0) return `<span class="font-medium text-rose-600">${this.fmtCurrency(n)}</span>`;
                        if (n < 0) return `<span class="font-medium text-emerald-600">${this.fmtCurrency(Math.abs(n))}</span>`;
                        return `<span class="text-gray-400">0</span>`;
                    case 'note':
                        const str = String(value);
                        if (str.length > 60) return `<span title="${this.escHtml(str)}" class="text-gray-600 cursor-help">${this.escHtml(str.substring(0, 60))}…</span>`;
                        return `<span class="text-gray-600">${this.escHtml(str)}</span>`;
                    default:
                        return `<span class="text-gray-700">${this.escHtml(String(value))}</span>`;
                }
            },

            fmtCurrency(val) {
                const n = parseFloat(val) || 0;
                return n.toLocaleString('{{ app()->getLocale() === 'ar' ? 'ar-SA' : 'en-US' }}', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            fmtDatetime(str) {
                if (!str) return '—';
                const d = new Date(str.replace(' ', 'T'));
                if (isNaN(d)) return str;
                return d.toLocaleDateString('{{ app()->getLocale() === 'ar' ? 'ar-SA' : 'en-US' }}', { year:'numeric', month:'short', day:'numeric' })
                    + ' ' + d.toLocaleTimeString('{{ app()->getLocale() === 'ar' ? 'ar-SA' : 'en-US' }}', { hour:'2-digit', minute:'2-digit' });
            },

            fmtDate(str) {
                if (!str) return '—';
                const d = new Date(str + (str.length === 10 ? 'T00:00:00' : ''));
                if (isNaN(d)) return str;
                return d.toLocaleDateString('{{ app()->getLocale() === 'ar' ? 'ar-SA' : 'en-US' }}', { year:'numeric', month:'short', day:'numeric' });
            },

            escHtml(str) {
                return str
                    .replace(/&/g,'&amp;')
                    .replace(/</g,'&lt;')
                    .replace(/>/g,'&gt;')
                    .replace(/"/g,'&quot;');
            },
        };
    }
    </script>
    @endpush

</x-app-layout>
