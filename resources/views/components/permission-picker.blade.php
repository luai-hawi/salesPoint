@props([
    'name' => 'permissions',
    'selected' => [],
    'showPresets' => true,
])

@php
    $groups = \App\Support\PermissionCatalog::forPicker();
    $presets = [];
    foreach (\App\Support\PermissionCatalog::presets() as $presetKey => $presetPermissions) {
        $presets[] = [
            'key' => $presetKey,
            'label' => __('permissions.presets.' . $presetKey),
            'permissions' => $presetPermissions,
        ];
    }
    $implies = [];
    foreach ($groups as $group) {
        foreach ($group['permissions'] as $permission) {
            $implies[$permission['key']] = $permission['implies'];
        }
    }
    $initial = \App\Support\PermissionCatalog::normalize(is_array($selected) ? $selected : []);
@endphp

<div x-data="permissionPicker(@js($initial), @js($implies), @js($presets))" {{ $attributes->merge(['class' => 'space-y-4']) }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h4 class="text-sm font-semibold text-gray-900">{{ __('permissions.picker.title') }}</h4>
            <p class="text-xs text-gray-500">{{ __('permissions.picker.hint') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700"
                x-text="@js(__('permissions.picker.selected_count', ['count' => ':count'])).replace(':count', selected.length)"></span>
            <button type="button" @click="selectAll()"
                class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('permissions.picker.select_all') }}</button>
            <button type="button" @click="clearAll()"
                class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('permissions.picker.clear_all') }}</button>
        </div>
    </div>

    @if ($showPresets)
        <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-3">
            <p class="mb-2 text-xs font-semibold text-indigo-900">{{ __('permissions.picker.quick_roles') }}</p>
            <div class="flex flex-wrap gap-2">
                <template x-for="preset in presets" :key="preset.key">
                    <button type="button" @click="applyPreset(preset)"
                        class="rounded-full border border-indigo-200 bg-white px-3 py-1 text-xs font-medium text-indigo-700 transition hover:bg-indigo-600 hover:text-white"
                        x-text="preset.label"></button>
                </template>
            </div>
            <p class="mt-2 text-[11px] text-indigo-700/80">{{ __('permissions.picker.quick_roles_hint') }}</p>
        </div>
    @endif

    <input type="search" x-model.debounce.150ms="query" placeholder="{{ __('permissions.picker.search') }}"
        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">

    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
        @foreach ($groups as $groupKey => $group)
            <fieldset x-show="groupVisible(@js(array_map(fn($p) => $p['label'], $group['permissions'])))"
                class="rounded-xl border border-gray-200 bg-white p-3">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <legend class="text-sm font-semibold text-gray-800">{{ $group['label'] }}</legend>
                    <div class="flex items-center gap-1 text-[11px]">
                        <button type="button" @click="setGroup(@js(array_column($group['permissions'], 'key')), true)"
                            class="rounded px-1.5 py-0.5 font-medium text-indigo-600 hover:bg-indigo-50">{{ __('permissions.picker.select_group') }}</button>
                        <button type="button" @click="setGroup(@js(array_column($group['permissions'], 'key')), false)"
                            class="rounded px-1.5 py-0.5 font-medium text-gray-500 hover:bg-gray-100">{{ __('permissions.picker.clear_group') }}</button>
                    </div>
                </div>
                <div class="space-y-1">
                    @foreach ($group['permissions'] as $permission)
                        <label x-show="matches(@js($permission['label']))"
                            class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                            <input type="checkbox" name="{{ $name }}[]" value="{{ $permission['key'] }}"
                                :checked="selected.includes(@js($permission['key']))"
                                @change="toggle(@js($permission['key']), $event.target.checked)"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="flex-1">{{ $permission['label'] }}</span>
                            @if ($permission['sensitive'])
                                <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">{{ __('permissions.picker.sensitive') }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </div>

    <p x-show="selected.length === 0" class="text-xs text-amber-700">{{ __('permissions.picker.none_selected') }}</p>
    <p class="text-[11px] text-gray-400">{{ __('permissions.picker.implied_notice') }}</p>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('permissionPicker', (initial, implies, presets) => ({
                    selected: initial,
                    implies,
                    presets,
                    query: '',
                    matches(label) {
                        return !this.query || label.toLowerCase().includes(this.query.toLowerCase());
                    },
                    groupVisible(labels) {
                        return labels.some(label => this.matches(label));
                    },
                    toggle(key, checked) {
                        if (checked) {
                            this.add(key);
                        } else {
                            this.remove(key);
                        }
                    },
                    add(key) {
                        if (!this.selected.includes(key)) {
                            this.selected.push(key);
                        }
                        (this.implies[key] || []).forEach(dep => this.add(dep));
                    },
                    remove(key) {
                        this.selected = this.selected.filter(k => k !== key);
                        Object.keys(this.implies).forEach(other => {
                            if ((this.implies[other] || []).includes(key) && this.selected.includes(other)) {
                                this.remove(other);
                            }
                        });
                    },
                    setGroup(keys, checked) {
                        keys.forEach(key => checked ? this.add(key) : this.remove(key));
                    },
                    selectAll() {
                        this.selected = Object.keys(this.implies);
                    },
                    clearAll() {
                        this.selected = [];
                    },
                    applyPreset(preset) {
                        this.selected = [];
                        preset.permissions.forEach(key => this.add(key));
                    },
                }));
            });
        </script>
    @endpush
@endonce
