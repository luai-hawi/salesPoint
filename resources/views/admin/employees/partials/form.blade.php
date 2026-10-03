@php($isEdit = $employee !== null)
<form action="{{ $action }}" method="POST" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-ui.card :title="__('admin.titles.employees')">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.owner_name') }}</label><input name="name" value="{{ old('name', $employee?->name) }}" class="w-full rounded-lg border-gray-300"></div>
            <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.email') }}</label><input type="email" name="email" value="{{ old('email', $employee?->email) }}" class="w-full rounded-lg border-gray-300"></div>
            <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.phone') }}</label><input name="phone_number" value="{{ old('phone_number', $employee?->phone_number) }}" class="w-full rounded-lg border-gray-300"></div>
            <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.password') }}</label><input name="password" value="{{ old('password') }}" class="w-full rounded-lg border-gray-300"></div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">{{ __('admin.fields.shop_name') }}</label>
                <select name="shop_owner_id" class="w-full rounded-lg border-gray-300">
                    @foreach ($shopOwners as $owner)
                        <option value="{{ $owner->id }}" @selected((int) old('shop_owner_id', $employee?->shop_owner_id) === $owner->id)>{{ $owner->name }} ({{ $owner->email }})</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-ui.card>

    <x-ui.card :title="__('permissions.picker.title')">
        <x-permission-picker name="permissions" :selected="old('permissions', $employee?->getPermissions() ?? [])" :showPresets="true" />
    </x-ui.card>

    <div class="flex justify-end gap-2">
        <a href="{{ route('admin.employees.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('admin.actions.cancel') }}</a>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('admin.actions.save') }}</button>
    </div>
</form>
