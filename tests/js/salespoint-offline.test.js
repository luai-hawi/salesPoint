import test from 'node:test';
import assert from 'node:assert/strict';
import { pathToFileURL } from 'node:url';

function createElement() {
    return {
        style: {},
        disabled: false,
        textContent: '',
        checked: false,
        addEventListener() {},
        removeEventListener() {},
        querySelectorAll() { return []; }
    };
}

function createFakeIndexedDB() {
    const stores = new Map();

    function ensureStore(name, keyPath = 'localId') {
        if (!stores.has(name)) {
            stores.set(name, { keyPath, records: new Map(), indexes: new Set() });
        }
        return stores.get(name);
    }

    function wrapRequest(executor) {
        const request = {};
        setTimeout(() => {
            try {
                executor(request);
            } catch (error) {
                request.error = error;
                if (request.onerror) {
                    request.onerror({ target: request });
                }
            }
        }, 0);
        return request;
    }

    function createDb() {
        return {
            objectStoreNames: {
                contains(name) {
                    return stores.has(name);
                }
            },
            createObjectStore(name, options = {}) {
                const store = ensureStore(name, options.keyPath || 'localId');
                return {
                    createIndex(indexName) {
                        store.indexes.add(indexName);
                    }
                };
            },
            transaction(name) {
                const store = ensureStore(name);
                const tx = {
                    oncomplete: null,
                    onerror: null,
                    objectStore() {
                        return {
                            put(value) {
                                store.records.set(value[store.keyPath], structuredClone(value));
                            },
                            delete(key) {
                                store.records.delete(key);
                            },
                            get(key) {
                                return wrapRequest((request) => {
                                    request.result = structuredClone(store.records.get(key) || null);
                                    request.onsuccess && request.onsuccess({ target: request });
                                });
                            },
                            getAll() {
                                return wrapRequest((request) => {
                                    request.result = Array.from(store.records.values()).map((value) => structuredClone(value));
                                    request.onsuccess && request.onsuccess({ target: request });
                                });
                            },
                            index(indexName) {
                                return {
                                    getAll(range) {
                                        return wrapRequest((request) => {
                                            request.result = Array.from(store.records.values())
                                                .filter((value) => value[indexName === 'byUser' ? 'userId' : indexName] === range.value)
                                                .map((value) => structuredClone(value));
                                            request.onsuccess && request.onsuccess({ target: request });
                                        });
                                    },
                                    getAllKeys(range) {
                                        return wrapRequest((request) => {
                                            request.result = Array.from(store.records.values())
                                                .filter((value) => value[indexName === 'byUser' ? 'userId' : indexName] === range.value)
                                                .map((value) => value[store.keyPath]);
                                            request.onsuccess && request.onsuccess({ target: request });
                                        });
                                    }
                                };
                            }
                        };
                    }
                };

                setTimeout(() => {
                    tx.oncomplete && tx.oncomplete();
                }, 0);

                return tx;
            }
        };
    }

    return {
        open() {
            const request = {};
            const db = createDb();
            setTimeout(() => {
                request.result = db;
                request.onupgradeneeded && request.onupgradeneeded({ target: { result: db }, oldVersion: 0 });
                setTimeout(() => {
                    request.onsuccess && request.onsuccess({ target: { result: db } });
                }, 0);
            }, 0);
            return request;
        }
    };
}

async function loadSalespointOffline({ online = false } = {}) {
    const notifications = [];
    const fetchCalls = [];
    const elements = new Map([
        ['sp-sync-btn', createElement()],
        ['sp-sync-spinner', createElement()],
        ['sp-sync-icon', createElement()],
        ['sp-sync-badge', createElement()],
        ['sp-sync-label', createElement()],
        ['sp-offline-banner', createElement()]
    ]);

    const document = {
        readyState: 'complete',
        getElementById(id) {
            return elements.get(id) || null;
        },
        querySelector(selector) {
            if (selector === 'meta[name="csrf-token"]') {
                return { content: 'csrf-token' };
            }
            return null;
        },
        querySelectorAll() {
            return [];
        },
        addEventListener() {}
    };

    const fetchMock = async (url, init = {}) => {
        fetchCalls.push({ url: String(url), init });
        if (String(url).startsWith('/offline/sync')) {
            return new Response(JSON.stringify({ bills: { results: [] }, payments: { results: [] }, installments: { results: [] } }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' }
            });
        }

        if (String(url).startsWith('/pos/held')) {
            return new Response(JSON.stringify({ success: true }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' }
            });
        }

        if (String(url).startsWith('/up?_sp_probe=')) {
            return new Response('ok', { status: 200 });
        }

        return new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } });
    };

    globalThis.window = globalThis;
    globalThis.document = document;
    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        value: {
        onLine: online,
        serviceWorker: {
            addEventListener() {},
            ready: Promise.resolve({ sync: { register: async () => {} } }),
            controller: { postMessage() {} }
        }
        }
    });
    globalThis.indexedDB = createFakeIndexedDB();
    globalThis.IDBKeyRange = { only: (value) => ({ value }) };
    globalThis.fetch = fetchMock;
    globalThis.location = { pathname: '/dashboard', href: 'https://example.test/dashboard', origin: 'https://example.test' };
    globalThis.addEventListener = () => {};
    globalThis.removeEventListener = () => {};
    globalThis.dispatchEvent = () => {};
    globalThis.setInterval = () => 1;
    globalThis.clearInterval = () => {};
    globalThis.showNotification = (message, type) => notifications.push({ message, type });
    globalThis.offlineTranslations = {
        pending_count: 'Pending: :count item(s) to sync',
        held_saved_offline: 'Saved offline',
        held_limit_reached: 'Limit :count',
        sync_failed: 'Sync failed',
        synced_success: ':count item(s) synced successfully!',
        sync_partial_fail: ':count item(s) could not be synced and need attention.',
        syncing: 'Syncing pending items with server...'
    };
    globalThis.spCurrentUserId = 5;
    globalThis.localStorage = {
        getItem() { return null; },
        setItem() {},
        removeItem() {}
    };

    const moduleUrl = pathToFileURL('C:\\Users\\lolo_\\OneDrive\\Desktop\\salesPoint\\public\\js\\salespoint-offline.js');
    await import(`${moduleUrl.href}?t=${Date.now()}-${Math.random()}`);
    await new Promise((resolve) => setTimeout(resolve, 20));

    return { fetchCalls, notifications };
}

test('offline held bills can be created listed resumed and deleted', async () => {
    await loadSalespointOffline({ online: false });

    const payload = { payload: { rows: [{ selling_price: 10, quantity: 2 }] }, customer_name: 'A' };
    const localId = await globalThis.spSaveLocalHeldBill(payload);
    assert.ok(localId);

    let list = await globalThis.spListLocalHeldBills();
    assert.equal(list.length, 1);

    const resumed = await globalThis.spTakeLocalHeldBill(localId);
    assert.deepEqual(resumed, payload.payload);
    list = await globalThis.spListLocalHeldBills();
    assert.equal(list.length, 0);

    const secondId = await globalThis.spSaveLocalHeldBill(payload);
    await globalThis.spDeleteLocalHeldBill(secondId);
    list = await globalThis.spListLocalHeldBills();
    assert.equal(list.length, 0);
});

test('offline held bills sync with stable idempotency and are removed locally', async () => {
    const { fetchCalls } = await loadSalespointOffline({ online: false });

    const localId = await globalThis.spSaveLocalHeldBill({
        label: 'Held',
        payload: { rows: [{ selling_price: 12, quantity: 1 }] }
    });

    globalThis.navigator.onLine = true;
    await globalThis.spSyncNow();

    const heldRequest = fetchCalls.find((entry) => String(entry.url).startsWith('/pos/held'));
    assert.ok(heldRequest);
    assert.equal(heldRequest.init.headers['X-Idempotency-Key'] || heldRequest.init.headers.get('X-Idempotency-Key'), localId);

    const body = JSON.parse(heldRequest.init.body);
    assert.equal(body.client_uuid, localId);

    const list = await globalThis.spListLocalHeldBills();
    assert.equal(list.length, 0);
});
