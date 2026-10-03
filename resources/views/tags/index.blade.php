<x-app-layout>
    @php
        $canCreateTags = auth()->user()->role !== 'employee' || auth()->user()->hasPermission('create_tags');
        $canDeleteTags = auth()->user()->role !== 'employee' || auth()->user()->hasPermission('delete_tags');
    @endphp
    <x-slot name="header">
        <x-ui.page-header :title="__('messages.Tags Management')" :subtitle="__('messages.Get started by creating a new tag.')">
            <x-ui.badge tone="purple">{{ __('messages.Total Tags') }}: {{ $tags->count() }}</x-ui.badge>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            @if ($canCreateTags)
                <x-ui.card :title="__('messages.Add New Tag')" :subtitle="__('messages.Quick Add Tag')">
                    <form method="POST" action="{{ route('tags.store') }}" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_auto]">
                        @csrf
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">{{ __('messages.Tag Name') }}</label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        </div>
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700">{{ __('messages.Price') }}</label>
                            <input id="price" type="number" name="price" step="0.01" min="0" value="{{ old('price') }}"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        </div>
                        <div class="flex items-end">
                            <button type="submit"
                                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">
                                {{ __('messages.Add Tag') }}
                            </button>
                        </div>
                    </form>
                </x-ui.card>
            @endif

            @if ($tags->isEmpty())
                <x-ui.empty :title="__('messages.No tags')" :text="__('messages.Get started by creating a new tag.')" />
            @else
                <x-ui.card :title="__('messages.Existing Tags')" :padding="false" class="overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('messages.Name') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('messages.Price') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('messages.Created') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('messages.Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach ($tags as $tag)
                                    <tr>
                                        <td class="px-4 py-4 font-medium text-gray-900">{{ $tag->name }}</td>
                                        <td class="px-4 py-4 text-gray-700">₪{{ number_format($tag->price, 2) }}</td>
                                        <td class="px-4 py-4 text-gray-600">{{ $tag->created_at?->format('Y-m-d') }}</td>
                                        <td class="px-4 py-4">
                                            @if ($canDeleteTags)
                                                <form method="POST" action="{{ route('tags.destroy', $tag) }}"
                                                    data-confirm="{{ __('ui.confirm_title') }}"
                                                    data-confirm-message="{{ __('messages.Are you sure you want to delete this tag?') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                                        {{ __('ui.delete') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-app-layout>
