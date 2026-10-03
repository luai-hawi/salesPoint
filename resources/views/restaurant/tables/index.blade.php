<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('restaurant.titles.tables')" :subtitle="__('restaurant.subtitles.tables')">
            <a href="{{ route('kitchen.display') }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('restaurant.buttons.open_kitchen') }}
            </a>
            <a href="{{ route('restaurant.orders.index') }}"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ __('restaurant.buttons.open_orders') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <div class="grid gap-6 lg:grid-cols-2">
                <x-ui.card :title="__('restaurant.buttons.add_table')">
                    <form method="POST" action="{{ route('restaurant.tables.store') }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('restaurant.fields.name') }}</label>
                            <input name="name" class="w-full rounded-lg border-gray-300" maxlength="40">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('restaurant.fields.zone') }}</label>
                            <input name="zone" class="w-full rounded-lg border-gray-300" maxlength="40">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('restaurant.fields.seats') }}</label>
                            <input name="seats" type="number" min="1" max="99" class="w-full rounded-lg border-gray-300">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('restaurant.fields.sort_order') }}</label>
                            <input name="sort_order" type="number" min="0" class="w-full rounded-lg border-gray-300" value="0">
                        </div>
                        <div class="sm:col-span-2">
                            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                {{ __('restaurant.buttons.add_table') }}
                            </button>
                        </div>
                    </form>
                </x-ui.card>

                <x-ui.card :title="__('restaurant.buttons.bulk_create')" :subtitle="__('restaurant.labels.status_hint')">
                    <form method="POST" action="{{ route('restaurant.tables.store') }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('restaurant.fields.range_prefix') }}</label>
                            <input name="bulk_prefix" value="{{ __('restaurant.defaults.table_prefix') }}" class="w-full rounded-lg border-gray-300" maxlength="20">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('restaurant.fields.zone') }}</label>
                            <input name="zone" class="w-full rounded-lg border-gray-300" maxlength="40">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('restaurant.fields.range_from') }}</label>
                            <input name="bulk_from" type="number" min="1" max="500" class="w-full rounded-lg border-gray-300">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('restaurant.fields.range_to') }}</label>
                            <input name="bulk_to" type="number" min="1" max="500" class="w-full rounded-lg border-gray-300">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('restaurant.fields.seats') }}</label>
                            <input name="seats" type="number" min="1" max="99" class="w-full rounded-lg border-gray-300">
                        </div>
                        <div class="sm:col-span-2">
                            <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                {{ __('restaurant.buttons.bulk_create') }}
                            </button>
                        </div>
                    </form>
                </x-ui.card>
            </div>

            @forelse ($zones as $zone => $zoneTables)
                <x-ui.card :title="$zone">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($zoneTables as $table)
                            @php($status = $statusMap[$table->id] ?? ['label' => __('restaurant.status.free'), 'tone' => 'green'])
                            <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">{{ $table->name }}</h3>
                                        <p class="text-sm text-gray-500">
                                            {{ __('restaurant.fields.seats') }}: {{ $table->seats ?: '—' }}
                                        </p>
                                    </div>
                                    <x-ui.badge :tone="$status['tone']">{{ $status['label'] }}</x-ui.badge>
                                </div>

                                <form method="POST" action="{{ route('restaurant.tables.update', $table) }}" class="mt-4 grid gap-3">
                                    @csrf
                                    @method('PUT')
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <input type="text" name="name" value="{{ $table->name }}" class="rounded-lg border-gray-300" maxlength="40">
                                        <input type="text" name="zone" value="{{ $table->zone }}" class="rounded-lg border-gray-300" maxlength="40">
                                        <input type="number" name="seats" value="{{ $table->seats }}" min="1" max="99" class="rounded-lg border-gray-300">
                                        <input type="number" name="sort_order" value="{{ $table->sort_order }}" min="0" class="rounded-lg border-gray-300">
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_active" value="1" @checked($table->is_active) class="rounded border-gray-300 text-indigo-600">
                                        {{ __('restaurant.labels.active') }}
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                            {{ __('restaurant.buttons.save') }}
                                        </button>
                                        <a href="{{ route('restaurant.orders.index', ['table' => $table->id]) }}"
                                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                            {{ __('restaurant.buttons.open_orders') }}
                                        </a>
                                    </div>
                                </form>

                                <form method="POST" action="{{ route('restaurant.tables.destroy', $table) }}" class="mt-3">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                        {{ __('restaurant.buttons.delete') }}
                                    </button>
                                </form>
                            </section>
                        @endforeach
                    </div>
                </x-ui.card>
            @empty
                <x-ui.empty :title="__('restaurant.messages.no_tables')" />
            @endforelse
        </div>
    </div>
</x-app-layout>
