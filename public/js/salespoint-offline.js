/**
 * SalesPoint Offline Module
 *
 * Provides offline-first capabilities for:
 * - Bill creation
 * - Customer payments
 * - Installment plans
 *
 * All operations are queued in IndexedDB and synced when connectivity returns.
 *
 * Security: records are tagged with userId. Only current user's records are synced.
 * Durability: a queued record is only ever removed after the server confirmed it
 * (or, for held bills, when the user explicitly resumes/deletes it). Logout and
 * user switching never delete unsynced records.
 */
(function () {
    'use strict';

    // ── Configuration ─────────────────────────────────────────────────────
    const CONFIG = {
        dbName: 'sp_offline',
        dbVersion: 3,
        stores: {
            bills: 'pending_bills',
            payments: 'pending_payments',
            installments: 'pending_installments',
            heldBills: 'pending_held_bills',
        },
        syncUrl: '/offline/sync',
        csrfUrl: '/offline/csrf',
        heldSyncUrl: '/pos/held',
        syncTag: 'sp-sync-bills',
        probePath: '/up?_sp_probe=',
        connectivityInterval: 5000,
        probeTimeout: 5000,
        // A bill request on a weak connection is abandoned after this long and
        // stored locally instead. The server de-duplicates on client_uuid, so a
        // request that did reach the server is never created twice.
        billRequestTimeout: 20000,
        syncRequestTimeout: 45000,
        autoSyncRetryDelays: [5000, 15000, 30000, 60000, 120000],
        syncBatch: { bills: 20, payments: 50, installments: 20 },
    };

    // Statuses that mean "the server could not be reached properly" - the bill
    // is kept locally and synced later instead of being reported as failed.
    const OFFLINE_STATUSES = new Set([0, 401, 408, 419, 425, 429, 502, 503, 504, 520, 521, 522, 523, 524]);

    const STORE_NAMES = Object.values(CONFIG.stores);
    const LOCAL_HELD_BILL_LIMIT = 50;

    // ── State ──────────────────────────────────────────────────────────────
    const rawFetch = typeof window.fetch === 'function' ? window.fetch.bind(window) : null;
    let db = null;
    let isSyncing = false;
    let userId = window.spCurrentUserId || null;
    let isOffline = false;
    let autoSyncFailures = 0;
    let nextAutoSyncAt = 0;

    // ── IndexedDB ──────────────────────────────────────────────────────────

    /**
     * Open (or create) the offline database.
     */
    function openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(CONFIG.dbName, CONFIG.dbVersion);

            request.onupgradeneeded = (event) => {
                const database = event.target.result;
                const oldVersion = event.oldVersion;

                // Bills store
                if (oldVersion < 1 && !database.objectStoreNames.contains(CONFIG.stores.bills)) {
                    const store = database.createObjectStore(CONFIG.stores.bills, { keyPath: 'localId' });
                    store.createIndex('byUser', 'userId', { unique: false });
                    store.createIndex('byStatus', 'status', { unique: false });
                }

                // Payments store
                if (oldVersion < 2 && !database.objectStoreNames.contains(CONFIG.stores.payments)) {
                    const store = database.createObjectStore(CONFIG.stores.payments, { keyPath: 'localId' });
                    store.createIndex('byUser', 'userId', { unique: false });
                    store.createIndex('byStatus', 'status', { unique: false });
                }

                // Installments store
                if (oldVersion < 2 && !database.objectStoreNames.contains(CONFIG.stores.installments)) {
                    const store = database.createObjectStore(CONFIG.stores.installments, { keyPath: 'localId' });
                    store.createIndex('byUser', 'userId', { unique: false });
                    store.createIndex('byStatus', 'status', { unique: false });
                }

                // Held bills store
                if (oldVersion < 3 && !database.objectStoreNames.contains(CONFIG.stores.heldBills)) {
                    const store = database.createObjectStore(CONFIG.stores.heldBills, { keyPath: 'localId' });
                    store.createIndex('byUser', 'userId', { unique: false });
                    store.createIndex('byStatus', 'status', { unique: false });
                }
            };

            request.onsuccess = (event) => {
                const database = event.target.result;
                // Another tab upgrading the schema must not leave us with a dead handle.
                if (database && typeof database.addEventListener === 'function') {
                    database.addEventListener('versionchange', () => {
                        try { database.close(); } catch { /* ignore */ }
                        db = null;
                    });
                }
                resolve(database);
            };
            request.onerror = (event) => reject(event.target.error);
            request.onblocked = () => reject(new Error('IndexedDB open blocked'));
        });
    }

    /**
     * Get the database instance, opening it if necessary.
     */
    let dbPromise = null;
    async function getDatabase() {
        if (db) {
            return db;
        }
        if (!dbPromise) {
            dbPromise = openDatabase().then((database) => {
                db = database;
                return database;
            }).finally(() => {
                dbPromise = null;
            });
        }
        return dbPromise;
    }

    // ── Generic Record Operations ──────────────────────────────────────────

    /**
     * Save a record to the specified store.
     */
    async function saveRecord(storeName, data) {
        const database = await getDatabase();

        return new Promise((resolve, reject) => {
            const transaction = database.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const localId = data.localId || data.local_id || generateLocalId();

            store.put({
                ...data,
                localId,
                local_id: data.local_id || localId,
                client_uuid: data.client_uuid || localId,
                operation_key: data.operation_key || data.client_uuid || data.local_id || localId,
                userId: data.userId || userId,
                status: data.status || 'pending',
                savedAt: data.savedAt || new Date().toISOString(),
            });

            transaction.oncomplete = () => resolve(localId);
            transaction.onerror = (event) => reject(event.target.error);
            transaction.onabort = (event) => reject((event && event.target && event.target.error) || new Error('Transaction aborted'));
        });
    }

    async function deleteRecord(storeName, localId) {
        const database = await getDatabase();

        return new Promise((resolve, reject) => {
            const transaction = database.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            store.delete(localId);

            transaction.oncomplete = () => resolve();
            transaction.onerror = (event) => reject(event.target.error);
        });
    }

    /**
     * Remove records that the server already confirmed. Unsynced (pending)
     * records are never touched here - they must survive logout, user
     * switching and reloads until they reach the server.
     */
    async function purgeSyncedRecords(targetUserId = userId) {
        if (!targetUserId) return;

        const database = await getDatabase();
        await Promise.all(STORE_NAMES.map((storeName) => new Promise((resolve, reject) => {
            const transaction = database.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const index = store.index('byUser');
            const request = index.getAll(IDBKeyRange.only(targetUserId));

            request.onsuccess = (event) => {
                const records = event.target.result || [];
                records
                    .filter((record) => record && record.status === 'synced')
                    .forEach((record) => store.delete(record.localId));
            };
            request.onerror = (event) => reject(event.target.error);
            transaction.oncomplete = () => resolve();
            transaction.onerror = (event) => reject(event.target.error);
        })));
    }

    /**
     * Get all pending records for the current user from a store.
     */
    async function getPendingRecords(storeName) {
        const records = await getUserRecords(storeName);
        return records.filter((record) => record.status !== 'synced');
    }

    async function getUserRecords(storeName) {
        if (!userId) return [];
        const database = await getDatabase();

        return new Promise((resolve, reject) => {
            const transaction = database.transaction(storeName, 'readonly');
            const store = transaction.objectStore(storeName);
            const index = store.index('byUser');
            const request = index.getAll(IDBKeyRange.only(userId));

            request.onsuccess = (event) => {
                const results = event.target.result || [];
                resolve(results);
            };
            request.onerror = (event) => reject(event.target.error);
        });
    }

    async function loadPendingRecords() {
        const [bills, payments, installments, heldBills] = await Promise.all([
            getPendingRecords(CONFIG.stores.bills),
            getPendingRecords(CONFIG.stores.payments),
            getPendingRecords(CONFIG.stores.installments),
            getPendingRecords(CONFIG.stores.heldBills),
        ]);

        return { bills, payments, installments, heldBills };
    }

    /**
     * Get total count of pending records across all stores.
     */
    async function getTotalPendingCount() {
        const { bills, payments, installments, heldBills } = await loadPendingRecords();
        return bills.length + payments.length + installments.length + heldBills.length;
    }

    async function recordSyncFailure(storeName, record, message) {
        try {
            await saveRecord(storeName, {
                ...record,
                status: 'pending',
                attempts: (record.attempts || 0) + 1,
                lastError: message ? String(message).slice(0, 500) : null,
                lastAttemptAt: new Date().toISOString(),
            });
        } catch {
            // The record itself is still stored; only the diagnostics failed.
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    function generateLocalId() {
        const timestamp = Date.now();
        const random = Math.random().toString(36).slice(2, 9);
        return `rec_${timestamp}_${random}`;
    }

    function ensureClientUuid(localId, clientUuid) {
        return clientUuid || localId || generateLocalId();
    }

    function ensureOperationKey(record, prefix = 'op') {
        return record.operation_key || record.client_uuid || record.local_id || record.localId || `${prefix}_${generateLocalId()}`;
    }

    async function enforceHeldBillLimit() {
        const heldBills = await getPendingRecords(CONFIG.stores.heldBills);
        if (heldBills.length >= LOCAL_HELD_BILL_LIMIT) {
            notify(translate('held_limit_reached', { count: LOCAL_HELD_BILL_LIMIT }), 'warning');
            return false;
        }

        return true;
    }

    function translate(key, replacements) {
        const translations = window.offlineTranslations || {};
        let text = translations[key] || key;

        if (replacements) {
            Object.entries(replacements).forEach(([placeholder, value]) => {
                text = text.replace(`:${placeholder}`, value);
            });
        }

        return text;
    }

    function notify(message, type = 'info') {
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type);
        }
    }

    function normalizeRecord(record) {
        const localId = record.localId || record.local_id;
        const normalized = localId ? { ...record, local_id: localId } : { ...record };
        // Local bookkeeping fields are not part of the server contract.
        delete normalized.attempts;
        delete normalized.lastError;
        delete normalized.lastAttemptAt;
        delete normalized.sync_batch_key;
        return normalized;
    }

    function wait(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    function requestPath(url) {
        try {
            const base = (window.location && window.location.href) || 'http://localhost/';
            const parsed = new URL(url, base);
            if (window.location && window.location.origin && parsed.origin !== window.location.origin) {
                return null;
            }
            return parsed.pathname;
        } catch {
            return null;
        }
    }

    /**
     * fetch() with a hard timeout. Weak mobile connections often leave a
     * request hanging forever, which previously froze the POS.
     */
    function fetchWithTimeout(fetchFn, input, init, timeoutMs) {
        const options = { ...(init || {}) };
        if (typeof AbortController !== 'function' || !timeoutMs) {
            return fetchFn(input, options);
        }

        const controller = new AbortController();
        const callerSignal = options.signal;
        if (callerSignal) {
            if (callerSignal.aborted) {
                controller.abort();
            } else if (typeof callerSignal.addEventListener === 'function') {
                callerSignal.addEventListener('abort', () => controller.abort(), { once: true });
            }
        }

        const timer = setTimeout(() => controller.abort(), timeoutMs);
        options.signal = controller.signal;

        return Promise.resolve()
            .then(() => fetchFn(input, options))
            .finally(() => clearTimeout(timer));
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return (meta && (meta.content || (meta.getAttribute && meta.getAttribute('content')))) || '';
    }

    /**
     * Laravel reads the form "_token" field before the X-CSRF-TOKEN header,
     * so a refreshed token must be written to the meta tag AND every form.
     */
    function setCsrfToken(token) {
        if (!token) return;

        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            if (typeof meta.setAttribute === 'function') {
                meta.setAttribute('content', token);
            } else {
                meta.content = token;
            }
        }

        try {
            document.querySelectorAll('input[name="_token"]').forEach((input) => {
                input.value = token;
            });
        } catch {
            // Ignore DOM access failures.
        }
    }

    async function fetchFreshCsrfToken() {
        if (!rawFetch) return null;

        try {
            const response = await fetchWithTimeout(rawFetch, CONFIG.csrfUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }, CONFIG.probeTimeout * 2);

            if (!response.ok || isUnexpectedJsonResponse(response)) {
                return null;
            }

            const data = await response.json();
            if (data && data.token) {
                setCsrfToken(data.token);
                return data.token;
            }
        } catch {
            // Offline or session expired - the caller falls back gracefully.
        }

        return null;
    }

    /**
     * Laravel answers an expired CSRF token / session with a redirect to the
     * login page. fetch() silently follows it and receives an HTML page with
     * status 200, which is not a real answer to a JSON request.
     */
    function isUnexpectedJsonResponse(response) {
        if (!response) return true;
        if (response.type === 'opaqueredirect') return true;
        if (response.redirected) return true;

        if (response.ok) {
            const contentType = (response.headers && response.headers.get && response.headers.get('Content-Type')) || '';
            if (contentType && !/json/i.test(contentType)) {
                return true;
            }
        }

        return false;
    }

    function applyCsrfTokenToRequest(init, token) {
        if (!token) return;
        const headers = init.headers instanceof Headers ? init.headers : new Headers(init.headers || {});
        headers.set('X-CSRF-TOKEN', token);
        init.headers = headers;

        if (typeof FormData !== 'undefined' && init.body instanceof FormData) {
            init.body.set('_token', token);
        }
    }

    // ── UI Helpers ─────────────────────────────────────────────────────────

    function setOfflineBannerVisibility(visible) {
        const banner = document.getElementById('sp-offline-banner');
        if (banner) {
            banner.style.display = visible ? 'flex' : 'none';
        }
    }

    async function updateSyncButton() {
        let pendingCount = 0;
        try {
            pendingCount = await getTotalPendingCount();
        } catch {
            return 0;
        }

        try {
            const button = document.getElementById('sp-sync-btn');
            const badge = document.getElementById('sp-sync-badge');
            const label = document.getElementById('sp-sync-label');

            if (button) {
                if (pendingCount > 0) {
                    button.style.display = 'flex';
                    if (badge) badge.textContent = pendingCount;
                    if (label) label.textContent = translate('pending_count', { count: pendingCount });
                } else {
                    button.style.display = 'none';
                }
            }
        } catch {
            // UI refresh must never break saving.
        }

        try {
            window.dispatchEvent(new CustomEvent('sp:pending-count', { detail: { count: pendingCount } }));
        } catch {
            // CustomEvent may be unavailable in very old browsers.
        }

        return pendingCount;
    }

    function setSyncButtonLoading(loading) {
        const button = document.getElementById('sp-sync-btn');
        const spinner = document.getElementById('sp-sync-spinner');
        const icon = document.getElementById('sp-sync-icon');

        if (!button) return;

        button.disabled = loading;
        if (spinner) spinner.style.display = loading ? 'block' : 'none';
        if (icon) icon.style.display = loading ? 'none' : 'block';
    }

    // ── Connectivity ───────────────────────────────────────────────────────

    function setOfflineState() {
        if (!isOffline) {
            isOffline = true;
            setOfflineBannerVisibility(true);
        }
    }

    function setOnlineState() {
        if (isOffline) {
            isOffline = false;
            setOfflineBannerVisibility(false);
            // Connection just came back: retry immediately.
            nextAutoSyncAt = 0;
            autoSyncFailures = 0;
        }
    }

    /**
     * Probe the network to detect connectivity changes.
     * Uses the health endpoint to bypass app page rendering and HTTP cache.
     */
    let probeInFlight = null;
    function probeConnectivity() {
        if (probeInFlight) return probeInFlight;

        probeInFlight = (async () => {
            let offline = navigator.onLine === false;

            if (!offline && rawFetch) {
                try {
                    await fetchWithTimeout(rawFetch, `${CONFIG.probePath}${Date.now()}`, {
                        method: 'GET',
                        cache: 'no-store',
                    }, CONFIG.probeTimeout);
                } catch {
                    offline = true;
                }
            }

            if (offline) {
                setOfflineState();
            } else {
                setOnlineState();
            }

            return !offline;
        })().finally(() => {
            probeInFlight = null;
        });

        return probeInFlight;
    }

    /**
     * Probe and, when online with queued records, sync them. Used by the
     * periodic timer and by every "the app is visible again" event, because
     * mobile browsers throttle timers in the background and frequently
     * report navigator.onLine === true while the connection is unusable.
     */
    async function checkAndAutoSync(force = false) {
        const online = await probeConnectivity();
        let pending = 0;
        try {
            pending = await updateSyncButton();
        } catch {
            pending = 0;
        }

        if (!online || !pending || isSyncing) return;
        if (!force && Date.now() < nextAutoSyncAt) return;

        await syncAll({ manual: false });
    }

    function watchConnectivity() {
        const trigger = () => { checkAndAutoSync(true); };

        window.addEventListener('online', trigger);
        window.addEventListener('offline', () => {
            setOfflineState();
        });
        window.addEventListener('pageshow', trigger);
        window.addEventListener('focus', trigger);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                trigger();
            }
        });

        setInterval(() => { checkAndAutoSync(false); }, CONFIG.connectivityInterval);
    }

    // ── Data Collection ────────────────────────────────────────────────────

    function populateReturnCosts(form) {
        const returnCostsMap = window.spReturnCostsMap;
        if (!returnCostsMap || !returnCostsMap.size) return;

        const productIdInputs = [...form.querySelectorAll('input[name="product_ids[]"]')];
        const returnCostInputs = [...form.querySelectorAll('input[name="return_costs[]"]')];

        productIdInputs.forEach((input, index) => {
            const productId = parseInt(input.value, 10);
            const costInput = returnCostInputs[index];

            if (returnCostsMap.has(productId) && costInput) {
                costInput.value = returnCostsMap.get(productId);
            }
        });
    }

    function extractBillData(form) {
        populateReturnCosts(form);
        const formData = new FormData(form);
        const localId = ensureClientUuid(formData.get('client_uuid') || generateLocalId(), formData.get('client_uuid'));

        return {
            localId: localId,
            product_ids: formData.getAll('product_ids[]').filter(Boolean),
            quantities: formData.getAll('quantities[]'),
            discounts: formData.getAll('discounts[]'),
            cost_prices: formData.getAll('cost_prices[]'),
            selling_prices: formData.getAll('selling_prices[]'),
            discount_types: formData.getAll('discount_types[]'),
            product_tags: formData.getAll('product_tags[]'),
            return_costs: formData.getAll('return_costs[]'),
            customer_id: formData.get('customer_id') || null,
            note: formData.get('note') || '',
            bill_date: formData.get('bill_date') || new Date().toISOString().slice(0, 10),
            is_damaged: !!(form.querySelector('#is_damaged') || { checked: false }).checked,
            is_returned: !!(form.querySelector('#is_returned') || { checked: false }).checked,
            paid_amount: formData.get('paid_amount') || '0',
            payment_method: formData.get('payment_method') || 'cash',
            client_uuid: localId,
            operation_key: localId,
        };
    }

    function extractBillDataFromFormData(formData) {
        const localId = ensureClientUuid(formData.get('client_uuid') || generateLocalId(), formData.get('client_uuid'));
        return {
            localId: localId,
            product_ids: formData.getAll('product_ids[]').filter(Boolean),
            quantities: formData.getAll('quantities[]'),
            discounts: formData.getAll('discounts[]'),
            cost_prices: formData.getAll('cost_prices[]'),
            selling_prices: formData.getAll('selling_prices[]'),
            discount_types: formData.getAll('discount_types[]'),
            product_tags: formData.getAll('product_tags[]'),
            return_costs: formData.getAll('return_costs[]'),
            customer_id: formData.get('customer_id') || null,
            note: formData.get('note') || '',
            bill_date: formData.get('bill_date') || new Date().toISOString().slice(0, 10),
            is_damaged: formData.get('is_damaged') === 'on' || formData.get('is_damaged') === '1',
            is_returned: formData.get('is_returned') === 'on' || formData.get('is_returned') === '1',
            paid_amount: formData.get('paid_amount') || '0',
            payment_method: formData.get('payment_method') || 'cash',
            client_uuid: localId,
            operation_key: localId,
        };
    }

    // ── Sync ───────────────────────────────────────────────────────────────

    /**
     * Group every bill with the payments / installments that reference it, so
     * the server can map the local bill id to the real one in the same request.
     */
    function buildSyncGroups(bills, payments, installments) {
        const groups = [];
        const byBill = new Map();

        bills.forEach((bill) => {
            const group = { bills: [bill], payments: [], installments: [] };
            groups.push(group);
            byBill.set(String(bill.localId || bill.local_id), group);
        });

        payments.forEach((payment) => {
            const group = payment.bill_id != null ? byBill.get(String(payment.bill_id)) : null;
            if (group) {
                group.payments.push(payment);
            } else {
                groups.push({ bills: [], payments: [payment], installments: [] });
            }
        });

        installments.forEach((installment) => {
            const group = installment.bill_id != null ? byBill.get(String(installment.bill_id)) : null;
            if (group) {
                group.installments.push(installment);
            } else {
                groups.push({ bills: [], payments: [], installments: [installment] });
            }
        });

        return groups;
    }

    function chunkSyncGroups(groups) {
        const batches = [];
        let current = [];
        let counts = { bills: 0, payments: 0, installments: 0 };

        groups.forEach((group) => {
            const exceeds = current.length && (
                counts.bills + group.bills.length > CONFIG.syncBatch.bills ||
                counts.payments + group.payments.length > CONFIG.syncBatch.payments ||
                counts.installments + group.installments.length > CONFIG.syncBatch.installments
            );

            if (exceeds) {
                batches.push(current);
                current = [];
                counts = { bills: 0, payments: 0, installments: 0 };
            }

            current.push(group);
            counts.bills += group.bills.length;
            counts.payments += group.payments.length;
            counts.installments += group.installments.length;
        });

        if (current.length) {
            batches.push(current);
        }

        return batches;
    }

    function mergeGroups(groups) {
        return groups.reduce((payload, group) => {
            payload.bills.push(...group.bills);
            payload.payments.push(...group.payments);
            payload.installments.push(...group.installments);
            return payload;
        }, { bills: [], payments: [], installments: [] });
    }

    async function postSyncPayload(payload, context) {
        // No X-Idempotency-Key here on purpose: the server de-duplicates every
        // record by client_uuid, and a replayed cached error for a reused batch
        // key would block these records forever.
        return fetchWithTimeout(rawFetch, CONFIG.syncUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': context.token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                bills: payload.bills.map(normalizeRecord),
                payments: payload.payments.map(normalizeRecord),
                installments: payload.installments.map(normalizeRecord),
            }),
        }, CONFIG.syncRequestTimeout);
    }

    async function readJsonSafely(response) {
        try {
            return await response.json();
        } catch {
            return null;
        }
    }

    async function applySyncResults(payload, results, outcome) {
        const sections = [
            ['bills', CONFIG.stores.bills],
            ['payments', CONFIG.stores.payments],
            ['installments', CONFIG.stores.installments],
        ];

        for (const [key, storeName] of sections) {
            const sent = payload[key];
            if (!sent.length) continue;

            const resultById = new Map();
            ((results && results[key] && results[key].results) || []).forEach((result) => {
                const localId = result && (result.local_id || result.localId);
                if (localId) resultById.set(String(localId), result);
            });

            for (const record of sent) {
                const localId = record.localId || record.local_id;
                const result = resultById.get(String(localId));

                if (result && result.success) {
                    // Confirmed by the server - only now is it safe to drop it.
                    await deleteRecord(storeName, localId);
                    outcome.synced++;
                } else {
                    await recordSyncFailure(storeName, record, result && result.error);
                    outcome.failed++;
                }
            }
        }
    }

    async function markPayloadFailed(payload, message, outcome) {
        const sections = [
            ['bills', CONFIG.stores.bills],
            ['payments', CONFIG.stores.payments],
            ['installments', CONFIG.stores.installments],
        ];

        for (const [key, storeName] of sections) {
            for (const record of payload[key]) {
                await recordSyncFailure(storeName, record, message);
                outcome.failed++;
            }
        }
    }

    /**
     * Sync one batch of groups. Throws on network failure (caller stops and
     * keeps everything). Returns false when the session is not usable.
     */
    async function syncGroups(groups, context, outcome) {
        const payload = mergeGroups(groups);
        let response = await postSyncPayload(payload, context);

        if (response.status === 419 || isUnexpectedJsonResponse(response)) {
            const token = await fetchFreshCsrfToken();
            if (!token) {
                outcome.authProblem = true;
                return false;
            }
            context.token = token;
            response = await postSyncPayload(payload, context);
        }

        if (response.status === 401 || response.status === 419 || isUnexpectedJsonResponse(response)) {
            outcome.authProblem = true;
            return false;
        }

        if (response.status === 422) {
            if (groups.length > 1) {
                // One invalid record must not block the others: retry one by one.
                for (const group of groups) {
                    const ok = await syncGroups([group], context, outcome);
                    if (!ok) return false;
                }
                return true;
            }

            const data = await readJsonSafely(response);
            await markPayloadFailed(payload, (data && data.message) || 'Validation failed', outcome);
            return true;
        }

        if (!response.ok) {
            const error = new Error(`HTTP ${response.status}`);
            error.retryable = true;
            throw error;
        }

        const results = await readJsonSafely(response);
        if (!results) {
            const error = new Error('Invalid sync response');
            error.retryable = true;
            throw error;
        }

        await applySyncResults(payload, results, outcome);
        return true;
    }

    async function syncHeldBillRecord(record, context) {
        const send = () => fetchWithTimeout(rawFetch, CONFIG.heldSyncUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': context.token,
                'X-Requested-With': 'XMLHttpRequest',
                'X-Idempotency-Key': ensureOperationKey(record, 'held'),
            },
            body: JSON.stringify({
                label: record.label || '',
                customer_id: record.customer_id || null,
                customer_name: record.customer_name || '',
                payload: record.payload,
                client_uuid: ensureClientUuid(record.local_id || record.localId, record.client_uuid),
            }),
        }, CONFIG.syncRequestTimeout);

        let response = await send();
        if (response.status === 419 || isUnexpectedJsonResponse(response)) {
            const token = await fetchFreshCsrfToken();
            if (!token) return false;
            context.token = token;
            response = await send();
        }

        if (!response.ok || isUnexpectedJsonResponse(response)) {
            if (response.status === 409 || response.status === 422) {
                notify(translate('held_limit_reached', { count: LOCAL_HELD_BILL_LIMIT }), 'warning');
            }
            return false;
        }

        await deleteRecord(CONFIG.stores.heldBills, record.localId || record.local_id);
        return true;
    }

    function scheduleAutoSyncRetry(success) {
        if (success) {
            autoSyncFailures = 0;
            nextAutoSyncAt = 0;
            return;
        }

        const delays = CONFIG.autoSyncRetryDelays;
        const delay = delays[Math.min(autoSyncFailures, delays.length - 1)];
        autoSyncFailures++;
        nextAutoSyncAt = Date.now() + delay;
    }

    /**
     * Sync all pending records to the server.
     * Records are deleted locally only after the server confirmed them.
     */
    let currentSyncPromise = null;

    async function syncAll(options = {}) {
        if (currentSyncPromise) {
            // A sync is already running: let the caller wait for it.
            return currentSyncPromise;
        }
        currentSyncPromise = runSync(options);
        try {
            return await currentSyncPromise;
        } finally {
            currentSyncPromise = null;
        }
    }

    async function runSync(options = {}) {
        const manual = !!(options && options.manual);
        if (isSyncing || !userId || !rawFetch) return false;
        if (!manual && navigator.onLine === false) return false;

        let pending;
        try {
            pending = await loadPendingRecords();
        } catch {
            if (manual) notify(translate('sync_failed'), 'error');
            return false;
        }

        const { bills, payments, installments, heldBills } = pending;
        if (!bills.length && !payments.length && !installments.length && !heldBills.length) {
            await updateSyncButton();
            return true;
        }

        isSyncing = true;
        setSyncButtonLoading(true);
        if (manual) {
            notify(translate('syncing'), 'info');
        }

        const outcome = { synced: 0, failed: 0, authProblem: false };
        let completed = false;

        try {
            const context = { token: (await fetchFreshCsrfToken()) || getCsrfToken() };

            for (const batch of chunkSyncGroups(buildSyncGroups(bills, payments, installments))) {
                const ok = await syncGroups(batch, context, outcome);
                if (!ok) break;
            }

            if (!outcome.authProblem) {
                for (const heldBill of heldBills) {
                    if (await syncHeldBillRecord(heldBill, context)) {
                        outcome.synced++;
                    } else {
                        outcome.failed++;
                    }
                }
            }

            completed = !outcome.authProblem;
        } catch {
            // Network dropped mid-sync. Everything not confirmed stays queued.
            setOfflineState();
        } finally {
            isSyncing = false;
            setSyncButtonLoading(false);
        }

        await updateSyncButton();
        scheduleAutoSyncRetry(completed && outcome.failed === 0);

        if (outcome.synced) {
            notify(translate('synced_success', { count: outcome.synced }), 'success');
        }
        if (outcome.failed && (manual || outcome.synced)) {
            notify(translate('sync_partial_fail', { count: outcome.failed }), 'warning');
        }
        if (!completed && manual) {
            notify(translate('sync_failed'), 'error');
        }

        return completed;
    }

    // ── Interceptors ───────────────────────────────────────────────────────

    /**
     * Fallback for pages whose bill form has no AJAX handler. The POS
     * dashboard handles offline saving itself (with validation, cart
     * clearing and receipts) through the fetch interceptor below.
     */
    function interceptBillForm() {
        const form = document.getElementById('create-bill');
        if (!form) return;

        form.addEventListener('submit', async (event) => {
            if (typeof window.submitBillForm === 'function') return;
            if (navigator.onLine !== false) return;

            event.preventDefault();
            event.stopImmediatePropagation();

            const productRows = document.querySelectorAll('.product-row');
            if (!productRows.length) {
                notify(translate('no_products'), 'warning');
                return;
            }

            try {
                await saveRecord(CONFIG.stores.bills, extractBillData(form));

                if (typeof window.clearBillForm === 'function') {
                    window.clearBillForm();
                }

                await updateSyncButton();
                notify(translate('bill_saved_offline'), 'success');
            } catch (error) {
                notify(translate('save_failed'), 'error');
            }
        }, { capture: true });
    }

    async function sendBillRequest(originalFetch, input, init) {
        const send = () => fetchWithTimeout(
            (requestInput, requestInit) => originalFetch.call(window, requestInput, requestInit),
            input,
            init,
            CONFIG.billRequestTimeout
        );

        let response;
        try {
            response = await send();

            if (response.status === 419 || isUnexpectedJsonResponse(response)) {
                const token = await fetchFreshCsrfToken();
                if (token) {
                    applyCsrfTokenToRequest(init, token);
                    response = await send();
                }
            }
        } catch (error) {
            if (init.signal && init.signal.aborted) {
                throw error;
            }
            // Network error or timeout: keep the bill locally.
            setOfflineState();
            nextAutoSyncAt = 0;
            return handleBillOffline(init);
        }

        if (OFFLINE_STATUSES.has(response.status) || isUnexpectedJsonResponse(response)) {
            const offlineResponse = await handleBillOffline(init);
            if (response.status === 401) {
                handleUnauthorized();
            }
            return offlineResponse;
        }

        if (response.status === 403) {
            handleUnauthorized();
        }

        return response;
    }

    /**
     * Wrap window.fetch to intercept bill/payment/installment POSTs.
     * Installed synchronously while this script loads so that every later
     * fetch wrapper (sp-offline-core) calls through it.
     */
    function installFetchInterceptor() {
        if (!rawFetch) return;
        const originalFetch = window.fetch;

        window.fetch = async function (input, init) {
            const url = typeof input === 'string' ? input : (input?.url ?? String(input));
            const method = ((init?.method) || (typeof input !== 'string' ? input?.method : null) || 'GET').toUpperCase();

            if (method !== 'POST') {
                const response = await originalFetch.apply(this, arguments);
                if (response && (response.status === 401 || response.status === 403)) {
                    handleUnauthorized();
                }
                return response;
            }

            const path = requestPath(url);
            const isPayment = !!path && /^\/customers\/\d+\/payments\/?$/.test(path);
            const isInstallment = !!path && /^\/installments\/from-bill\/?$/.test(path);
            const isBill = !!path && /^\/bills\/?$/.test(path);

            if (!isPayment && !isInstallment && !isBill) {
                const response = await originalFetch.apply(this, arguments);
                if (response && (response.status === 401 || response.status === 403)) {
                    handleUnauthorized();
                }
                return response;
            }

            const nextInit = { ...(init || {}) };
            const headers = new Headers(nextInit.headers || (typeof input !== 'string' ? input?.headers : undefined) || {});

            if (isBill) {
                if (typeof FormData !== 'undefined' && nextInit.body instanceof FormData) {
                    const localId = ensureClientUuid(nextInit.body.get('client_uuid') || generateLocalId(), nextInit.body.get('client_uuid'));
                    nextInit.body.set('client_uuid', localId);
                }
                nextInit.headers = headers;
                return sendBillRequest(originalFetch, input, nextInit);
            }

            if (isPayment) {
                const paymentKey = headers.get('X-Idempotency-Key') || `pay_${generateLocalId()}`;
                headers.set('X-Idempotency-Key', paymentKey);
            }

            if (isInstallment) {
                const installmentKey = headers.get('X-Idempotency-Key') || `inst_${generateLocalId()}`;
                headers.set('X-Idempotency-Key', installmentKey);
            }

            nextInit.headers = headers;

            try {
                const response = await originalFetch.call(window, input, nextInit);
                if (response.status === 401 || response.status === 403) {
                    handleUnauthorized();
                }
                return response;
            } catch (error) {
                if (nextInit.signal && nextInit.signal.aborted) {
                    throw error;
                }
                // Network failed: save offline
                setOfflineState();
                nextAutoSyncAt = 0;

                if (isPayment) {
                    return handlePaymentOffline(url, nextInit);
                }
                return handleInstallmentOffline(nextInit);
            }
        };
    }

    function localSaveFailedResponse() {
        notify(translate('save_failed'), 'error');
        return new Response(
            JSON.stringify({ success: false, offline: true, message: translate('save_failed') }),
            { status: 503, headers: { 'Content-Type': 'application/json' } }
        );
    }

    async function handlePaymentOffline(url, init) {
        const customerId = parseInt(url.match(/\/customers\/(\d+)\/payments/)?.[1] || '0', 10);
        const paymentData = {};

        if (init.body instanceof FormData) {
            for (const [key, value] of init.body.entries()) {
                paymentData[key] = value;
            }
        }

        const localId = `pay_${Date.now()}_${generateRandomSuffix()}`;
        const operationKey = init?.headers instanceof Headers
            ? (init.headers.get('X-Idempotency-Key') || `pay_${localId}`)
            : ((init?.headers && (init.headers['X-Idempotency-Key'] || init.headers['x-idempotency-key'])) || `pay_${localId}`);

        try {
            await saveRecord(CONFIG.stores.payments, {
                localId,
                client_uuid: localId,
                operation_key: operationKey,
                customer_id: customerId,
                amount: paymentData.amount,
                type: paymentData.type || 'cash',
                note: paymentData.note || '',
                payment_date: paymentData.payment_date || new Date().toISOString().slice(0, 10),
            });

            await updateSyncButton();
            notify(translate('payment_saved_offline'), 'success');

            return new Response(
                JSON.stringify({ success: true, new_balance: null, offline: true }),
                { status: 200, headers: { 'Content-Type': 'application/json' } }
            );
        } catch {
            return localSaveFailedResponse();
        }
    }

    async function handleInstallmentOffline(init) {
        const localId = `inst_${Date.now()}_${generateRandomSuffix()}`;
        const operationKey = init?.headers instanceof Headers
            ? (init.headers.get('X-Idempotency-Key') || `inst_${localId}`)
            : ((init?.headers && (init.headers['X-Idempotency-Key'] || init.headers['x-idempotency-key'])) || `inst_${localId}`);

        try {
            const rawBody = init?.body;
            const body = typeof rawBody === 'string' ? JSON.parse(rawBody) : {};
            await saveRecord(CONFIG.stores.installments, { ...body, localId, client_uuid: localId, operation_key: operationKey });
            await updateSyncButton();
            notify(translate('installment_saved_offline'), 'success');

            return new Response(
                JSON.stringify({ success: true, offline: true }),
                { status: 200, headers: { 'Content-Type': 'application/json' } }
            );
        } catch {
            return localSaveFailedResponse();
        }
    }

    async function handleBillOffline(init) {
        const formData = init.body instanceof FormData ? init.body : new FormData();
        const localId = ensureClientUuid(formData.get('client_uuid') || generateLocalId(), formData.get('client_uuid'));
        formData.set('client_uuid', localId);
        const billData = extractBillDataFromFormData(formData);

        if (!billData.product_ids.length) {
            return new Response(
                JSON.stringify({ success: false, message: translate('no_products') }),
                { status: 422, headers: { 'Content-Type': 'application/json' } }
            );
        }

        try {
            await saveRecord(CONFIG.stores.bills, billData);
        } catch {
            return localSaveFailedResponse();
        }

        await updateSyncButton();
        notify(translate('bill_saved_offline'), 'success');

        return new Response(
            JSON.stringify({ success: true, offline: true, local_id: billData.localId }),
            { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
    }

    function generateRandomSuffix() {
        return Math.random().toString(36).slice(2, 9);
    }

    // ── Auth Handling ──────────────────────────────────────────────────────

    function handleUnauthorized() {
        // Tell the service worker the user is no longer authenticated
        if (navigator.serviceWorker?.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'SP_SET_AUTH',
                authenticated: false,
            });
        }

        const path = window.location.pathname;
        const protectedPaths = [
            '/dashboard', '/bills/create',
            '/bills/', '/products/', '/customers/', '/settings',
            '/installments', '/purchase-bills',
        ];

        const isProtected = protectedPaths.some((prefix) => {
            if (prefix.endsWith('/')) {
                return path.startsWith(prefix);
            }
            return path === prefix;
        });

        if (isProtected && !path.includes('/login')) {
            notify('You have been logged out', 'warning');
            setTimeout(() => {
                window.location.href = '/login';
            }, 1500);
        }
    }

    // ── Public API ─────────────────────────────────────────────────────────

    /**
     * Manually trigger a sync of all pending records.
     */
    window.spSyncNow = function () {
        return syncAll({ manual: true });
    };

    window.spRefreshSyncButton = updateSyncButton;

    /**
     * Save a bill offline without going through the fetch interceptor.
     */
    window.spSaveBillOffline = async function (form) {
        const data = extractBillData(form);
        await saveRecord(CONFIG.stores.bills, data);
        await updateSyncButton();
        notify(translate('bill_saved_offline'), 'success');
        return data.localId;
    };

    window.spSaveLocalHeldBill = async function (payload) {
        if (!await enforceHeldBillLimit()) {
            return null;
        }

        const localId = payload.client_uuid || payload.local_id || generateLocalId();
        await saveRecord(CONFIG.stores.heldBills, {
            ...payload,
            localId,
            client_uuid: payload.client_uuid || localId,
            operation_key: payload.operation_key || payload.client_uuid || localId,
            local_only: true,
        });
        await updateSyncButton();
        return localId;
    };

    window.spListLocalHeldBills = async function () {
        const records = await getPendingRecords(CONFIG.stores.heldBills);
        return records.map((record) => ({
            local_id: record.local_id || record.localId,
            client_uuid: record.client_uuid || record.local_id || record.localId,
            label: record.label || '',
            customer_name: record.customer_name || record.payload?.customer?.name || '',
            items_count: record.payload?.rows?.length || 0,
            total: record.payload?.rows?.reduce((sum, row) => sum + ((parseFloat(row.selling_price || 0) * parseFloat(row.quantity || 0)) || 0), 0) || 0,
            created_at_human: translate('held_saved_offline') || 'Offline',
            is_stale: false,
            local_only: true,
        }));
    };

    window.spTakeLocalHeldBill = async function (localId) {
        const records = await getUserRecords(CONFIG.stores.heldBills);
        const record = records.find((entry) => (entry.localId || entry.local_id) === localId || entry.client_uuid === localId);
        if (!record) return null;
        await deleteRecord(CONFIG.stores.heldBills, record.localId || record.local_id);
        await updateSyncButton();
        return record.payload || null;
    };

    window.spRenameLocalHeldBill = async function (localId, label) {
        const records = await getUserRecords(CONFIG.stores.heldBills);
        const record = records.find((entry) => (entry.localId || entry.local_id) === localId || entry.client_uuid === localId);
        if (!record) return;
        record.label = label;
        await saveRecord(CONFIG.stores.heldBills, record);
        await updateSyncButton();
    };

    window.spDeleteLocalHeldBill = async function (localId) {
        const records = await getUserRecords(CONFIG.stores.heldBills);
        const record = records.find((entry) => (entry.localId || entry.local_id) === localId || entry.client_uuid === localId);
        if (!record) return;
        await deleteRecord(CONFIG.stores.heldBills, record.localId || record.local_id);
        await updateSyncButton();
    };

    /**
     * Kept for backwards compatibility. Only removes records the server has
     * already confirmed; unsynced bills/payments are NEVER deleted here.
     */
    window.spClearOfflineData = async function (targetUserId) {
        try {
            await purgeSyncedRecords(targetUserId || userId);
            await updateSyncButton();
        } catch {
            // Ignore cleanup failures.
        }
    };

    // ── Initialization ─────────────────────────────────────────────────────

    async function requestPersistentStorage() {
        try {
            if (navigator.storage && typeof navigator.storage.persist === 'function') {
                const persisted = typeof navigator.storage.persisted === 'function'
                    ? await navigator.storage.persisted()
                    : false;
                if (!persisted) {
                    await navigator.storage.persist();
                }
            }
        } catch {
            // Best effort only.
        }
    }

    async function initialize() {
        userId = window.spCurrentUserId || userId;
        if (!userId) return;

        try {
            await getDatabase();
        } catch (error) {
            return;
        }

        interceptBillForm();
        requestPersistentStorage();

        try {
            await purgeSyncedRecords();
        } catch {
            // Non-critical.
        }

        await updateSyncButton();

        // Records left from an earlier session (offline logout, reload, crash)
        // are synced as soon as the page is usable again.
        await checkAndAutoSync(true);
    }

    // Install the interceptor right away (synchronously) so sp-offline-core,
    // which loads after this file, wraps it instead of bypassing it.
    if (userId) {
        installFetchInterceptor();
    }

    // Start connectivity monitoring immediately
    watchConnectivity();

    // Register background sync when going offline
    window.addEventListener('offline', async () => {
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            try {
                const registration = await navigator.serviceWorker.ready;
                await registration.sync.register(CONFIG.syncTag);
            } catch {
                // Fallback: online event will trigger sync
            }
        }
    });

    // Listen for sync triggers from the service worker
    if ('serviceWorker' in navigator && navigator.serviceWorker && typeof navigator.serviceWorker.addEventListener === 'function') {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data?.type === 'SP_TRIGGER_SYNC') {
                syncAll({ manual: false });
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
