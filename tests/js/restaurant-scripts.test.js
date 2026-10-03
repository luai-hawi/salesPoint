import test from 'node:test';
import assert from 'node:assert/strict';
import { pathToFileURL } from 'node:url';

class FakeClassList {
    constructor(owner) {
        this.owner = owner;
        this.items = new Set();
    }

    add(...tokens) {
        tokens.filter(Boolean).forEach((token) => this.items.add(token));
    }

    remove(...tokens) {
        tokens.forEach((token) => this.items.delete(token));
    }

    toggle(token, force) {
        if (force === true) {
            this.items.add(token);
            return true;
        }

        if (force === false) {
            this.items.delete(token);
            return false;
        }

        if (this.items.has(token)) {
            this.items.delete(token);
            return false;
        }

        this.items.add(token);
        return true;
    }

    contains(token) {
        return this.items.has(token);
    }
}

class FakeElement {
    constructor(tagName = 'div', id = '') {
        this.tagName = String(tagName).toUpperCase();
        this.id = id;
        this.children = [];
        this.dataset = {};
        this.style = {};
        this.value = '';
        this.type = '';
        this.disabled = false;
        this.checked = false;
        this.listeners = new Map();
        this.className = '';
        this._textContent = '';
        this.classList = new FakeClassList(this);
    }

    set textContent(value) {
        this._textContent = String(value ?? '');
        this.children = [];
    }

    get textContent() {
        return this._textContent + this.children.map((child) => child.textContent).join('');
    }

    set innerHTML(value) {
        throw new Error(`innerHTML is forbidden: ${value}`);
    }

    appendChild(child) {
        this.children.push(child);
        return child;
    }

    replaceChildren(...children) {
        this._textContent = '';
        this.children = children.filter(Boolean);
    }

    addEventListener(type, handler) {
        this.listeners.set(type, handler);
    }

    querySelector() {
        return null;
    }

    querySelectorAll() {
        return [];
    }

    closest() {
        return null;
    }
}

function createDocument(elements) {
    const listeners = new Map();
    const document = {
        hidden: false,
        body: new FakeElement('body'),
        documentElement: new FakeElement('html'),
        fullscreenElement: null,
        createElement(tagName) {
            return new FakeElement(tagName);
        },
        getElementById(id) {
            return elements.get(id) || null;
        },
        querySelector(selector) {
            if (selector === 'meta[name="csrf-token"]') {
                return {
                    getAttribute(name) {
                        return name === 'content' ? 'csrf-token' : null;
                    },
                };
            }

            return null;
        },
        querySelectorAll(selector) {
            if (selector === '.product-row' || selector === '[data-restaurant-close]') {
                return [];
            }

            return [];
        },
        addEventListener(type, handler) {
            listeners.set(type, handler);
        },
        dispatchEvent(event) {
            const handler = listeners.get(event.type);
            if (handler) {
                handler(event);
            }
        },
        exitFullscreen: async () => {
            document.fullscreenElement = null;
        },
    };

    return document;
}

function collectText(node) {
    return (node?.textContent || '').trim();
}

function resetGlobals() {
    for (const key of ['window', 'document', 'navigator', 'CSS', 'Option', 'fetch', 'SP', 'PosCart', 'PosCartHelpers', 'RestaurantPosConfig', 'addEventListener', 'removeEventListener']) {
        delete globalThis[key];
    }
}

async function flush() {
    await new Promise((resolve) => setImmediate(resolve));
    await new Promise((resolve) => setImmediate(resolve));
}

test('restaurant kds renders user payload as text nodes only', async () => {
    resetGlobals();

    const payload = '\"><img src=x onerror=alert(1)>';
    const elements = new Map([
        ['restaurant-kds', new FakeElement('div', 'restaurant-kds')],
        ['kds-column-new', new FakeElement('div', 'kds-column-new')],
        ['kds-column-preparing', new FakeElement('div', 'kds-column-preparing')],
        ['kds-column-ready', new FakeElement('div', 'kds-column-ready')],
        ['kds-column-served', new FakeElement('div', 'kds-column-served')],
        ['kds-station-filter', new FakeElement('select', 'kds-station-filter')],
        ['kds-sound-banner', new FakeElement('div', 'kds-sound-banner')],
        ['kds-connection-banner', new FakeElement('div', 'kds-connection-banner')],
        ['kds-auth-banner', new FakeElement('div', 'kds-auth-banner')],
        ['kds-sound-toggle', new FakeElement('button', 'kds-sound-toggle')],
        ['kds-theme-toggle', new FakeElement('button', 'kds-theme-toggle')],
        ['kds-fullscreen-toggle', new FakeElement('button', 'kds-fullscreen-toggle')],
    ]);

    elements.get('restaurant-kds').dataset.config = JSON.stringify({
        feedUrl: '/kitchen/feed',
        transitionUrl: '/kitchen/tickets/__ID__/transition',
        thresholds: { green: 5, amber: 10, red: 15 },
        pollSeconds: 4,
        translations: {
            allStations: 'All',
            free: 'Free',
            rushBadge: 'Rush',
            start: 'Start',
            ready: 'Ready',
            served: 'Served',
            recall: 'Recall',
            rush: 'Rush',
            cancel: 'Cancel',
            mute: 'Mute',
            unmute: 'Unmute',
            cancelPrompt: 'Reason',
            updateFailed: 'Failed',
            loginRequired: 'Login required',
        },
    });

    globalThis.window = globalThis;
    globalThis.document = createDocument(elements);
    globalThis.navigator = {};
    globalThis.CSS = { escape: (value) => String(value) };
    globalThis.addEventListener = () => {};
    globalThis.removeEventListener = () => {};
    globalThis.Option = function Option(label, value) {
        const option = new FakeElement('option');
        option.textContent = label;
        option.value = value;
        return option;
    };
    globalThis.setTimeout = () => 0;
    globalThis.clearTimeout = () => {};
    globalThis.fetch = async () => new Response(JSON.stringify({
        cursor: 'next',
        etag: 'abc',
        delta: false,
        removed_ids: [],
        tickets: [{
            id: 5,
            number: 12,
            status: 'new',
            priority: 'normal',
            station: payload,
            sent_at: '2026-10-03T10:00:00Z',
            updated_at: '2026-10-03T10:00:00Z',
            items: [{ quantity: 1, name: payload, note: payload }],
            order: {
                id: 9,
                label: payload,
                type: 'delivery',
                table: payload,
                delivery: { name: payload, phone: payload, address: payload },
            },
        }],
    }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
    });

    await import(pathToFileURL('C:\\Users\\lolo_\\OneDrive\\Desktop\\salesPoint\\public\\js\\restaurant-kds.js').href + `?kds=${Date.now()}`);
    await flush();

    const card = elements.get('kds-column-new').children[0];
    assert.ok(card, 'expected one rendered KDS card');
    assert.match(collectText(card), /<img src=x onerror=alert\(1\)>/);
});

test('restaurant pos open orders render user payload as text nodes only', async () => {
    resetGlobals();

    const payload = '\"><img src=x onerror=alert(1)>';
    const elements = new Map([
        ['restaurant-pos-toolbar', new FakeElement('div', 'restaurant-pos-toolbar')],
        ['restaurant-pos-shell', new FakeElement('div', 'restaurant-pos-shell')],
        ['restaurant-order-type', new FakeElement('select', 'restaurant-order-type')],
        ['restaurant-order-guests', new FakeElement('input', 'restaurant-order-guests')],
        ['restaurant-current-order', new FakeElement('span', 'restaurant-current-order')],
        ['restaurant-table-picker-button', new FakeElement('button', 'restaurant-table-picker-button')],
        ['restaurant-save-order', new FakeElement('button', 'restaurant-save-order')],
        ['restaurant-send-kitchen', new FakeElement('button', 'restaurant-send-kitchen')],
        ['restaurant-open-orders', new FakeElement('button', 'restaurant-open-orders')],
        ['restaurant-delivery-panel', new FakeElement('div', 'restaurant-delivery-panel')],
        ['restaurant-delivery-name', new FakeElement('input', 'restaurant-delivery-name')],
        ['restaurant-delivery-phone', new FakeElement('input', 'restaurant-delivery-phone')],
        ['restaurant-delivery-address', new FakeElement('input', 'restaurant-delivery-address')],
        ['restaurant-table-modal', new FakeElement('div', 'restaurant-table-modal')],
        ['restaurant-table-grid', new FakeElement('div', 'restaurant-table-grid')],
        ['restaurant-orders-drawer', new FakeElement('div', 'restaurant-orders-drawer')],
        ['restaurant-orders-list', new FakeElement('div', 'restaurant-orders-list')],
        ['restaurant-orders-backdrop', new FakeElement('div', 'restaurant-orders-backdrop')],
    ]);

    elements.get('restaurant-order-type').value = 'dine_in';

    globalThis.window = globalThis;
    globalThis.document = createDocument(elements);
    globalThis.addEventListener = () => {};
    globalThis.removeEventListener = () => {};
    globalThis.SP = { toast() {} };
    globalThis.PosCart = {
        snapshot() {
            return { rows: [], customer: null };
        },
        async restore() {
            return true;
        },
        clear() {},
        isEmpty() {
            return true;
        },
    };
    globalThis.PosCartHelpers = {
        createUuid() {
            return 'uuid-1';
        },
    };
    globalThis.RestaurantPosConfig = {
        ordersUrl: '/restaurant/orders',
        messages: {
            currentOrderLabelPrefix: 'Order #',
            noActiveOrder: 'No active order',
            chooseTable: 'Choose table',
            pendingPaymentLink: 'Pending',
            loadToPos: 'Load',
            sendToKitchen: 'Send',
            print: 'Print',
            cancel: 'Cancel',
            retry: 'Retry',
            readyToast: 'Ready',
            orderLoaded: 'Loaded',
            orderNotFound: 'Not found',
            requestFailed: 'Failed',
            billLinkPending: 'Pending',
            billLinkFailed: 'Link failed',
            sendFailed: 'Send failed',
        },
        loadOrderUrl: '/restaurant/orders/__ID__/load',
        sendOrderUrl: '/restaurant/orders/__ID__/send',
        printOrderUrl: '/restaurant/orders/__ID__/print',
        cancelOrderUrl: '/restaurant/orders/__ID__/cancel',
        payOrderUrl: '/restaurant/orders/__ID__/paid',
        tablesUrl: '/restaurant/tables',
        storeOrderUrl: '/restaurant/orders',
        showOrderUrl: '/restaurant/orders/__ID__',
    };
    globalThis.setInterval = () => 0;
    globalThis.fetch = async (url) => {
        if (String(url).startsWith('/restaurant/orders')) {
            return new Response(JSON.stringify({
                orders: [{
                    id: 7,
                    label: payload,
                    order_type: payload,
                    status: 'open',
                    total: 13,
                    table_id: 4,
                    table_name: payload,
                    customer_name: payload,
                    latest_ticket_status: 'ready',
                    pending_bill_client_uuid: null,
                }],
            }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            });
        }

        return new Response(JSON.stringify({ tables: [] }), {
            status: 200,
            headers: { 'Content-Type': 'application/json' },
        });
    };

    await import(pathToFileURL('C:\\Users\\lolo_\\OneDrive\\Desktop\\salesPoint\\public\\js\\restaurant-pos.js').href + `?pos=${Date.now()}`);
    await flush();

    const card = elements.get('restaurant-orders-list').children[0];
    assert.ok(card, 'expected one rendered POS order card');
    assert.match(collectText(card), /<img src=x onerror=alert\(1\)>/);
});
