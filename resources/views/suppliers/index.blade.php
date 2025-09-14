<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('messages.Suppliers Management') }}
            </h2>
            <a href="{{ route('suppliers.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                {{ __('messages.Add New Supplier') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Search Filter -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" action="{{ route('suppliers.index') }}" class="flex gap-4">
                        <input type="text" name="search" value="{{ request('search') }}" 
                               placeholder="{{ __('messages.Search suppliers...') }}" 
                               class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition-colors">
                            {{ __('messages.Search') }}
                        </button>
                        @if(request('search'))
                            <a href="{{ route('suppliers.index') }}" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-lg transition-colors">
                                {{ __('messages.Clear') }}
                            </a>
                        @endif
                    </form>
                </div>
            </div>

            <!-- Suppliers List -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    @if($suppliers->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('messages.Name') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('messages.Contact') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('messages.Balance') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('messages.Last Purchase') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('messages.Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($suppliers as $supplier)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium text-gray-900">{{ $supplier->name }}</div>
                                            @if($supplier->email)
                                                <div class="text-sm text-gray-500">{{ $supplier->email }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($supplier->phone)
                                                <div class="text-sm text-gray-900">{{ $supplier->phone }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                {{ $supplier->balance > 0 ? 'bg-red-100 text-red-800' : 
                                                   ($supplier->balance < 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') }}">
                                                ₪{{ number_format(abs($supplier->balance), 2) }}
                                                {{ $supplier->balance > 0 ? '(' . __('messages.We Owe') . ')' : ($supplier->balance < 0 ? '(' . __('messages.They Owe') . ')' : '') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            @php
                                                $lastBill = $supplier->getLastPurchaseBillData(auth()->user()->role === 'employee' ? auth()->user()->shop_owner_id : auth()->id());
                                            @endphp
                                            @if($lastBill['amount'] > 0)
                                                ₪{{ number_format($lastBill['amount'], 2) }}
                                                <div class="text-xs">{{ $lastBill['date'] }}</div>
                                            @else
                                                {{ __('messages.No purchases') }}
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <a href="{{ route('suppliers.edit', $supplier) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('messages.Edit') }}</a>
                                            <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900" 
                                                        onclick="return confirm('{{ __('messages.Are you sure you want to delete this supplier?') }}')">
                                                    {{ __('messages.Delete') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="mt-6">
                            {{ $suppliers->appends(request()->query())->links() }}
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="text-gray-500 text-lg">{{ __('messages.No suppliers found') }}</div>
                            <a href="{{ route('suppliers.create') }}" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                {{ __('messages.Add Your First Supplier') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>