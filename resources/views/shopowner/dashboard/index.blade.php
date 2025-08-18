<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    <div class="py-12 mx-6">
        <!-- Date Range Filter -->
        <form method="GET" action="{{ route('shopowner.dashboard.index') }}" class="mb-6 flex items-center space-x-4">
            <x-input type="date" name="from" value="{{ request('from') }}" class="w-40" />
            <x-input type="date" name="to" value="{{ request('to') }}" class="w-40" />
            <x-button>Filter</x-button>
        </form>

        <!-- Financial Overview -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
            <x-dashboard-card title="Total Revenue" :value="number_format($totalRevenue, 2)" />
            <x-dashboard-card title="Total Expenses" :value="number_format($totalExpenses, 2)" />
            <x-dashboard-card title="Profit" :value="number_format($profit, 2)" />
        </div>

        <!-- Damaged Goods -->
        <x-dashboard-section title="Damaged Goods">
            <x-dashboard-card title="Quantity" :value="$damagedQuantity" />
            <x-dashboard-card title="Value" :value="number_format($damagedValue, 2)" />
        </x-dashboard-section>

        <!-- Employee Payments -->
        <x-dashboard-section title="Employee Payments">
            @foreach($employees as $employee)
                <x-dashboard-card title="{{ $employee->name }}" :value="number_format($employee->paidThisMonth, 2)" />
                <x-dashboard-card title="Remaining" :value="number_format($employee->remainingThisMonth, 2)" />
            @endforeach
        </x-dashboard-section>

        <!-- Customer Payments -->
        <x-dashboard-section title="Customer Payments">
            <x-dashboard-card title="Total Payments" :value="number_format($totalCustomerPayments, 2)" />
            @foreach($customerBalances as $customerId => $balance)
                <x-dashboard-card title="Customer {{ $customerId }}" :value="number_format($balance, 2)" />
            @endforeach
        </x-dashboard-section>

        <!-- Product Performance -->
        <x-dashboard-section title="Product Performance">
            @foreach($products as $product)
                <x-dashboard-card title="{{ $product->name }}" :value="number_format($product->profit, 2)" />
            @endforeach
        </x-dashboard-section>

        <!-- Charts (Optional) -->
        <!-- Implement charts using a library like Chart.js or ApexCharts -->
    </div>
</x-app-layout>
