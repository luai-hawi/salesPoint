<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-xl text-gray-800 leading-tight flex items-center gap-2">
                <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                {{ __('sales.Sales & Promotions') }}
            </h2>
            <button onclick="openCreateModal()"
                class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-4 rounded-lg flex items-center gap-2 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('sales.New Sale') }}
            </button>
        </div>
    </x-slot>

    <div class="py-6 bg-gradient-to-br from-gray-50 to-orange-50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
                    <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="text-green-700 font-medium">{{ session('success') }}</span>
                </div>
            @endif

            {{-- Stats bar --}}
            @php
                $activeSalesCount = $sales->filter(fn($s) => $s->isCurrentlyActive())->count();
                $expiredCount = $sales->filter(fn($s) => $s->status === 'expired')->count();
                $scheduledCount = $sales->filter(fn($s) => $s->status === 'scheduled')->count();
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-center">
                    <div class="text-2xl font-bold text-gray-800">{{ $sales->count() }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ __('sales.Total Sales') }}</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $activeSalesCount }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ __('sales.Active') }}</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-center">
                    <div class="text-2xl font-bold text-blue-500">{{ $scheduledCount }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ __('sales.Scheduled') }}</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-center">
                    <div class="text-2xl font-bold text-gray-400">{{ $expiredCount }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ __('sales.Expired') }}</div>
                </div>
            </div>

            {{-- Sales list --}}
            @if ($sales->isEmpty())
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-16 text-center">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <p class="text-gray-500 text-lg font-medium">{{ __('sales.No sales yet') }}</p>
                    <p class="text-gray-400 text-sm mt-1">{{ __('sales.Create your first sale promotion') }}</p>
                    <button onclick="openCreateModal()"
                        class="mt-4 bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                        {{ __('sales.Create Sale') }}
                    </button>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($sales as $sale)
                        @php
                            $status = $sale->status;
                            $statusColors = [
                                'active' => 'bg-green-100 text-green-700 border-green-200',
                                'expired' => 'bg-gray-100 text-gray-500 border-gray-200',
                                'scheduled' => 'bg-blue-100 text-blue-700 border-blue-200',
                                'disabled' => 'bg-red-100 text-red-600 border-red-200',
                            ];
                            $statusLabels = [
                                'active' => __('sales.Active'),
                                'expired' => __('sales.Expired'),
                                'scheduled' => __('sales.Scheduled'),
                                'disabled' => __('sales.Disabled'),
                            ];
                            $cardBorder =
                                $status === 'active'
                                    ? 'border-green-200'
                                    : ($status === 'scheduled'
                                        ? 'border-blue-200'
                                        : 'border-gray-200');
                        @endphp

                        <div class="bg-white rounded-xl border {{ $cardBorder }} shadow-sm overflow-hidden">
                            {{-- Header --}}
                            <div class="p-5 flex flex-wrap items-start gap-3 justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <h3 class="text-lg font-bold text-gray-800 truncate">{{ $sale->name }}</h3>
                                        <span
                                            class="text-xs font-semibold px-2.5 py-1 rounded-full border {{ $statusColors[$status] ?? $statusColors['disabled'] }}">
                                            {{ $statusLabels[$status] ?? $status }}
                                        </span>
                                    </div>
                                    @if ($sale->description)
                                        <p class="text-sm text-gray-500 mt-1">{{ $sale->description }}</p>
                                    @endif
                                    <div class="flex flex-wrap gap-4 mt-2 text-xs text-gray-500">
                                        @if ($sale->start_date)
                                            <span>{{ __('sales.Start') }}:
                                                <strong>{{ $sale->start_date->format('Y-m-d') }}</strong></span>
                                        @endif
                                        @if ($sale->end_date)
                                            <span>{{ __('sales.End') }}:
                                                <strong>{{ $sale->end_date->format('Y-m-d') }}</strong></span>
                                        @else
                                            <span class="text-green-600">{{ __('sales.No expiry') }}</span>
                                        @endif
                                        <span>{{ $sale->rules->count() }} {{ __('sales.rules') }}</span>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    {{-- Toggle active --}}
                                    <button onclick="toggleSale({{ $sale->id }}, this)"
                                        data-active="{{ $sale->is_active ? '1' : '0' }}"
                                        title="{{ $sale->is_active ? __('sales.Disable') : __('sales.Enable') }}"
                                        class="p-2 rounded-lg transition-colors {{ $sale->is_active ? 'bg-green-50 text-green-600 hover:bg-green-100' : 'bg-gray-100 text-gray-400 hover:bg-gray-200' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="{{ $sale->is_active ? 'M5 13l4 4L19 7' : 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636' }}" />
                                        </svg>
                                    </button>

                                    {{-- Extend date --}}
                                    @if ($sale->end_date)
                                        <button
                                            onclick="openExtendModal({{ $sale->id }}, '{{ $sale->end_date->format('Y-m-d') }}')"
                                            title="{{ __('sales.Extend End Date') }}"
                                            class="p-2 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </button>
                                    @endif

                                    {{-- Edit --}}
                                    <button onclick='openEditModal(@json($sale->load('rules.product')))'
                                        title="{{ __('sales.Edit') }}"
                                        class="p-2 rounded-lg bg-orange-50 text-orange-600 hover:bg-orange-100 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>

                                    {{-- Delete --}}
                                    <form method="POST" action="{{ route('sales.destroy', $sale) }}"
                                        onsubmit="return confirm('{{ __('sales.Confirm delete sale') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="{{ __('sales.Delete') }}"
                                            class="p-2 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- Rules --}}
                            @if ($sale->rules->isNotEmpty())
                                <div class="border-t border-gray-100 bg-gray-50 px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($sale->rules as $rule)
                                            <div
                                                class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-xs">
                                                <span
                                                    class="font-medium text-gray-700">{{ $rule->product->name ?? '—' }}</span>
                                                <span class="text-gray-400">|</span>
                                                @if ($rule->applies_every_n > 1)
                                                    <span class="text-purple-600 font-semibold">
                                                        {{ __('sales.Every :n pcs', ['n' => $rule->applies_every_n]) }}
                                                    </span>
                                                    <span class="text-gray-400">→</span>
                                                @endif
                                                <span class="text-orange-600 font-bold">
                                                    @if ($rule->discount_type === 'percentage')
                                                        {{ $rule->discount_value }}%
                                                    @else
                                                        -₪{{ number_format($rule->discount_value, 2) }}
                                                    @endif
                                                    {{ __('sales.off') }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════ CREATE / EDIT MODAL ═══════════════════════ --}}
    <div id="sale-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-start justify-center min-h-screen pt-8 pb-8 px-4">
            <div class="fixed inset-0 bg-black/50" onclick="closeSaleModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl z-10">
                <div class="flex items-center justify-between p-6 border-b border-gray-100">
                    <h2 class="text-xl font-bold text-gray-800" id="modal-title">{{ __('sales.Create Sale') }}</h2>
                    <button onclick="closeSaleModal()"
                        class="p-2 rounded-lg text-gray-400 hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form id="sale-form" method="POST" action="{{ route('sales.store') }}" class="p-6 space-y-5">
                    @csrf
                    <span id="method-override"></span>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('sales.Sale Name') }}
                                *</label>
                            <input type="text" name="name" id="input-name" required maxlength="255"
                                placeholder="{{ __('sales.E.g. Summer Sale') }}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                        </div>
                        <div>
                            <label
                                class="block text-sm font-semibold text-gray-700 mb-1">{{ __('sales.Description') }}</label>
                            <input type="text" name="description" id="input-description" maxlength="1000"
                                placeholder="{{ __('sales.Optional description') }}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                        <div>
                            <label
                                class="block text-sm font-semibold text-gray-700 mb-1">{{ __('sales.Start Date') }}</label>
                            <input type="date" name="start_date" id="input-start-date"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                            <p class="text-xs text-gray-400 mt-1">{{ __('sales.Leave empty to start immediately') }}
                            </p>
                        </div>
                        <div>
                            <label
                                class="block text-sm font-semibold text-gray-700 mb-1">{{ __('sales.End Date') }}</label>
                            <input type="date" name="end_date" id="input-end-date"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                            <p class="text-xs text-gray-400 mt-1">{{ __('sales.Leave empty for no expiry') }}</p>
                        </div>
                        <div class="flex items-center gap-3 pb-1">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" id="input-is-active" value="1" checked
                                    class="sr-only peer">
                                <div
                                    class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-orange-300 rounded-full peer peer-checked:bg-orange-500 transition-colors after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full">
                                </div>
                            </label>
                            <span class="text-sm font-semibold text-gray-700">{{ __('sales.Active') }}</span>
                        </div>
                    </div>

                    {{-- Rules builder --}}
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">
                                {{ __('sales.Discount Rules') }}</h3>
                            <button type="button" onclick="addRule()"
                                class="flex items-center gap-1.5 text-sm font-medium text-orange-600 hover:text-orange-700 bg-orange-50 hover:bg-orange-100 px-3 py-1.5 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                {{ __('sales.Add Rule') }}
                            </button>
                        </div>

                        <div id="rules-container" class="space-y-3">
                            {{-- Rules injected by JS --}}
                        </div>

                        <div id="no-rules-msg"
                            class="text-center py-6 border-2 border-dashed border-gray-200 rounded-xl text-gray-400 text-sm">
                            {{ __('sales.No rules yet. Click Add Rule to create one.') }}
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                        <button type="button" onclick="closeSaleModal()"
                            class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50 transition-colors">
                            {{ __('sales.Cancel') }}
                        </button>
                        <button type="submit"
                            class="px-5 py-2.5 rounded-lg bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold transition-colors shadow-sm">
                            {{ __('sales.Save Sale') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════ EXTEND DATE MODAL ═══════════════════════ --}}
    <div id="extend-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" onclick="closeExtendModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10 p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">{{ __('sales.Extend End Date') }}</h2>
            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('sales.New End Date') }}</label>
            <input type="date" id="extend-date-input"
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 mb-4">
            <div class="flex gap-3 justify-end">
                <button onclick="closeExtendModal()"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
                    {{ __('sales.Cancel') }}
                </button>
                <button onclick="confirmExtend()"
                    class="px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold">
                    {{ __('sales.Extend') }}
                </button>
            </div>
        </div>
    </div>

    <script>
        // ─── Product list for rule selects ───────────────────────────────────────
        const allProducts = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'selling_price' => $p->selling_price]));

        let ruleIndex = 0;
        let editingSaleId = null;
        let extendingSaleId = null;

        // ─── Modal helpers ────────────────────────────────────────────────────────
        function openCreateModal() {
            editingSaleId = null;
            document.getElementById('modal-title').textContent = @js(__('sales.Create Sale'));
            document.getElementById('sale-form').action = @js(route('sales.store'));
            document.getElementById('method-override').innerHTML = '';
            resetForm();
            document.getElementById('sale-modal').classList.remove('hidden');
            // Add one empty rule by default
            if (document.querySelectorAll('.rule-row').length === 0) addRule();
        }

        function openEditModal(sale) {
            editingSaleId = sale.id;
            document.getElementById('modal-title').textContent = @js(__('sales.Edit Sale'));
            document.getElementById('sale-form').action = `/sales/${sale.id}`;
            document.getElementById('method-override').innerHTML =
                '<input type="hidden" name="_method" value="PUT">';

            // Fill basic fields
            document.getElementById('input-name').value = sale.name ?? '';
            document.getElementById('input-description').value = sale.description ?? '';
            document.getElementById('input-start-date').value = sale.start_date ?? '';
            document.getElementById('input-end-date').value = sale.end_date ?? '';
            document.getElementById('input-is-active').checked = !!sale.is_active;

            // Clear & populate rules
            document.getElementById('rules-container').innerHTML = '';
            ruleIndex = 0;
            (sale.rules || []).forEach(rule => {
                addRule({
                    product_id: rule.product_id,
                    discount_type: rule.discount_type,
                    discount_value: rule.discount_value,
                    applies_every_n: rule.applies_every_n,
                });
            });

            updateNoRulesMsg();
            document.getElementById('sale-modal').classList.remove('hidden');
        }

        function closeSaleModal() {
            document.getElementById('sale-modal').classList.add('hidden');
        }

        function resetForm() {
            document.getElementById('input-name').value = '';
            document.getElementById('input-description').value = '';
            document.getElementById('input-start-date').value = '';
            document.getElementById('input-end-date').value = '';
            document.getElementById('input-is-active').checked = true;
            document.getElementById('rules-container').innerHTML = '';
            ruleIndex = 0;
            updateNoRulesMsg();
        }

        // ─── Rule builder ─────────────────────────────────────────────────────────
        function initProductPicker(idx, defaults) {
            const textInput = document.getElementById(`product-picker-text-${idx}`);
            const hiddenInput = document.getElementById(`product-picker-hidden-${idx}`);
            const dropdown = document.getElementById(`product-picker-dropdown-${idx}`);

            // Pre-fill when editing
            if (defaults.product_id) {
                const product = allProducts.find(p => p.id == defaults.product_id);
                if (product) {
                    textInput.value = product.name + ' (₪' + parseFloat(product.selling_price).toFixed(2) + ')';
                    hiddenInput.value = product.id;
                }
            }

            function renderDropdown(term) {
                let filtered;
                if (!term) {
                    filtered = allProducts.slice(0, 30);
                } else {
                    const words = term.toLowerCase().split(/\s+/).filter(w => w.length > 0);
                    filtered = allProducts.filter(p =>
                        words.every(w => p.name.toLowerCase().includes(w))
                    ).slice(0, 30);
                }
                dropdown.innerHTML = '';
                if (filtered.length === 0) {
                    dropdown.innerHTML =
                        `<p class="text-sm text-gray-500 text-center py-3">@js(__('sales.No products found'))</p>`;
                } else {
                    filtered.forEach(p => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className =
                            'w-full text-left px-3 py-2 text-sm hover:bg-orange-50 border-b border-gray-100 last:border-0 transition-colors';
                        item.textContent = p.name + ' (₪' + parseFloat(p.selling_price).toFixed(2) + ')';
                        item.addEventListener('mousedown', e => {
                            e.preventDefault();
                            textInput.value = p.name + ' (₪' + parseFloat(p.selling_price).toFixed(2) + ')';
                            hiddenInput.value = p.id;
                            dropdown.classList.add('hidden');
                        });
                        dropdown.appendChild(item);
                    });
                }
                dropdown.classList.remove('hidden');
            }

            textInput.addEventListener('input', function() {
                hiddenInput.value = ''; // clear selection while typing
                renderDropdown(this.value.trim());
            });

            textInput.addEventListener('focus', function() {
                renderDropdown(this.value.trim());
            });

            textInput.addEventListener('blur', () => {
                setTimeout(() => dropdown.classList.add('hidden'), 150);
                // If nothing was selected, clear the text
                if (!hiddenInput.value) textInput.value = '';
            });
        }

        function addRule(defaults = {}) {
            const idx = ruleIndex++;
            const html = `
            <div class="rule-row bg-gray-50 border border-gray-200 rounded-xl p-4 relative" data-idx="${idx}">
                <button type="button" onclick="removeRule(${idx})"
                    class="absolute top-3 end-3 p-1 text-gray-400 hover:text-red-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">${@js(__('sales.Product'))}</label>
                        <div class="relative" data-picker-idx="${idx}">
                            <input type="text"
                                id="product-picker-text-${idx}"
                                placeholder="${@js(__('sales.Select Product'))}"
                                autocomplete="off"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                            <input type="hidden"
                                id="product-picker-hidden-${idx}"
                                name="rules[${idx}][product_id]"
                                required>
                            <div id="product-picker-dropdown-${idx}"
                                class="hidden absolute left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-50 overflow-y-auto"
                                style="max-height:240px">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">${@js(__('sales.Type'))}</label>
                        <select name="rules[${idx}][discount_type]" required
                            class="w-full border border-gray-300 rounded-lg px-8 py-2 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                            <option value="amount" ${(defaults.discount_type || 'amount') === 'amount' ? 'selected' : ''}>${@js(__('sales.Fixed Amount (₪)'))}</option>
                            <option value="percentage" ${defaults.discount_type === 'percentage' ? 'selected' : ''}>${@js(__('sales.Percentage (%)'))}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">${@js(__('sales.Discount Value'))}</label>
                        <input type="number" name="rules[${idx}][discount_value]" required
                            min="0.01" step="0.01" value="${defaults.discount_value || ''}"
                            placeholder="${@js(__('sales.e.g. 2'))}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">
                            ${@js(__('sales.Apply every N items (1 = automatic on all, 3 = every 3 items)'))}
                        </label>
                        <div class="flex items-center gap-3">
                            <input type="number" name="rules[${idx}][applies_every_n]" required
                                min="1" step="1" value="${defaults.applies_every_n || 1}"
                                class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                            <span class="text-xs text-gray-500">${@js(__('sales.every_n_hint'))}</span>
                        </div>
                    </div>
                </div>
            </div>`;

            document.getElementById('rules-container').insertAdjacentHTML('beforeend', html);
            initProductPicker(idx, defaults);
            updateNoRulesMsg();
        }

        function removeRule(idx) {
            const el = document.querySelector(`.rule-row[data-idx="${idx}"]`);
            if (el) el.remove();
            updateNoRulesMsg();
        }

        function updateNoRulesMsg() {
            const hasRules = document.querySelectorAll('.rule-row').length > 0;
            document.getElementById('no-rules-msg').style.display = hasRules ? 'none' : 'block';
        }

        // ─── Toggle active ────────────────────────────────────────────────────────
        function toggleSale(saleId, btn) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch(`/sales/${saleId}/toggle-active`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                })
                .then(r => r.json())
                .then(data => {
                    btn.dataset.active = data.is_active ? '1' : '0';
                    // Reload for simplicity (status badge also needs to update)
                    location.reload();
                });
        }

        // ─── Extend date ──────────────────────────────────────────────────────────
        function openExtendModal(saleId, currentEndDate) {
            extendingSaleId = saleId;
            document.getElementById('extend-date-input').value = currentEndDate;
            document.getElementById('extend-modal').classList.remove('hidden');
        }

        function closeExtendModal() {
            document.getElementById('extend-modal').classList.add('hidden');
        }

        function confirmExtend() {
            const newDate = document.getElementById('extend-date-input').value;
            if (!newDate) return;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch(`/sales/${extendingSaleId}/extend-date`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        end_date: newDate
                    }),
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) location.reload();
                });
        }

        // ─── Init ------------------------------------------------------------------
        updateNoRulesMsg();
    </script>
</x-app-layout>
