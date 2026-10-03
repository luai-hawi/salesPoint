(function () {
    const config = window.RestaurantPosConfig;
    const toolbar = document.getElementById('restaurant-pos-toolbar');
    if (!config || !toolbar || !window.PosCart) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const shell = document.getElementById('restaurant-pos-shell');
    if (shell) {
        shell.classList.remove('hidden');
    }

    const state = {
        activeOrderId: null,
        tableId: null,
        tableName: '',
        orderType: 'dine_in',
        guests: '',
        delivery: { name: '', phone: '', address: '' },
        notes: '',
        knownStatuses: new Map(),
        pendingSendUuid: null,
        audioUnlocked: false,
        muted: false,
        linkQueue: [],
        linkInFlight: false,
    };

    const orderTypeSelect = document.getElementById('restaurant-order-type');
    const guestsInput = document.getElementById('restaurant-order-guests');
    const currentOrderBadge = document.getElementById('restaurant-current-order');
    const tableButton = document.getElementById('restaurant-table-picker-button');
    const saveButton = document.getElementById('restaurant-save-order');
    const sendButton = document.getElementById('restaurant-send-kitchen');
    const openOrdersButton = document.getElementById('restaurant-open-orders');
    const deliveryPanel = document.getElementById('restaurant-delivery-panel');
    const deliveryName = document.getElementById('restaurant-delivery-name');
    const deliveryPhone = document.getElementById('restaurant-delivery-phone');
    const deliveryAddress = document.getElementById('restaurant-delivery-address');
    const tableModal = document.getElementById('restaurant-table-modal');
    const tableGrid = document.getElementById('restaurant-table-grid');
    const ordersDrawer = document.getElementById('restaurant-orders-drawer');
    const ordersList = document.getElementById('restaurant-orders-list');
    const ordersBackdrop = document.getElementById('restaurant-orders-backdrop');
    const billForm = document.getElementById('create-bill');

    const originalSnapshot = window.PosCart.snapshot.bind(window.PosCart);
    const originalRestore = window.PosCart.restore.bind(window.PosCart);
    const originalClear = window.PosCart.clear ? window.PosCart.clear.bind(window.PosCart) : null;

    function toast(message, tone) {
        if (window.SP && typeof window.SP.toast === 'function') {
            window.SP.toast(message, tone || 'info');
        }
    }

    function text(value) {
        return String(value ?? '');
    }

    function el(tag, className, value) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (value !== undefined) {
            node.textContent = text(value);
        }

        return node;
    }

    function unlockAudio() {
        state.audioUnlocked = true;
    }

    function beep() {
        if (state.muted || !state.audioUnlocked || !window.AudioContext) {
            return;
        }

        const context = new AudioContext();
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = 'square';
        oscillator.frequency.value = 880;
        gain.gain.value = 0.04;
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start();
        oscillator.stop(context.currentTime + 0.15);
    }

    async function api(url, options) {
        const response = await fetch(url, Object.assign({
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }, options || {}));

        const contentType = response.headers.get('content-type') || '';
        const data = contentType.includes('application/json') ? await response.json() : null;
        if (!response.ok) {
            throw new Error(data?.message || config.messages.requestFailed);
        }

        return data;
    }

    function resetRestaurantState() {
        state.activeOrderId = null;
        state.tableId = null;
        state.tableName = '';
        state.guests = '';
        state.delivery = { name: '', phone: '', address: '' };
        state.notes = '';
        state.pendingSendUuid = null;
        orderTypeSelect.value = 'dine_in';
        guestsInput.value = '';
        deliveryName.value = '';
        deliveryPhone.value = '';
        deliveryAddress.value = '';
        updateBadge();
        toggleDeliveryPanel();
    }

    function updateBadge() {
        currentOrderBadge.textContent = state.activeOrderId
            ? `${config.messages.currentOrderLabelPrefix}${state.activeOrderId}${state.tableName ? ' • ' + state.tableName : ''}`
            : config.messages.noActiveOrder;
        tableButton.textContent = state.tableName || config.messages.chooseTable;
    }

    function toggleDeliveryPanel() {
        deliveryPanel.classList.toggle('hidden', orderTypeSelect.value !== 'delivery');
    }

    function getRowNote(row) {
        return row.dataset.restaurantNote || '';
    }

    function setRowNote(row, note) {
        row.dataset.restaurantNote = note || '';
        const preview = row.querySelector('.restaurant-row-note-preview');
        if (preview) {
            preview.textContent = note || '';
            preview.classList.toggle('hidden', !note);
        }
    }

    function openRowNote(row) {
        unlockAudio();
        const next = window.prompt(config.messages.rowNotePrompt, getRowNote(row));
        if (next === null) {
            return;
        }
        setRowNote(row, next.trim());
    }

    function enhanceRows() {
        document.querySelectorAll('.product-row').forEach((row) => {
            if (row.dataset.restaurantEnhanced === '1') {
                return;
            }

            row.dataset.restaurantEnhanced = '1';
            const actionBar = row.querySelector('td:last-child .flex');
            const nameCell = row.querySelector('.product-name-cell');
            if (nameCell && !nameCell.querySelector('.restaurant-row-note-preview')) {
                const preview = el('div', 'restaurant-row-note-preview mt-1 hidden text-xs font-medium text-amber-600');
                nameCell.appendChild(preview);
            }
            if (actionBar) {
                const button = el('button', 'rounded p-1 text-amber-600 hover:bg-amber-50 hover:text-amber-700', '✎');
                button.type = 'button';
                button.title = config.messages.rowNotePrompt;
                button.addEventListener('click', () => openRowNote(row));
                actionBar.appendChild(button);
            }
            setRowNote(row, row.dataset.restaurantNote || '');
        });

        if (window.PosCart.isEmpty()) {
            resetRestaurantState();
        }
    }

    function snapshotWithRestaurant() {
        const snapshot = originalSnapshot();
        const rows = Array.from(document.querySelectorAll('.product-row'));
        snapshot.rows = snapshot.rows.map((item, index) => Object.assign({}, item, {
            note: getRowNote(rows[index] || document.createElement('div')),
        }));
        snapshot.restaurant = {
            orderId: state.activeOrderId,
            tableId: state.tableId,
            orderType: orderTypeSelect.value,
            guests: guestsInput.value || '',
            delivery: {
                name: deliveryName.value || '',
                phone: deliveryPhone.value || '',
                address: deliveryAddress.value || '',
            },
            notes: document.getElementById('note')?.value || '',
        };

        return snapshot;
    }

    async function restoreWithRestaurant(snapshot) {
        const ok = await originalRestore(snapshot);
        if (!ok) {
            return ok;
        }

        const rows = Array.from(document.querySelectorAll('.product-row'));
        (snapshot.rows || []).forEach((item, index) => {
            if (rows[index]) {
                setRowNote(rows[index], item.note || '');
            }
        });

        if (snapshot.restaurant) {
            state.activeOrderId = snapshot.restaurant.orderId || null;
            state.tableId = snapshot.restaurant.tableId || null;
            state.orderType = snapshot.restaurant.orderType || 'dine_in';
            state.guests = snapshot.restaurant.guests || '';
            state.delivery = Object.assign({ name: '', phone: '', address: '' }, snapshot.restaurant.delivery || {});
            state.notes = snapshot.restaurant.notes || '';
            orderTypeSelect.value = state.orderType;
            guestsInput.value = state.guests;
            deliveryName.value = state.delivery.name;
            deliveryPhone.value = state.delivery.phone;
            deliveryAddress.value = state.delivery.address;
            if (!state.tableName && snapshot.table_label) {
                state.tableName = snapshot.table_label;
            }
        }

        updateBadge();
        toggleDeliveryPanel();
        hydrateTableName();

        return ok;
    }

    window.PosCart.snapshot = snapshotWithRestaurant;
    window.PosCart.restore = restoreWithRestaurant;
    if (originalClear) {
        window.PosCart.clear = function () {
            originalClear();
            resetRestaurantState();
        };
    }

    function currentPayload() {
        const snapshot = window.PosCart.snapshot();
        return {
            table_id: orderTypeSelect.value === 'dine_in' ? state.tableId : null,
            order_type: orderTypeSelect.value,
            guests: guestsInput.value || null,
            customer_id: snapshot.customer?.id || null,
            customer_name: orderTypeSelect.value === 'delivery' ? (deliveryName.value || snapshot.customer?.name || null) : (snapshot.customer?.name || null),
            customer_phone: orderTypeSelect.value === 'delivery' ? (deliveryPhone.value || null) : null,
            customer_address: orderTypeSelect.value === 'delivery' ? (deliveryAddress.value || null) : null,
            note: snapshot.note || '',
            bill_discount_percent: snapshot.bill_discount_percent || 0,
            paid_amount: snapshot.paid_amount || 0,
            payment_method: snapshot.payment_method || 'cash',
            bill_date: snapshot.bill_date || '',
            is_damaged: snapshot.is_damaged || false,
            is_returned: snapshot.is_returned || false,
            rows: snapshot.rows || [],
        };
    }

    async function saveCurrentOrder() {
        unlockAudio();
        if (window.PosCart.isEmpty()) {
            toast(config.messages.orderNotFound, 'warning');
            return null;
        }
        if (orderTypeSelect.value === 'dine_in' && !state.tableId) {
            toast(config.messages.selectTableFirst, 'warning');
            return null;
        }

        const payload = currentPayload();
        const url = state.activeOrderId ? config.showOrderUrl.replace('__ID__', String(state.activeOrderId)) : config.storeOrderUrl;
        const method = state.activeOrderId ? 'PUT' : 'POST';
        const data = await api(url, { method, body: JSON.stringify(payload) });
        const order = data.order || null;

        if (order) {
            state.activeOrderId = order.id;
            state.tableId = order.table_id || null;
            state.tableName = order.table_name || state.tableName;
            updateBadge();
        }

        toast(data.message || config.messages.orderLoaded, 'success');
        await refreshOrders(false);

        return order;
    }

    async function sendCurrentOrder() {
        let order = await saveCurrentOrder();
        if (!order && state.activeOrderId) {
            order = { id: state.activeOrderId };
        }
        if (!order) {
            return;
        }

        state.pendingSendUuid = state.pendingSendUuid || (window.PosCartHelpers ? window.PosCartHelpers.createUuid() : String(Date.now()));

        try {
            const data = await api(config.sendOrderUrl.replace('__ID__', String(order.id)), {
                method: 'POST',
                body: JSON.stringify({ client_uuid: state.pendingSendUuid }),
            });
            toast(data.message, 'success');
            state.pendingSendUuid = null;
            await refreshOrders(false);
        } catch (error) {
            toast(error.message || config.messages.sendFailed, 'error');
        }
    }

    function renderTableCard(table) {
        const occupied = table.status?.code !== 'free';
        const button = el('button', `restaurant-table-option rounded-xl border px-4 py-4 text-start ${occupied ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-white'}`);
        button.type = 'button';
        button.dataset.tableId = String(table.id);
        button.dataset.tableName = table.name;
        button.appendChild(el('div', 'font-semibold text-gray-900', table.name));
        button.appendChild(el('div', 'mt-1 text-xs text-gray-500', `${text(table.zone || '')}${table.seats ? ' • ' + text(table.seats) : ''}`.trim()));
        button.appendChild(el('div', `mt-2 text-xs font-semibold ${occupied ? 'text-amber-700' : 'text-green-700'}`, table.status?.label || ''));

        return button;
    }

    async function openTableModal() {
        unlockAudio();
        const data = await api(config.tablesUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        tableGrid.replaceChildren(...(data.tables || []).map(renderTableCard));
        tableModal.classList.remove('hidden');
    }

    async function hydrateTableName() {
        if (!state.tableId || state.tableName) {
            return;
        }

        try {
            const data = await api(config.tablesUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const table = (data.tables || []).find((entry) => Number(entry.id) === Number(state.tableId));
            if (table) {
                state.tableName = table.name || '';
                updateBadge();
            }
        } catch (error) {
            // Leave the badge in its fallback state.
        }
    }

    function closeOverlays() {
        tableModal.classList.add('hidden');
        ordersDrawer.classList.add('hidden');
        ordersBackdrop.classList.add('hidden');
    }

    function orderCard(order) {
        const card = el('article', 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm');
        const header = el('div', 'flex items-start justify-between gap-2');
        const left = el('div');
        left.appendChild(el('div', 'font-semibold text-gray-900', order.table_name || order.label || ''));
        left.appendChild(el('div', 'text-xs text-gray-500', order.customer_name || ''));
        header.appendChild(left);
        const status = order.pending_bill_client_uuid
            ? config.messages.pendingPaymentLink
            : (order.latest_ticket_status || order.status);
        header.appendChild(el('span', 'rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700', status));
        card.appendChild(header);
        card.appendChild(el('div', 'mt-2 text-sm text-gray-600', `${text(order.order_type)} • ₪${Number(order.total || 0).toFixed(2)}`));

        const actions = el('div', 'mt-3 flex flex-wrap gap-2');
        const loadButton = el('button', 'rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700', config.messages.loadToPos);
        loadButton.type = 'button';
        loadButton.dataset.loadOrder = String(order.id);
        actions.appendChild(loadButton);

        const sendButtonNode = el('button', 'rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700', config.messages.sendToKitchen);
        sendButtonNode.type = 'button';
        sendButtonNode.dataset.sendOrder = String(order.id);
        actions.appendChild(sendButtonNode);

        const printButton = el('button', 'rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700', config.messages.print);
        printButton.type = 'button';
        printButton.dataset.printOrder = String(order.id);
        actions.appendChild(printButton);

        const cancelButton = el('button', 'rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white', config.messages.cancel);
        cancelButton.type = 'button';
        cancelButton.dataset.cancelOrder = String(order.id);
        actions.appendChild(cancelButton);

        if (order.pending_bill_client_uuid) {
            const retryButton = el('button', 'rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-700', config.messages.retry);
            retryButton.type = 'button';
            retryButton.dataset.retryLinkOrder = String(order.id);
            retryButton.dataset.billClientUuid = order.pending_bill_client_uuid;
            actions.appendChild(retryButton);
        }

        card.appendChild(actions);
        return card;
    }

    async function refreshOrders(openDrawerAfter) {
        const data = await api(config.ordersUrl + '?open_only=1', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const orders = data.orders || [];
        const readyTransitions = [];
        orders.forEach((order) => {
            const status = order.latest_ticket_status || order.status;
            const previous = state.knownStatuses.get(order.id);
            if (previous && previous !== status && status === 'ready') {
                readyTransitions.push(order.id);
            }
            state.knownStatuses.set(order.id, status);
        });
        ordersList.replaceChildren(...orders.map(orderCard));

        if (readyTransitions.length) {
            beep();
            toast(config.messages.readyToast, 'success');
        }

        if (openDrawerAfter) {
            ordersDrawer.classList.remove('hidden');
            ordersBackdrop.classList.remove('hidden');
        }
    }

    async function loadOrder(id) {
        const data = await api(config.loadOrderUrl.replace('__ID__', String(id)), {
            method: 'POST',
            body: JSON.stringify({}),
        });
        const ok = await window.PosCart.restore(data.snapshot);
        if (!ok) {
            toast(config.messages.orderNotFound, 'error');
            return;
        }

        state.activeOrderId = data.order.id;
        state.tableId = data.order.table_id || null;
        state.tableName = data.order.table_name || '';
        updateBadge();
        toast(config.messages.orderLoaded, 'success');
        closeOverlays();
    }

    async function cancelOrder(id) {
        const reason = window.prompt(config.messages.cancelPrompt) || '';
        if (!reason) {
            return;
        }
        const data = await api(config.cancelOrderUrl.replace('__ID__', String(id)), {
            method: 'POST',
            body: JSON.stringify({ reason }),
        });
        if (state.activeOrderId === id) {
            resetRestaurantState();
        }
        toast(data.message, 'success');
        await refreshOrders(false);
    }

    async function linkOrderToBill(orderId, payload, silent) {
        try {
            const data = await api(config.payOrderUrl.replace('__ID__', String(orderId)), {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            if (data.order?.pending_bill_client_uuid) {
                if (!silent) {
                    toast(config.messages.billLinkPending, 'warning');
                }
                return false;
            }

            if (state.activeOrderId === orderId) {
                resetRestaurantState();
            }
            await refreshOrders(false);
            if (!silent) {
                toast(data.message, 'success');
            }

            return true;
        } catch (error) {
            if (!silent) {
                toast(config.messages.billLinkFailed, 'warning');
            }
            return false;
        }
    }

    function enqueueBillLink(detail) {
        const snapshot = detail?.snapshot || {};
        const restaurant = snapshot.restaurant || {};
        const orderId = restaurant.orderId || state.activeOrderId;
        if (!orderId) {
            return;
        }

        const payload = {};
        if (Number.isInteger(detail?.bill?.id)) {
            payload.bill_id = detail.bill.id;
        }
        payload.bill_client_uuid = detail?.bill?.client_uuid || snapshot.client_uuid || null;
        if (!payload.bill_id && !payload.bill_client_uuid) {
            return;
        }

        state.linkQueue.push({ orderId, payload });
        flushBillLinks();
    }

    async function flushBillLinks() {
        if (state.linkInFlight || !state.linkQueue.length) {
            return;
        }
        state.linkInFlight = true;
        const next = state.linkQueue.shift();
        if (!next) {
            state.linkInFlight = false;
            return;
        }

        const linked = await linkOrderToBill(next.orderId, next.payload, true);
        if (!linked && next.payload.bill_client_uuid) {
            state.linkQueue.push(next);
        }
        state.linkInFlight = false;
    }

    window.addEventListener('click', unlockAudio, { once: true });
    document.addEventListener('pos:cart-changed', enhanceRows);
    document.addEventListener('pos:bill-saved', function (event) {
        enqueueBillLink(event.detail || {});
    });

    enhanceRows();
    toggleDeliveryPanel();
    updateBadge();
    refreshOrders(false).catch(function () {});

    orderTypeSelect.addEventListener('change', function () {
        state.orderType = this.value;
        if (this.value !== 'dine_in') {
            state.tableId = null;
            state.tableName = '';
        }
        toggleDeliveryPanel();
        updateBadge();
    });

    guestsInput.addEventListener('input', function () {
        state.guests = this.value;
    });
    [deliveryName, deliveryPhone, deliveryAddress].forEach((input) => {
        input.addEventListener('input', function () {
            state.delivery = {
                name: deliveryName.value || '',
                phone: deliveryPhone.value || '',
                address: deliveryAddress.value || '',
            };
        });
    });

    tableButton.addEventListener('click', () => openTableModal().catch((error) => toast(error.message, 'error')));
    saveButton.addEventListener('click', () => saveCurrentOrder().catch((error) => toast(error.message, 'error')));
    sendButton.addEventListener('click', () => sendCurrentOrder().catch((error) => toast(error.message, 'error')));
    openOrdersButton.addEventListener('click', () => refreshOrders(true).catch((error) => toast(error.message, 'error')));
    ordersBackdrop.addEventListener('click', closeOverlays);
    document.querySelectorAll('[data-restaurant-close]').forEach((button) => button.addEventListener('click', closeOverlays));

    tableGrid.addEventListener('click', function (event) {
        const option = event.target.closest('.restaurant-table-option');
        if (!option) return;
        state.tableId = Number(option.dataset.tableId);
        state.tableName = option.dataset.tableName || '';
        updateBadge();
        closeOverlays();
    });

    ordersList.addEventListener('click', function (event) {
        const load = event.target.closest('[data-load-order]');
        if (load) {
            loadOrder(Number(load.dataset.loadOrder)).catch((error) => toast(error.message, 'error'));
            return;
        }
        const send = event.target.closest('[data-send-order]');
        if (send) {
            state.pendingSendUuid = window.PosCartHelpers ? window.PosCartHelpers.createUuid() : String(Date.now());
            api(config.sendOrderUrl.replace('__ID__', send.dataset.sendOrder), {
                method: 'POST',
                body: JSON.stringify({ client_uuid: state.pendingSendUuid }),
            }).then((data) => {
                state.pendingSendUuid = null;
                toast(data.message, 'success');
                refreshOrders(false);
            }).catch((error) => {
                toast(error.message, 'error');
            });
            return;
        }
        const print = event.target.closest('[data-print-order]');
        if (print) {
            window.open(config.printOrderUrl.replace('__ID__', print.dataset.printOrder), '_blank');
            return;
        }
        const cancel = event.target.closest('[data-cancel-order]');
        if (cancel) {
            cancelOrder(Number(cancel.dataset.cancelOrder)).catch((error) => toast(error.message, 'error'));
            return;
        }
        const retry = event.target.closest('[data-retry-link-order]');
        if (retry) {
            linkOrderToBill(Number(retry.dataset.retryLinkOrder), { bill_client_uuid: retry.dataset.billClientUuid }, false);
        }
    });

    if (billForm) {
        billForm.addEventListener('reset', resetRestaurantState);
    }

    setInterval(() => {
        refreshOrders(false).catch(function () {});
        flushBillLinks();
    }, 5000);
})();
