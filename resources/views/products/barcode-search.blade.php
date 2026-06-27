@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('messages.Barcode Search') }}
            </h2>
            <a href="{{ route('products.index') }}"
                class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                {{ __('messages.Back to Products') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            {{ __('messages.Search Barcode in Purchase Bills') }}</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Barcode Search -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">
                                    {{ __('messages.Search by Barcode') }}</h4>
                                <form method="GET" action="{{ route('products.search-barcode') }}" class="flex gap-2">
                                    <div class="flex-1">
                                        <input type="text" name="barcode"
                                            value="{{ old('barcode', $barcode ?? '') }}"
                                            placeholder="{{ __('messages.Enter barcode to search...') }}"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            required>
                                    </div>
                                    <button type="submit"
                                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                        {{ __('messages.Search') }}
                                    </button>
                                </form>
                            </div>

                            <!-- Product Search -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">
                                    {{ __('messages.Search by Product Name') }}</h4>
                                <div class="relative">
                                    <input type="text" id="product-search"
                                        placeholder="{{ __('messages.Search products by name...') }}"
                                        class="w-full px-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                    <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Search Results -->
                    <div id="product-search-section" class="mt-6" style="display: none;">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('messages.Product Search Results') }}
                        </h3>

                        <!-- Filter Options -->
                        <div class="flex flex-wrap gap-2 mb-4">
                            <button id="filter-all"
                                class="filter-btn active px-3 py-1 text-xs rounded-full bg-green-100 text-green-700 border border-green-200 hover:bg-green-200 transition-colors">
                                {{ __('messages.All Products') }}
                            </button>
                            <button id="filter-in-stock"
                                class="filter-btn px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 transition-colors">
                                {{ __('messages.In Stock Only') }}
                            </button>
                            <button id="filter-out-of-stock"
                                class="filter-btn px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 transition-colors">
                                {{ __('messages.Out of Stock') }}
                            </button>
                        </div>

                        <!-- Product Results -->
                        <div class="bg-white rounded-xl border border-gray-200">
                            <div class="p-4 border-b border-gray-100">
                                <h4 class="font-medium text-gray-800 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4">
                                        </path>
                                    </svg>
                                    {{ __('messages.Available Products') }}
                                </h4>
                            </div>
                            <div id="product-cards-container" class="max-h-80 overflow-y-auto">
                                <div id="product-results"
                                    class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 p-4">
                                    <!-- Products will be loaded here -->
                                </div>
                                <div id="loading-indicator" class="hidden p-4 text-center">
                                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-green-600 mx-auto">
                                    </div>
                                    <p class="text-sm text-gray-500 mt-2">{{ __('messages.Loading products...') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Suppliers Results -->
                    <div id="product-suppliers-section" class="mt-6" style="display: none;">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            {{ __('messages.Suppliers for Selected Product') }}</h3>

                        <div id="suppliers-results">
                            <!-- Suppliers table will be loaded here -->
                        </div>
                    </div>

                    @if (isset($searched) && $searched)

                        {{-- ── IMEI Result ────────────────────────────────────────────── --}}
                        @if (isset($imeiResult) && $imeiResult)
                            @php $imei = $imeiResult; @endphp
                            <div
                                class="mb-6 rounded-xl border-2 {{ $imei->isSold() ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50' }} p-5">
                                <div class="flex items-center gap-3 mb-4">
                                    <div
                                        class="flex-shrink-0 w-10 h-10 rounded-full {{ $imei->isSold() ? 'bg-red-100' : 'bg-green-100' }} flex items-center justify-center">
                                        <svg class="w-5 h-5 {{ $imei->isSold() ? 'text-red-600' : 'text-green-600' }}"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900">{{ __('messages.IMEI Found') }}</h4>
                                        <p
                                            class="text-sm {{ $imei->isSold() ? 'text-red-600 font-medium' : 'text-green-600 font-medium' }}">
                                            {{ $imei->isSold() ? __('messages.IMEI is sold') : __('messages.IMEI is available') }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {{-- Left: Purchase info --}}
                                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                                        <h5 class="text-xs font-semibold text-gray-500 uppercase mb-3">
                                            {{ __('messages.Purchase Info') }}</h5>
                                        <dl class="space-y-2 text-sm">
                                            <div class="flex justify-between">
                                                <dt class="text-gray-500">{{ __('messages.Product') }}</dt>
                                                <dd class="font-medium text-gray-900">{{ $imei->product->name ?? '-' }}
                                                </dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-gray-500">IMEI</dt>
                                                <dd class="font-mono font-medium text-gray-900">{{ $imei->imei }}
                                                </dd>
                                            </div>
                                            @if ($imei->supplier)
                                                <div class="flex justify-between">
                                                    <dt class="text-gray-500">{{ __('messages.Purchased From') }}</dt>
                                                    <dd class="font-medium text-gray-900">{{ $imei->supplier->name }}
                                                    </dd>
                                                </div>
                                            @endif
                                            @if ($imei->purchased_at)
                                                <div class="flex justify-between">
                                                    <dt class="text-gray-500">{{ __('messages.Purchased On') }}</dt>
                                                    <dd class="text-gray-900">
                                                        {{ $imei->purchased_at->format('Y-m-d') }}</dd>
                                                </div>
                                            @endif
                                            @if ($imei->unit_cost)
                                                <div class="flex justify-between">
                                                    <dt class="text-gray-500">{{ __('messages.Unit Cost') }}</dt>
                                                    <dd class="text-gray-900">
                                                        ₪{{ number_format($imei->unit_cost, 2) }}</dd>
                                                </div>
                                            @endif
                                            @if ($imei->purchaseBill)
                                                <div class="flex justify-between">
                                                    <dt class="text-gray-500">{{ __('messages.Purchase Bill ID') }}
                                                    </dt>
                                                    <dd>
                                                        <a href="{{ route('purchase-bills.show', $imei->purchaseBill) }}"
                                                            class="text-blue-600 hover:underline font-medium">#{{ $imei->purchase_bill_id }}</a>
                                                    </dd>
                                                </div>
                                            @endif
                                        </dl>
                                    </div>

                                    {{-- Right: Sale info --}}
                                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                                        <h5 class="text-xs font-semibold text-gray-500 uppercase mb-3">
                                            {{ __('messages.Sale Info') }}</h5>
                                        @if ($imei->isSold())
                                            <dl class="space-y-2 text-sm">
                                                @if ($imei->saleBill && $imei->saleBill->customer)
                                                    <div class="flex justify-between">
                                                        <dt class="text-gray-500">{{ __('messages.Sold To') }}</dt>
                                                        <dd class="font-medium text-gray-900">
                                                            {{ $imei->saleBill->customer->name }}</dd>
                                                    </div>
                                                @endif
                                                @if ($imei->sold_at)
                                                    <div class="flex justify-between">
                                                        <dt class="text-gray-500">{{ __('messages.Sold On') }}</dt>
                                                        <dd class="text-gray-900">
                                                            {{ $imei->sold_at->format('Y-m-d') }}</dd>
                                                    </div>
                                                @endif
                                                @if ($imei->selling_price)
                                                    <div class="flex justify-between">
                                                        <dt class="text-gray-500">{{ __('messages.Unit Price') }}</dt>
                                                        <dd class="text-gray-900">
                                                            ₪{{ number_format($imei->selling_price, 2) }}</dd>
                                                    </div>
                                                @endif
                                                @if ($imei->saleBill)
                                                    <div class="flex justify-between">
                                                        <dt class="text-gray-500">{{ __('messages.Bill ID') }}</dt>
                                                        <dd>
                                                            <a href="{{ route('bills.show', $imei->saleBill) }}"
                                                                class="text-blue-600 hover:underline font-medium">#{{ $imei->sale_bill_id }}</a>
                                                        </dd>
                                                    </div>
                                                    @if ($imei->saleBill->creator)
                                                        <div class="flex justify-between">
                                                            <dt class="text-gray-500">{{ __('messages.Created By') }}
                                                            </dt>
                                                            <dd class="text-gray-900">
                                                                {{ $imei->saleBill->creator->name }}</dd>
                                                        </div>
                                                    @endif
                                                @endif
                                            </dl>
                                        @else
                                            <div class="flex items-center gap-2 text-green-700">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span
                                                    class="text-sm font-medium">{{ __('messages.IMEI is available') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if ($barcodeResults->count() > 0)
                            <div class="mb-6">
                                <h4 class="text-md font-medium text-gray-900 mb-4">
                                    {{ __('messages.Suppliers with Barcode') }}: {{ $barcode }}
                                </h4>

                                <div class="overflow-x-auto">
                                    <table
                                        class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Product') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Supplier') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Purchase Date') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Quantity') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Unit Cost') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Reference') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Barcodes') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach ($barcodeResults as $result)
                                                <tr>
                                                    <td class="px-4 py-3">
                                                        <div class="font-medium text-gray-900">
                                                            {{ $result->product_name }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="font-medium text-gray-900">
                                                            {{ $result->supplier_name }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">
                                                            {{ $result->purchase_date->format('Y-m-d') }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">{{ $result->quantity }}
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">
                                                            ₪{{ number_format($result->unit_cost, 2) }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">
                                                            {{ $result->reference_number ?? '-' }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">
                                                            @if (is_array($result->barcodes))
                                                                {{ implode(', ', $result->barcodes) }}
                                                            @else
                                                                {{ $result->barcodes }}
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if ($productSuppliers->count() > 0)
                            <div class="mb-6">
                                <h4 class="text-md font-medium text-gray-900 mb-4">
                                    {{ __('messages.Suppliers for Product') }}
                                    ({{ __('messages.Barcode not found, showing suppliers who purchased this product') }})
                                </h4>

                                <div class="overflow-x-auto">
                                    <table
                                        class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Product') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Supplier') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Purchase Date') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Quantity') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Unit Cost') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Reference') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach ($productSuppliers as $result)
                                                <tr>
                                                    <td class="px-4 py-3">
                                                        <div class="font-medium text-gray-900">
                                                            {{ $result->product_name }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="font-medium text-gray-900">
                                                            {{ $result->supplier_name }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">
                                                            {{ $result->purchase_date->format('Y-m-d') }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">{{ $result->quantity }}
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">
                                                            ₪{{ number_format($result->unit_cost, 2) }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">
                                                            {{ $result->reference_number ?? '-' }}</div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if ($barcodeResults->count() == 0 && $productSuppliers->count() == 0)
                            <div class="text-center py-8 text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">
                                    {{ __('messages.No results found') }}</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ __('messages.No purchase bills found containing barcode: :barcode', ['barcode' => $barcode]) }}
                                </p>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Styles for product cards and filters -->
    <style>
        .product-card {
            transition: transform 0.15s ease-out, box-shadow 0.15s ease-out;
        }

        .product-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .product-card.out-of-stock {
            opacity: 0.7;
        }

        .filter-btn.active {
            background-color: rgb(34 197 94);
            color: white;
            border-color: rgb(34 197 94);
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            .product-card:hover {
                transform: none;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }
        }
    </style>

    <!-- JavaScript for Product Search -->
    <script>
        // Product search variables
        let currentFilter = 'all';
        let debounceTimeout = null;
        let currentPage = 1;
        let hasMore = true;
        let isLoading = false;
        let searchTerm = '';

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Focus barcode input
            const barcodeInput = document.querySelector('input[name="barcode"]');
            if (barcodeInput) barcodeInput.focus();
        });

        // Filter buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('filter-btn')) {
                document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
                e.target.classList.add('active');

                currentFilter = e.target.id.replace('filter-', '');
                searchTerm = document.getElementById('product-search').value.trim();
                fetchProducts(true);
            }
        });

        // Enhanced product search with debouncing
        document.getElementById('product-search').addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                searchTerm = this.value.trim();
                if (searchTerm.length > 0) {
                    document.getElementById('product-search-section').style.display = 'block';
                    currentPage = 1;
                    hasMore = true;
                    fetchProducts(true);
                } else {
                    document.getElementById('product-search-section').style.display = 'none';
                    document.getElementById('product-suppliers-section').style.display = 'none';
                }
            }, 300);
        });

        // Product fetching
        function fetchProducts(reset = false) {
            if (isLoading || !hasMore) return;
            isLoading = true;

            if (reset) {
                const container = document.getElementById('product-results');
                container.replaceChildren();
                currentPage = 1;
                hasMore = true;
                showLoadingIndicator(true);
            }

            const params = new URLSearchParams({
                search: searchTerm,
                page: currentPage,
                filter: currentFilter,
                per_page: 12
            });

            fetch(`/products/searchAll?${params}`)
                .then(response => {
                    if (!response.ok) throw new Error('Search failed');
                    return response.json();
                })
                .then(data => {
                    const products = data.data || [];

                    if (products.length === 0 && currentPage === 1) {
                        document.getElementById('product-results').innerHTML =
                            '<p class="text-gray-500 text-center py-4 col-span-full">{{ __('messages.No products found') }}</p>';
                        hasMore = false;
                        return;
                    }

                    renderProducts(products);

                    hasMore = data.current_page < data.last_page;
                    currentPage++;
                })
                .catch(error => {
                    if (currentPage === 1) {
                        document.getElementById('product-results').innerHTML =
                            '<p class="text-red-500 text-center py-4 col-span-full">{{ __('messages.Error loading products') }}</p>';
                    }
                    console.error(error);
                })
                .finally(() => {
                    isLoading = false;
                    showLoadingIndicator(false);
                });
        }

        // Render products
        function renderProducts(products) {
            const container = document.getElementById('product-results');
            products.forEach(product => {
                const card = createProductCard(product);
                container.appendChild(card);
            });
        }

        // Create product card
        function createProductCard(product) {
            const images = product.pictures ? (Array.isArray(product.pictures) ? product.pictures : JSON.parse(product
                .pictures)) : [];
            const firstImage = images[0];
            const isOutOfStock = product.quantity <= 0;

            const card = document.createElement('div');
            card.className =
                'product-card bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden cursor-pointer';
            card.dataset.productId = product.id;
            card.onclick = () => showProductSuppliers(product.id, product.name);

            card.innerHTML = `
                <div class="relative overflow-hidden">
                    ${firstImage ? `<img src="/storage/${firstImage}" alt="${product.name}" class="product-image w-full h-32 object-cover">` :
                        `<div class="w-full h-32 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>`}

                    ${isOutOfStock ? `<div class="absolute inset-0 bg-red-500 bg-opacity-80 flex items-center justify-center">
                                <span class="text-white font-bold text-sm">OUT OF STOCK</span>
                            </div>` : ''}

                    <div class="absolute top-1 left-1">
                        <span class="bg-gray-800 bg-opacity-75 text-white text-xs font-mono px-1.5 py-0.5 rounded">#${product.id}</span>
                    </div>
                </div>

                <div class="p-3">
                    <h3 class="font-semibold text-gray-900 text-sm mb-2 line-clamp-2 min-h-[2rem]">${product.name}</h3>

                    ${product.barcode ? `<div class="mb-2 p-1.5 bg-gray-50 rounded border-l-2 border-blue-500">
                                <div class="flex items-center">
                                    <svg class="w-3 h-3 text-gray-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                                    </svg>
                                    <span class="text-xs text-gray-600 font-mono">${product.barcode}</span>
                                </div>
                            </div>` : ''}

                    <div class="grid grid-cols-2 gap-1.5 mb-2">
                        <div class="text-center p-1.5 bg-green-50 rounded">
                            <div class="text-xs text-green-600 font-medium">{{ __('messages.Selling') }}</div>
                            <div class="text-sm font-bold text-green-700">₪${parseFloat(product.selling_price).toFixed(2)}</div>
                        </div>
                        <div class="text-center p-1.5 bg-orange-50 rounded">
                            <div class="text-xs text-orange-600 font-medium">{{ __('messages.Cost') }}</div>
                            <div class="text-sm font-semibold text-orange-700">₪${parseFloat(product.cost_price).toFixed(2)}</div>
                        </div>
                    </div>
                </div>
            `;

            return card;
        }

        // Show loading indicator
        function showLoadingIndicator(show) {
            document.getElementById('loading-indicator').classList.toggle('hidden', !show);
        }

        // Show suppliers for selected product
        function showProductSuppliers(productId, productName) {
            document.getElementById('product-suppliers-section').style.display = 'block';
            document.querySelector('#product-suppliers-section h3').textContent =
                `{{ __('messages.Suppliers for') }} ${productName}`;

            fetch(`/products/get-suppliers?product_id=${productId}`)
                .then(response => response.json())
                .then(suppliers => {
                    renderSuppliers(suppliers);
                })
                .catch(error => {
                    console.error('Error fetching suppliers:', error);
                    document.getElementById('suppliers-results').innerHTML =
                        '<p class="text-red-500">{{ __('messages.Error loading suppliers') }}</p>';
                });
        }

        // Render suppliers table
        function renderSuppliers(suppliers) {
            if (suppliers.length === 0) {
                document.getElementById('suppliers-results').innerHTML =
                    '<p class="text-gray-500 text-center py-4">{{ __('messages.No suppliers found for this product') }}</p>';
                return;
            }

            const table = document.createElement('div');
            table.className = 'overflow-x-auto';
            table.innerHTML = `
                <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Supplier') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Purchase Date') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Quantity') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Unit Cost') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Reference') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${suppliers.map(supplier => `
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">${supplier.supplier_name}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm text-gray-900">${new Date(supplier.purchase_date).toLocaleDateString()}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm text-gray-900">${supplier.quantity}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm text-gray-900">₪${parseFloat(supplier.unit_cost).toFixed(2)}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm text-gray-900">${supplier.reference_number || '-'}</div>
                                        </td>
                                    </tr>
                                `).join('')}
                    </tbody>
                </table>
            `;

            document.getElementById('suppliers-results').innerHTML = '';
            document.getElementById('suppliers-results').appendChild(table);
        }
    </script>
</x-app-layout>
