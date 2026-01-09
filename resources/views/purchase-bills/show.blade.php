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
                {{ __('messages.Purchase Bill #') }}{{ $purchaseBill->id }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('purchase-bills.edit', $purchaseBill) }}"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                    {{ __('messages.Edit') }}
                </a>
                <button onclick="printBill()"
                    class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                    {{ __('messages.Print') }}
                </button>
                <a href="{{ route('purchase-bills.index') }}"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-lg transition-colors">
                    {{ __('messages.Back to List') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg" id="bill-content">
                <div class="p-8">
                    <!-- Bill Header -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('messages.Supplier Information') }}
                            </h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="font-semibold text-gray-900">{{ $purchaseBill->supplier->name }}</div>
                                @if ($purchaseBill->supplier->phone)
                                    <div class="text-sm text-gray-600">{{ $purchaseBill->supplier->phone }}</div>
                                @endif
                                @if ($purchaseBill->supplier->email)
                                    <div class="text-sm text-gray-600">{{ $purchaseBill->supplier->email }}</div>
                                @endif
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('messages.Bill Information') }}
                            </h3>
                            <div class="bg-gray-50 p-4 rounded-lg space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('messages.Bill #:') }}</span>
                                    <span class="font-medium">{{ $purchaseBill->id }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('messages.Purchase Date:') }}</span>
                                    <span
                                        class="font-medium">{{ $purchaseBill->purchase_date->format('M d, Y') }}</span>
                                </div>
                                @if ($purchaseBill->reference_number)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">{{ __('messages.Reference:') }}</span>
                                        <span class="font-medium">{{ $purchaseBill->reference_number }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('messages.Created by:') }}</span>
                                    <span class="font-medium">{{ $purchaseBill->creator->name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Products Table -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('messages.Products') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Product') }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Quantity') }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Unit Cost') }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($purchaseBill->products as $product)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="font-medium text-gray-900">{{ $product->name }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ number_format($product->pivot->quantity) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                ₪{{ number_format($product->pivot->unit_cost, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                ₪{{ number_format($product->pivot->total_cost, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="3" class="px-6 py-4 text-right font-medium text-gray-900">
                                            {{ __('messages.Total Amount:') }}</td>
                                        <td class="px-6 py-4 font-bold text-lg text-gray-900">
                                            ₪{{ number_format($purchaseBill->total_amount, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Notes -->
                    @if ($purchaseBill->notes)
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('messages.Notes') }}</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-700">{{ $purchaseBill->notes }}</p>
                            </div>
                        </div>
                    @endif

                    <!-- Actions -->
                    <div class="flex justify-end space-x-3 print:hidden">
                        <a href="{{ route('purchase-bills.create', ['duplicate' => $purchaseBill->id]) }}"
                            class="bg-yellow-600 hover:bg-yellow-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                            {{ __('messages.Duplicate') }}
                        </a>
                        <form method="POST" action="{{ route('purchase-bills.destroy', $purchaseBill) }}"
                            class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition-colors"
                                onclick="return confirm('{{ __('messages.Are you sure? This will reverse all stock changes.') }}')">
                                {{ __('messages.Delete Bill') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Styles -->
    <style>
        @media print {
            body * {
                visibility: hidden;
            }

            #bill-content,
            #bill-content * {
                visibility: visible;
            }

            #bill-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .print\\:hidden {
                display: none !important;
            }

            /* Hide header and other page elements during print */
            header,
            .py-12>.max-w-4xl>.bg-white>.p-8>.print\\:hidden {
                display: none !important;
            }

            /* Ensure proper page margins */
            @page {
                margin: 1in;
                size: A4;
            }

            /* Style the printed bill */
            #bill-content {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
                padding: 20px;
            }
        }
    </style>

    <!-- JavaScript for Print Function -->
    <script>
        function printBill() {
            // Open a new window for printing
            const printWindow = window.open('', '_blank');

            // Get the bill content
            const billContent = document.getElementById('bill-content').innerHTML;

            // Create the print document
            const printDocument = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Purchase Bill #{{ $purchaseBill->id }}</title>
                    <meta charset="utf-8">
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 0;
                            padding: 20px;
                            color: #374151;
                            line-height: 1.6;
                        }
                        .bg-gray-50 {
                            background-color: #f9fafb;
                        }
                        .p-8 { padding: 2rem; }
                        .p-4 { padding: 1rem; }
                        .mb-8 { margin-bottom: 2rem; }
                        .mb-4 { margin-bottom: 1rem; }
                        .grid { display: grid; }
                        .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
                        .md\\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                        .gap-8 { gap: 2rem; }
                        .gap-2 { gap: 0.5rem; }
                        .space-y-2 > * + * { margin-top: 0.5rem; }
                        .text-lg { font-size: 1.125rem; line-height: 1.75rem; }
                        .font-medium { font-weight: 500; }
                        .font-semibold { font-weight: 600; }
                        .font-bold { font-weight: 700; }
                        .text-gray-900 { color: #111827; }
                        .text-gray-700 { color: #374151; }
                        .text-gray-600 { color: #4b5563; }
                        .text-sm { font-size: 0.875rem; line-height: 1.25rem; }
                        .text-xs { font-size: 0.75rem; line-height: 1rem; }
                        .rounded-lg { border-radius: 0.5rem; }
                        .flex { display: flex; }
                        .justify-between { justify-content: space-between; }
                        .overflow-x-auto { overflow-x: auto; }
                        .min-w-full { min-width: 100%; }
                        .divide-y { border-color: #e5e7eb; }
                        .divide-y > :not([hidden]) ~ :not([hidden]) { border-top-width: 1px; }
                        .divide-gray-200 > :not([hidden]) ~ :not([hidden]) { border-color: #e5e7eb; }
                        .border { border-width: 1px; }
                        .border-gray-200 { border-color: #e5e7eb; }
                        table { border-collapse: collapse; width: 100%; }
                        .bg-gray-50 { background-color: #f9fafb; }
                        th, td { padding: 0.75rem 1.5rem; text-align: left; }
                        .px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
                        .py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
                        .py-4 { padding-top: 1rem; padding-bottom: 1rem; }
                        .text-left { text-align: left; }
                        .text-right { text-align: right; }
                        .whitespace-nowrap { white-space: nowrap; }
                        .uppercase { text-transform: uppercase; }
                        .tracking-wider { letter-spacing: 0.05em; }
                        .print\\:hidden { display: none !important; }

                        @media print {
                            @page {
                                margin: 1in;
                                size: A4;
                            }
                            body {
                                margin: 0;
                                padding: 0;
                            }
                        }
                    </style>
                </head>
                <body>
                    ${billContent}
                </body>
                </html>
            `;

            // Write the document and print
            printWindow.document.write(printDocument);
            printWindow.document.close();

            // Wait for content to load then print
            printWindow.onload = function() {
                printWindow.focus();
                printWindow.print();
                // Optionally close the window after printing
                // printWindow.close();
            };
        }
    </script>
</x-app-layout>
