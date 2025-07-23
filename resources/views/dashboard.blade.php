<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Bill') }}
        </h2>
    </x-slot>

    <div class="py-12 flex flex-row space-x-6">
        {{-- Product Selector --}}
        <x-block class="w-2/5">
            <div class="mb-4">
                <input type="text" id="product-search" placeholder="Search products..." class="w-full px-3 py-2 border rounded" />
            </div>
            <div id="product-cards-container">
            <div id="product-results" class="grid grid-cols-3 gap-4">
               
            </div>
            </div>
        </x-block>

        {{-- Bill Form --}}
        <x-block class="w-full">
            <div id="printable" class="p-6 bg-white border-b border-gray-200">
                <form id="create-bill" method="POST" action="{{ route('bills.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label for="note" class="block font-medium text-sm text-gray-700">Note</label>
                        <textarea name="note" id="note" rows="3" class="form-textarea mt-1 block w-full border rounded px-3 py-2"></textarea>
                    </div>

                    <div class="mb-6">
                        <label for="barcode_input" class="block font-medium text-sm text-gray-700">Scan Barcode</label>
                        <input type="text" id="barcode_input" placeholder="Enter barcode..." class="w-1/3 px-3 py-2 border rounded">
                    </div>

                    <div id="products-list"></div>

                    <button type="button" id="add-product-row" class="bg-blue-500 text-white px-4 py-2 rounded mb-4">+ Add Product Manually</button>

                    <div class="flex gap-4">
                        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Create Bill</button>
                        <button type="button" id="clear-all" class="bg-red-500 text-white px-4 py-2 rounded">Clear All</button>
                        <button type="button" id="print-button" class="bg-gray-500 text-white px-4 py-2 rounded">Print</button>
                    </div>
                </form>
            </div>
        </x-block>

        {{-- Totals --}}
        <x-block>
            <div class="grid grid-cols-1 gap-4 mb-4">
                <div>
                    <label for="total_discount" class="block">Total Discount</label>
                    <input type="number" id="total_discount" class="form-input px-3 py-2 border rounded w-full bg-gray-100" readonly>
                </div>
                <div>
                    <label for="total_price" class="block">Total Price</label>
                    <input type="number" name="total_price" id="total_price" class="form-input px-3 py-2 border rounded w-full bg-gray-100" readonly>
                </div>
                <div>
                    <label for="total_sales_today" class="block">Total Sales Today:</label>
                    <input type="number" name="total_sales_today" id="total_sales_today" class="form-input px-3 py-2 border rounded w-full bg-gray-100" readonly>
                </div>
            </div>
        </x-block>
    </div>

    {{-- Printable Invoice --}}
    <div id="print-area" class="print-hidden p-6 text-sm">
        <div class="text-center mb-4">
            <h1 class="text-2xl font-bold">Bee Phone</h1>
            <p>{{ now()->format('Y-m-d H:i') }}</p>
            <hr class="my-2">
        </div>
        <table class="w-full border border-gray-400 text-sm">
            <thead>
                <tr>
                    <th class="border px-2 py-1">Product</th>
                    <th class="border px-2 py-1 text-right">Qty</th>
                    <th class="border px-2 py-1 text-right">Unit Price</th>
                    <th class="border px-2 py-1 text-right">Discount</th>
                    <th class="border px-2 py-1 text-right">Total</th>
                </tr>
            </thead>
            <tbody id="print-products-list"></tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="border px-2 py-1 text-right font-bold">Totals</td>
                    <td id="print-total-discount" class="border px-2 py-1 text-right">0.00₪</td>
                    <td id="print-total-price" class="border px-2 py-1 text-right">0.00₪</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Print Styles --}}
    <style>
         @media print {
        body {
            margin: 0;
            padding: 0;
        }

        /* Hide everything */
        body * {
            visibility: hidden !important;
            height: 0 !important;
            overflow: hidden !important;
        }

        /* Only show the print area */
        #print-area, #print-area * {
            visibility: visible !important;
            height: auto !important;
            overflow: visible !important;
        }

        #print-area {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            padding: 0.5cm !important;
            background: white;
        }

        html, body {
            height: auto !important;
            overflow: visible !important;
        }

        /* Avoid page breaks inside rows */
        #print-area table {
            page-break-inside: auto;
        }

        #print-area tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        /* Prevent empty pages from layout spacing */
        .product-row, .x-block, .py-12, .flex, form {
            display: none !important;
        }
    }
    /* Hide the print area during normal screen view */
    #print-area {
        display: none;
    }

    @media print {
        #print-area {
            display: block !important;
        }
    }
    </style>

    {{-- Scripts --}}
    <script>
        const products = @json($productsForJS);
        const totalSalesToday = {{ $totalToday ?? 0 }};
        document.getElementById('total_sales_today').value = totalSalesToday.toFixed(2);
        const productsList = document.getElementById('products-list');

        document.getElementById('barcode_input').addEventListener('keypress', async e => {
    if (e.key === 'Enter') {
        e.preventDefault();
        const code = e.target.value.trim();
        if (!code) return;

        try {
            const response = await fetch(`/products/search?barcode=${encodeURIComponent(code)}`);
            if (!response.ok) {
                alert('Error fetching product from server.');
                return;
            }
            const product = await response.json();

            if (product && product.id) {
                product.price=product.selling_price;
                addProductRow(product);
                e.target.value = '';
            } else {
                alert('Product not found for barcode: ' + code);
            }
        } catch (err) {
            console.error('Fetch error:', err);
            alert('Failed to fetch product data.');
        }
    }
});



        document.getElementById('add-product-row').addEventListener('click', () => {
            addProductRow();
        });

        document.getElementById('clear-all').addEventListener('click', () => {
            productsList.innerHTML = '';
            calculateTotal();
        });

        document.addEventListener('input', e => {
            if (['quantity', 'discount', 'product-select'].some(cls => e.target.classList.contains(cls))) {
                calculateTotal();
            }
        });

        document.addEventListener('click', e => {
            if (e.target.classList.contains('remove-row')) {
                e.target.closest('.product-row').remove();
                calculateTotal();
            }

            const card = e.target.closest('.product-card');
            if (card) {

                const product = {};
                product.id= parseInt(card.dataset.productId);
                product.name = card.dataset.name;
                product.cost_price = parseFloat(card.dataset.cost);
                product.selling_price = parseFloat(card.dataset.price);

                addProductRow(product);
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target.matches('.product-select') && !e.target.disabled) {
                const selectedId = e.target.value;
                const currentRow = e.target.closest('.product-row');
                if (!selectedId || !currentRow) return;

                const existingRow = Array.from(document.querySelectorAll('.product-row')).find(row => {
                    const hiddenId = row.querySelector('input[name="product_ids[]"]');
                    return hiddenId?.value == selectedId && row !== currentRow;
                });

                if (existingRow) {
                    const qtyInput = existingRow.querySelector('.quantity');
                    qtyInput.value = parseInt(qtyInput.value || 0) + 1;
                    currentRow.remove();
                } else {
                    const product = products.find(p => p.id == selectedId);
                    if (!product) return;

                    const hiddenInputs = `
                        <input type="hidden" name="product_ids[]" value="${product.id}">
                        <input type="hidden" name="cost_prices[]" value="${product.cost_price}">
                        <input type="hidden" name="selling_prices[]" value="${product.price}">
                    `;
                    currentRow.insertAdjacentHTML('afterbegin', hiddenInputs);
                    e.target.disabled = true;
                }

                calculateTotal();
            }
        });


        function addProductRow(product = null) {
            if (product) {
                const existing = [...document.querySelectorAll('input[name="product_ids[]"]')].find(input => input.value == product.id);
                if (existing) {
                    const row = existing.closest('.product-row');
                    const qty = row.querySelector('.quantity');
                    qty.value = parseInt(qty.value) + 1;

                    const manualRow = [...document.querySelectorAll('.product-select')].find(select => !select.disabled && select.value == product.id)?.closest('.product-row');
                    if (manualRow) manualRow.remove();

                    calculateTotal();
                    return;
                }
            }

            const row = document.createElement('div');
            row.className = 'product-row flex flex-wrap gap-2 mb-4 items-end';

            const id = product?.id ?? '';
            const cost = product?.cost_price ?? '';
            const price = product?.selling_price ?? '';

            row.innerHTML = `
                ${product ? `
                    <input type="hidden" name="product_ids[]" value="${id}">
                    <input type="hidden" name="cost_prices[]" value="${cost}">
                    <input type="hidden" name="selling_prices[]" value="${price}">
                ` : ''}
                <div class="flex-1">
                    <div class="flex-1 relative">
                    <label class="text-sm">Product</label>
                    <select class="form-select w-full px-3 py-2 border rounded product-select" ${product ? 'disabled' : ''}>
                        <option value="">Select Product</option>
                        ${products.map(p => `
                            <option value="${p.id}" ${product && p.id === product.id ? 'selected' : ''}>${p.name} (${p.price})</option>
                        `).join('')}
                    </select>
                    </div>

                </div>
                <div class="w-24">
                    <label class="text-sm">Qty</label>
                    <input type="number" name="quantities[]" class="form-input w-full px-2 py-1 border rounded quantity" min="1" value="1" required>
                </div>
                <div class="w-32">
                    <label class="text-sm">Discount</label>
                    <input type="number" name="discounts[]" class="form-input w-full px-2 py-1 border rounded discount" min="0" value="0" required>
                </div>
                <button type="button" class="remove-row bg-red-500 text-white px-2 py-1 rounded h-9">Remove</button>
            `;
            productsList.appendChild(row);
            calculateTotal();
        }

        function calculateTotal() {
            let total = 0, discount = 0;
            document.querySelectorAll('.product-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.quantity')?.value || 0);
                const disc = parseFloat(row.querySelector('.discount')?.value || 0);
                const select = row.querySelector('.product-select');
                const priceMatch = select?.selectedOptions[0]?.textContent.match(/\(([^)]+)\)/);
                const price = parseFloat(priceMatch?.[1] || 0);
                total += (price * qty) - disc;
                discount += disc;
            });
            document.getElementById('total_price').value = total.toFixed(2);
            document.getElementById('total_discount').value = discount.toFixed(2);
        }

        document.getElementById('print-button').addEventListener('click', () => {
    const printList = document.getElementById('print-products-list');
    printList.innerHTML = ''; // Clear previous rows

    let total = 0, discount = 0;

    document.querySelectorAll('.product-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.quantity')?.value || 0);
        const disc = parseFloat(row.querySelector('.discount')?.value || 0);
        const select = row.querySelector('.product-select');
        const name = select?.selectedOptions[0]?.textContent.split('(')[0]?.trim() || 'Unknown';
        const price = parseFloat(select?.selectedOptions[0]?.textContent.match(/\(([^)]+)\)/)?.[1] || 0);
        const sub = (price * qty) - disc;
        total += sub;
        discount += disc;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="border px-2 py-1">${name}</td>
            <td class="border px-2 py-1 text-right">${qty}</td>
            <td class="border px-2 py-1 text-right">${price.toFixed(2)}₪</td>
            <td class="border px-2 py-1 text-right">${disc.toFixed(2)}₪</td>
            <td class="border px-2 py-1 text-right">${sub.toFixed(2)}₪</td>
        `;
        printList.appendChild(tr);
    });

    document.getElementById('print-total-price').textContent = total.toFixed(2) + '₪';
    document.getElementById('print-total-discount').textContent = discount.toFixed(2) + '₪';

    // Finally call print
    window.print();
});

 let debounceTimeout = null;
let currentPage = 1;
let hasMore = true;
let isLoading = false;
let searchTerm = '';

document.addEventListener('DOMContentLoaded', () => {
    searchTerm = '';
    currentPage = 1;
    hasMore = true;
    fetchProducts(true);
})


const container = document.getElementById('product-results');
const searchInput = document.getElementById('product-search');

function renderProductCard(product) {
    
    const card = document.createElement('div');
    card.className = 'bg-white p-2 border rounded shadow text-center cursor-pointer product-card';
    card.dataset.productId = product.id;
    card.dataset.name = product.name.toLowerCase();
    card.dataset.cost = product.cost_price;
    card.dataset.price = product.selling_price;
    

    let firstImage = null;
    try {
        const pictures = typeof product.pictures === 'string' ? JSON.parse(product.pictures) : product.pictures;
        firstImage = Array.isArray(pictures) ? pictures[0] : null;
    } catch (e) {
        console.warn('Invalid pictures JSON for product', product, e);
    }

    card.innerHTML = `
        ${firstImage
            ? `<img src="/storage/${firstImage}" class="w-full h-32 object-contain mb-2">`
            : `<span class="block h-32 flex items-center justify-center text-gray-400">No image</span>`
        }
        <div class="text-sm font-medium">${product.name}</div>
    `;

    container.appendChild(card);
}

function fetchProducts(reset = false) {
    if (isLoading || !hasMore) return;
    isLoading = true;

    if (reset) {
        container.innerHTML = '';
        currentPage = 1;
        hasMore = true;
    }

    fetch(`/products/searchWithoutBarcode?search=${encodeURIComponent(searchTerm)}&page=${currentPage}`)
        .then(response => {
            if (!response.ok) throw new Error('Search failed');
            return response.json();
        })
        .then(data => {
            const products = data.data || [];
            if (products.length === 0 && currentPage === 1) {
                container.innerHTML = '<p class="text-gray-500">No products found</p>';
                hasMore = false;
                return;
            }

            products.forEach(renderProductCard);

            hasMore = data.current_page < data.last_page;
            currentPage++;
        })
        .catch(error => {
            if (currentPage === 1) {
                container.innerHTML = '<p class="text-red-500">Error loading products</p>';
            }
            console.error(error);
        })
        .finally(() => {
            isLoading = false;
        });
}

// Debounced search input handler
searchInput.addEventListener('input', function () {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        searchTerm = searchInput.value.trim(); // Can be empty string

        // Always reset state on new search
        currentPage = 1;
        hasMore = true;

        fetchProducts(true); // Load all products if search is empty
    }, 300);
});


// Lazy loading on scroll
window.addEventListener('scroll', () => {
    const scrollPos = window.scrollY + window.innerHeight;
    const nearBottom = document.documentElement.scrollHeight - 200;
    if (scrollPos >= nearBottom) {
        fetchProducts();
    }
});



        window.addEventListener('DOMContentLoaded', () => {
            const barcodeInput = document.getElementById('barcode_input');
            if (barcodeInput) {
                barcodeInput.focus();
            }
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'F2') {
                e.preventDefault();
                document.getElementById('create-bill').submit();
            }
        });
    </script>
</x-app-layout>
