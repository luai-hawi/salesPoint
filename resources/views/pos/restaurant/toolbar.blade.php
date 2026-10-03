@if (auth()->check() && auth()->user()->isRestaurantAccount())
    <div id="restaurant-pos-toolbar" class="flex flex-wrap items-center gap-2">
        <select id="restaurant-order-type" class="rounded-xl border border-blue-200 bg-white px-3 py-3 text-sm font-semibold text-blue-700">
            <option value="dine_in">{{ __('restaurant.order_types.dine_in') }}</option>
            <option value="takeaway">{{ __('restaurant.order_types.takeaway') }}</option>
            <option value="delivery">{{ __('restaurant.order_types.delivery') }}</option>
        </select>
        <button type="button" id="restaurant-table-picker-button"
            class="rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm font-semibold text-blue-700 hover:bg-blue-50">
            {{ __('restaurant.labels.table_picker') }}
        </button>
        <input type="number" id="restaurant-order-guests" min="1" max="99" placeholder="{{ __('restaurant.fields.guests') }}"
            class="w-24 rounded-xl border border-blue-200 bg-white px-3 py-3 text-sm text-blue-700">
        <button type="button" id="restaurant-save-order"
            class="rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm font-semibold text-blue-700 hover:bg-blue-50">
            {{ __('restaurant.buttons.save_order') }}
        </button>
        <button type="button" id="restaurant-send-kitchen"
            class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">
            {{ __('restaurant.buttons.send_to_kitchen') }}
        </button>
        <button type="button" id="restaurant-open-orders"
            class="rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm font-semibold text-blue-700 hover:bg-blue-50">
            {{ __('restaurant.buttons.open_orders') }}
        </button>
        <span id="restaurant-current-order" class="rounded-xl bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700">
            {{ __('restaurant.labels.no_active_order') }}
        </span>
    </div>
@endif
