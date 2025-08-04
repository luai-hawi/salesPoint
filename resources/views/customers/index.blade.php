<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Customers') }}
        </h2>
    </x-slot>

    <div class="py-12 mx-6">

        {{-- Search Bar and Add Button --}}
        <div class="flex justify-between items-center mb-6">
            <input 
                type="text" 
                id="customer-search" 
                placeholder="Search name, phone..." 
                class="px-4 py-2 border rounded w-64"
                value="{{ request('search') }}"
            >
            <a href="{{ route('customers.create') }}" 
               class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                + Add Customer
            </a>
        </div>

        {{-- Table --}}
        <div class="overflow-hidden shadow-sm sm:rounded-lg">
            <table class="min-w-full table-auto">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-xs font-medium">
                        <th class="px-6 py-3 text-left">#</th>
                        <th class="px-6 py-3 text-left">Name</th>
                        <th class="px-6 py-3 text-left">Phone</th>
                        <th class="px-6 py-3 text-left">Balance</th>
                        <th class="px-6 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody id="customers-table-body">
                    @forelse ($customers as $customer)
                        <tr class="bg-white border-b hover:bg-gray-50">
                            <td class="py-3 px-6">{{ $customer->id }}</td>
                            <td class="py-3 px-6">{{ $customer->name }}</td>
                            <td class="py-3 px-6">{{ $customer->phone ?? '-' }}</td>
                            <td class="py-3 px-6 text-red-600 font-semibold">
                                {{ number_format($customer->balance, 2) }}₪
                            </td>
                            <td class="py-3 px-6 space-x-2">
                                <a href="{{ route('customers.show', $customer) }}" 
                                   class="text-yellow-500 hover:text-yellow-700">Edit</a>
                                <a href="{{ route('customers.payments', $customer) }}" 
                                   class="text-blue-500 hover:text-blue-700">Payments</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-3 px-6 text-center text-gray-500">
                                No customers found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div id="pagination-links" class="mt-4 flex justify-center">
            {{ $customers->links('vendor.pagination.custom-light') }}
        </div>
    </div>

    {{-- JS for AJAX Search & Pagination --}}
    <script>
        const typingDelay = 500;
        let typingTimer;
        const searchInput = document.getElementById('customer-search');
        const tableBody = document.getElementById('customers-table-body');
        const paginationLinks = document.getElementById('pagination-links');

        // Load customers via AJAX
        function loadCustomers(url) {
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    const newTbody = doc.querySelector('#customers-table-body');
                    const newPagination = doc.querySelector('#pagination-links');

                    if (newTbody) tableBody.innerHTML = newTbody.innerHTML;
                    if (newPagination) paginationLinks.innerHTML = newPagination.innerHTML;

                    attachPaginationLinks();
                });
        }

        // Debounced search
        searchInput.addEventListener('input', () => {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                const query = searchInput.value.trim();
                const url = new URL('{{ route('customers.index') }}', window.location.origin);
                if (query.length > 0) {
                    url.searchParams.set('search', query);
                }
                loadCustomers(url.toString());
            }, typingDelay);
        });

        // AJAX pagination
        function attachPaginationLinks() {
            document.querySelectorAll('#pagination-links a').forEach(link => {
                link.onclick = function(e) {
                    e.preventDefault();
                    loadCustomers(this.href);
                }
            });
        }

        // Initial bind
        attachPaginationLinks();
    </script>
</x-app-layout>
