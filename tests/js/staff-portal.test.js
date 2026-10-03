import test from 'node:test';
import assert from 'node:assert/strict';

function createLocalStorage(initial = {}) {
    const store = new Map(Object.entries(initial));

    return {
        get length() {
            return store.size;
        },
        key(index) {
            return Array.from(store.keys())[index] ?? null;
        },
        getItem(key) {
            return store.has(key) ? store.get(key) : null;
        },
        setItem(key, value) {
            store.set(key, String(value));
        },
        removeItem(key) {
            store.delete(key);
        },
        clear() {
            store.clear();
        },
    };
}

function stubNode() {
    return {
        disabled: false,
        textContent: '',
        className: '',
        innerHTML: '',
        addEventListener() {},
        appendChild() {},
        classList: {
            add() {},
            remove() {},
            toggle() {},
        },
    };
}

globalThis.window = globalThis;
globalThis.__STAFF_PORTAL_DISABLE_AUTO_INIT__ = true;
globalThis.StaffPortalBoot = {
    portalKey: 'portal-test',
    locale: 'en',
    dir: 'ltr',
    state: {},
    routes: {},
    strings: {},
};
globalThis.document = {
    documentElement: { dir: 'ltr' },
    getElementById() {
        return stubNode();
    },
};
Object.defineProperty(globalThis, 'navigator', {
    configurable: true,
    value: { onLine: true, geolocation: null },
});
globalThis.localStorage = createLocalStorage();
globalThis.fetch = async () => ({ ok: true, json: async () => ({}) });
globalThis.URL = globalThis.URL;
globalThis.location = { origin: 'https://localhost', hostname: 'localhost' };
Object.defineProperty(globalThis, 'crypto', {
    configurable: true,
    value: { randomUUID: () => 'req-test' },
});
globalThis.setTimeout = globalThis.setTimeout;
globalThis.clearTimeout = globalThis.clearTimeout;
globalThis.setInterval = () => 1;
globalThis.clearInterval = () => {};

await import('../../public/js/staff-portal.js');

const helpers = globalThis.StaffPortalTest;

test('queue keys are namespaced by employee and device identity', () => {
    assert.equal(
        helpers.queueKeyForIdentity({ employee_id: '7', device_id: '22' }),
        'staff-queue:portal-test:7:22'
    );
    assert.notEqual(
        helpers.queueKeyForIdentity({ employee_id: '7', device_id: '22' }),
        helpers.queueKeyForIdentity({ employee_id: '9', device_id: '22' })
    );
});

test('loadQueue discards legacy and mismatched queued punches', () => {
    globalThis.localStorage = createLocalStorage({
        'staff-queue:portal-test': JSON.stringify([{ request_id: 'legacy' }]),
        'staff-queue:portal-test:5:11': JSON.stringify([
            { request_id: 'keep', queue_identity: { employee_id: '5', device_id: '11' } },
            { request_id: 'drop', queue_identity: { employee_id: '5', device_id: '99' } },
            { request_id: 'legacy-shape' },
        ]),
    });

    const queue = helpers.loadQueue({
        authenticated: true,
        employee: { id: 5 },
        device: { id: 11 },
    });

    assert.deepEqual(queue.map((entry) => entry.request_id), ['keep']);
    assert.equal(globalThis.localStorage.getItem('staff-queue:portal-test'), null);
    assert.deepEqual(
        JSON.parse(globalThis.localStorage.getItem('staff-queue:portal-test:5:11')),
        [{ request_id: 'keep', queue_identity: { employee_id: '5', device_id: '11' } }]
    );
});

test('clearPortalQueues removes all queue variants for the current portal only', () => {
    globalThis.localStorage = createLocalStorage({
        'staff-queue:portal-test': '[]',
        'staff-queue:portal-test:5:11': '[]',
        'staff-queue:other-portal:1:1': '[]',
    });

    helpers.clearPortalQueues();

    assert.equal(globalThis.localStorage.getItem('staff-queue:portal-test'), null);
    assert.equal(globalThis.localStorage.getItem('staff-queue:portal-test:5:11'), null);
    assert.equal(globalThis.localStorage.getItem('staff-queue:other-portal:1:1'), '[]');
});
