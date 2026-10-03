@if (auth()->check() && auth()->user()->isRestaurantAccount())
    @php
        $restaurantPosConfig = [
            'tablesUrl' => route('restaurant.tables.list'),
            'ordersUrl' => route('restaurant.orders.index'),
            'storeOrderUrl' => route('restaurant.orders.store'),
            'showOrderUrl' => route('restaurant.orders.show', ['order' => '__ID__']),
            'loadOrderUrl' => route('restaurant.orders.load', ['order' => '__ID__']),
            'sendOrderUrl' => route('restaurant.orders.send', ['order' => '__ID__']),
            'payOrderUrl' => route('restaurant.orders.paid', ['order' => '__ID__']),
            'cancelOrderUrl' => route('restaurant.orders.cancel', ['order' => '__ID__']),
            'moveOrderUrl' => route('restaurant.orders.move', ['order' => '__ID__']),
            'mergeOrderUrl' => route('restaurant.orders.merge', ['order' => '__ID__']),
            'printOrderUrl' => route('restaurant.orders.print', ['order' => '__ID__']),
            'messages' => [
                'readyToast' => __('restaurant.messages.kitchen_ready_toast'),
                'sendToKitchen' => __('restaurant.buttons.send_to_kitchen'),
                'openOrders' => __('restaurant.buttons.open_orders'),
                'saveOrder' => __('restaurant.buttons.save_order'),
                'loadToPos' => __('restaurant.buttons.load_to_pos'),
                'print' => __('restaurant.buttons.print'),
                'cancel' => __('restaurant.buttons.cancel'),
                'selectTableFirst' => __('restaurant.messages.select_table_first'),
                'orderLoaded' => __('restaurant.messages.order_loaded'),
                'orderNotFound' => __('restaurant.messages.order_not_found'),
                'chooseTable' => __('restaurant.messages.choose_table'),
                'rowNotePrompt' => __('restaurant.messages.row_note_prompt'),
                'cancelPrompt' => __('restaurant.messages.cancel_prompt'),
                'noActiveOrder' => __('restaurant.labels.no_active_order'),
                'currentOrderLabelPrefix' => __('restaurant.labels.current_order') . ' #',
                'requestFailed' => __('restaurant.messages.order_not_found'),
                'pendingPaymentLink' => __('restaurant.labels.pending_payment_link'),
                'billLinkPending' => __('restaurant.messages.bill_link_pending'),
                'billLinkFailed' => __('restaurant.messages.bill_link_failed'),
                'retry' => __('restaurant.buttons.retry'),
                'sendFailed' => __('restaurant.messages.send_failed'),
            ],
        ];
    @endphp
    <div id="restaurant-pos-shell" class="hidden">
        <div id="restaurant-delivery-panel" class="mt-3 hidden rounded-xl border border-blue-100 bg-blue-50 p-3">
            <div class="grid gap-3 sm:grid-cols-3">
                <input id="restaurant-delivery-name" type="text" placeholder="{{ __('restaurant.fields.customer') }}"
                    class="rounded-lg border-blue-200 bg-white text-sm">
                <input id="restaurant-delivery-phone" type="text" placeholder="{{ __('restaurant.fields.phone') }}"
                    class="rounded-lg border-blue-200 bg-white text-sm">
                <input id="restaurant-delivery-address" type="text" placeholder="{{ __('restaurant.fields.address') }}"
                    class="rounded-lg border-blue-200 bg-white text-sm sm:col-span-3">
            </div>
        </div>

        <div id="restaurant-table-modal" class="fixed inset-0 z-[70] hidden bg-slate-950/50 p-4">
            <div class="mx-auto mt-10 max-w-3xl rounded-2xl bg-white p-5 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">{{ __('restaurant.labels.table_picker') }}</h3>
                    <button type="button" data-restaurant-close="table-modal" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ __('restaurant.buttons.close') }}</button>
                </div>
                <div id="restaurant-table-grid" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"></div>
            </div>
        </div>

        <div id="restaurant-orders-drawer" class="fixed inset-y-0 end-0 z-[70] hidden w-full max-w-md bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-4">
                <h3 class="text-lg font-bold text-gray-900">{{ __('restaurant.buttons.open_orders') }}</h3>
                <button type="button" data-restaurant-close="orders-drawer" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ __('restaurant.buttons.close') }}</button>
            </div>
            <div id="restaurant-orders-list" class="space-y-3 overflow-y-auto p-4"></div>
        </div>
        <div id="restaurant-orders-backdrop" class="fixed inset-0 z-[65] hidden bg-slate-950/50"></div>
    </div>
    <script>
        window.RestaurantPosConfig = @json($restaurantPosConfig);
    </script>
    <script src="{{ \App\Support\Assets::versioned('js/restaurant-pos.js') }}" defer></script>
@endif
