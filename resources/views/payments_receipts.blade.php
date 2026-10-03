@php
    $customerOptions = $customers->map(fn ($customer) => [
        'id' => $customer->id,
        'name' => $customer->name,
        'phone' => $customer->phone,
        'openBills' => $customer->open_bills->map(fn ($row) => ['id' => $row['bill']->id, 'due' => $row['due']])->values()->all(),
    ])->values()->all();
    $employeeOptions = $employees->map(fn ($employee) => ['id' => $employee->id, 'name' => $employee->name])->values()->all();
    $supplierOptions = $suppliers->map(fn ($supplier) => ['id' => $supplier->id, 'name' => $supplier->name, 'phone' => $supplier->phone])->values()->all();
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('messages.Payments and Receipts')" :subtitle="__('receivables.payments_receipts_subtitle')" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card>
                <form method="POST" action="{{ route('payments-receipts.store') }}" class="space-y-6" x-data="receiptForm()">
                    @csrf

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('messages.Transaction Type') }}</label>
                            <select name="transaction_type" x-model="transactionType"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="payment">{{ __('messages.Payment') }}</option>
                                <option value="receipt">{{ __('messages.Receipt') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('messages.Process For') }}</label>
                            <select name="entity_type" x-model="entityType"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="customer">{{ __('messages.Customer') }}</option>
                                <option value="employee">{{ __('messages.Employee') }}</option>
                                <option value="supplier">{{ __('messages.Supplier') }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700" x-text="entityLabel"></label>
                        <select name="entity_id" x-model="entityId" @change="onEntityChanged"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('messages.Select Customer') }}</option>
                            <template x-if="entityType === 'customer'">
                                <template x-for="customer in customers" :key="customer.id">
                                    <option :value="customer.id" x-text="`${customer.name} — ${customer.phone || '—'}`"></option>
                                </template>
                            </template>
                            <template x-if="entityType === 'employee'">
                                <template x-for="employee in employees" :key="employee.id">
                                    <option :value="employee.id" x-text="employee.name"></option>
                                </template>
                            </template>
                            <template x-if="entityType === 'supplier'">
                                <template x-for="supplier in suppliers" :key="supplier.id">
                                    <option :value="supplier.id" x-text="`${supplier.name} — ${supplier.phone || '—'}`"></option>
                                </template>
                            </template>
                        </select>
                    </div>

                    <div x-show="entityType === 'customer' && transactionType === 'receipt'" x-cloak>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.select_bill_optional') }}</label>
                        <select name="bill_id"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('receivables.general_account_payment') }}</option>
                            <template x-for="bill in selectedCustomerBills" :key="bill.id">
                                <option :value="bill.id" x-text="`#${bill.id} — ₪${Number(bill.due).toFixed(2)}`"></option>
                            </template>
                        </select>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('messages.Amount') }}</label>
                            <input type="number" name="amount" step="0.01" min="0.01" required
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('messages.Date') }}</label>
                            <input type="date" name="payment_date" value="{{ now()->toDateString() }}" required
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.payment_method') }}</label>
                            <select name="type"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="cash">{{ __('messages.Cash') }}</option>
                                <option value="card">{{ __('messages.Card') }}</option>
                                <option value="transfer">{{ __('messages.Transfer') }}</option>
                                <option value="check">{{ __('messages.Check') }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.note') }}</label>
                        <textarea name="note" rows="3"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('messages.Submit Transaction') }}
                        </button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('receiptForm', () => ({
                    entityType: 'customer',
                    transactionType: 'payment',
                    entityId: '',
                    customers: @js($customerOptions),
                    employees: @js($employeeOptions),
                    suppliers: @js($supplierOptions),
                    selectedCustomerBills: [],
                    get entityLabel() {
                        return this.entityType === 'customer'
                            ? @js(__('messages.Select Customer'))
                            : (this.entityType === 'employee' ? @js(__('messages.Employee')) : @js(__('messages.Supplier')));
                    },
                    onEntityChanged() {
                        if (this.entityType !== 'customer') {
                            this.selectedCustomerBills = [];
                            return;
                        }

                        const customer = this.customers.find((row) => Number(row.id) === Number(this.entityId));
                        this.selectedCustomerBills = customer ? customer.openBills : [];
                    },
                }));
            });
        </script>
    @endpush
</x-app-layout>
