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
        heldSyncUrl: '/pos/held',
        syncTag: 'sp-sync-bills',
        probePath: '/up?_sp_probe=',
        connectivityInterval: 5000,
        probeTimeout: 5000,
    };

    const STORE_NAMES = Object.values(CONFIG.stores);
    const LOCAL_HELD_BILL_LIMIT = 50;

    // ── State ──────────────────────────────────────────────────────────────
    let db = null;
    let isSyncing = false;
    let userId = null;
    let isOffline = false;

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

            request.onsuccess = (event) => resolve(event.target.result);
            request.onerror = (event) => reject(event.target.error);
        });
    }

    /**
     * Get the database instance, opening it if necessary.
     */
    async function getDatabase() {
        if (!db) {
            db = await openDatabase();
        }
        return db;
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
                userId,
                status: data.status || 'pending',
                savedAt: new Date().toISOString(),
            });

            transaction.oncomplete = () => resolve();
            transaction.onerror = (event) => reject(event.target.error);
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

    async function clearUserRecords(targetUserId = userId) {
        if (!targetUserId) return;

        const database = await getDatabase();
        await Promise.all(STORE_NAMES.map((storeName) => new Promise((resolve, reject) => {
            const transaction = database.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const index = store.index('byUser');
            const request = index.getAllKeys(IDBKeyRange.only(targetUserId));

            request.onsuccess = (event) => {
                const keys = event.target.result || [];
                keys.forEach((key) => store.delete(key));
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
        return records.filter((record) => record.status === 'pending');
    }

    async function getUserRecords(storeName) {
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

    /**
     * Mark a record as synced.
     */
    async function markRecordSynced(storeName, localId) {
        const database = await getDatabase();

        return new Promise((resolve, reject) => {
            const transaction = database.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const getRequest = store.get(localId);

            getRequest.onsuccess = (event) => {
                const record = event.target.result;
                if (record) {
                    record.status = 'synced';
                    store.put(record);
                }
            };

            transaction.oncomplete = () => resolve();
            transaction.onerror = (event) => reject(event.target.error);
        });
    }

    /**
     * Get total count of pending records across all stores.
     */
    async function getTotalPendingCount() {
        const [bills, payments, installments, heldBills] = await Promise.all([
            getPendingRecords(CONFIG.stores.bills),
            getPendingRecords(CONFIG.stores.payments),
            getPendingRecords(CONFIG.stores.installments),
            getPendingRecords(CONFIG.stores.heldBills),
        ]);

        return bills.length + payments.length + installments.length + heldBills.length;
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

    async function assignBatchKey(recordsByStore) {
        const existing = [];
        Object.values(recordsByStore).forEach((records) => {
            (records || []).forEach((record) => {
                if (record.sync_batch_key) {
                    existing.push(record.sync_batch_key);
                }
            });
        });

        const batchKey = existing[0] || `batch_${generateLocalId()}`;

        await Promise.all(Object.entries(recordsByStore).flatMap(([storeName, records]) => (records || [])
            .filter((record) => !record.sync_batch_key)
            .map((record) => saveRecord(storeName, { ...record, sync_batch_key: batchKey }))));

        return batchKey;
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
        return localId ? { ...record, local_id: localId } : { ...record };
    }

    // ── UI Helpers ─────────────────────────────────────────────────────────

    function setOfflineBannerVisibility(visible) {
        const banner = document.getElementById('sp-offline-banner');
        if (banner) {
            banner.style.display = visible ? 'flex' : 'none';
        }
    }

    async function updateSyncButton() {
        const pendingCount = await getTotalPendingCount();
        const button = document.getElementById('sp-sync-btn');
        const badge = document.getElementById('sp-sync-badge');
        const label = document.getElementById('sp-sync-label');

        if (!button) return;

        if (pendingCount > 0) {
            button.style.display = 'flex';
            if (badge) badge.textContent = pendingCount;
            if (label) label.textContent = translate('pending_count', { count: pendingCount });
        } else {
            button.style.display = 'none';
        }
    }

    // Alias for backward compatibility
    const refreshSyncUI = updateSyncButton;

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
            syncAll();
        }
    }

    /**
     * Probe the network to detect connectivity changes.
     * Uses the health endpoint to bypass app page rendering and HTTP cache.
     */
    async function probeConnectivity() {
        let offline = !navigator.onLine;

        if (!offline) {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), CONFIG.probeTimeout);

                await fetch(`${CONFIG.probePath}${Date.now()}`, {
                    method: 'GET',
                    cache: 'no-store',
                    signal: controller.signal,
                });

                clearTimeout(timeoutId);
            } catch {
                offline = true;
            }
        }

        if (offline) {
            setOfflineState();
        } else {
            setOnlineState();
        }
    }

    function watchConnectivity() {
        window.addEventListener('online', probeConnectivity);
        window.addEventListener('offline', probeConnectivity);
        probeConnectivity();
        setInterval(probeConnectivity, CONFIG.connectivityInterval);
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
     * Sync all pending records to the server.
     */
    async function syncAll() {
        if (isSyncing || !navigator.onLine || !userId) return;

        const [bills, payments, installments, heldBills] = await Promise.all([
            getPendingRecords(CONFIG.stores.bills),
            getPendingRecords(CONFIG.stores.payments),
            getPendingRecords(CONFIG.stores.installments),
            getPendingRecords(CONFIG.stores.heldBills),
        ]);

        if (!bills.length && !payments.length && !installments.length && !heldBills.length) return;

        isSyncing = true;
        setSyncButtonLoading(true);
        notify(translate('syncing'), 'info');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        try {
            const batchKey = await assignBatchKey({
                [CONFIG.stores.bills]: bills,
                [CONFIG.stores.payments]: payments,
                [CONFIG.stores.installments]: installments,
            });

            const response = await fetch(CONFIG.syncUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Idempotency-Key': batchKey,
                },
                body: JSON.stringify({
                    bills: bills.map(normalizeRecord),
                    payments: payments.map(normalizeRecord),
                    installments: installments.map(normalizeRecord),
                }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const results = await response.json();
            let syncedCount = 0;
            let failedCount = 0;

            // Process synced bills
            for (const result of results.bills?.results || []) {
                const localId = result.local_id || result.localId;
                if (result.success && localId) {
                    await markRecordSynced(CONFIG.stores.bills, localId);
                    syncedCount++;
                } else {
                    failedCount++;
                }
            }

            // Process synced payments
            for (const result of results.payments?.results || []) {
                const localId = result.local_id || result.localId;
                if (result.success && localId) {
                    await markRecordSynced(CONFIG.stores.payments, localId);
                    syncedCount++;
                } else {
                    failedCount++;
                }
            }

            // Process synced installments
            for (const result of results.installments?.results || []) {
                const localId = result.local_id || result.localId;
                if (result.success && localId) {
                    await markRecordSynced(CONFIG.stores.installments, localId);
                    syncedCount++;
                } else {
                    failedCount++;
                }
            }

            for (const heldBill of heldBills) {
                const synced = await syncHeldBillRecord(heldBill, csrfToken);
                if (synced) {
                    syncedCount++;
                } else {
                    failedCount++;
                }
            }

            await updateSyncButton();

            if (syncedCount) {
                notify(translate('synced_success', { count: syncedCount }), 'success');
            }

            async function syncHeldBillRecord(record, csrfToken) {
                try {
                    const response = await fetch(CONFIG.heldSyncUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
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
                    });

                    if (!response.ok) {
                        if (response.status === 409 || response.status === 422) {
                            notify(translate('held_limit_reached', { count: LOCAL_HELD_BILL_LIMIT }), 'warning');
                        }
                        return false;
                    }

                    await deleteRecord(CONFIG.stores.heldBills, record.localId || record.local_id);
                    return true;
                } catch {
                    return false;
                }
            }
            if (failedCount) {
                notify(translate('sync_partial_fail', { count: failedCount }), 'warning');
            }

        } catch (error) {
            notify(translate('sync_failed'), 'error');
        } finally {
            isSyncing = false;
            setSyncButtonLoading(false);
        }
    }

    // ── Interceptors ───────────────────────────────────────────────────────

    /**
     * Intercept bill form submission when offline.
     */
    function interceptBillForm() {
        const form = document.getElementById('create-bill');
        if (!form) return;

        form.addEventListener('submit', async (event) => {
            if (navigator.onLine) return;

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

    /**
     * Wrap window.fetch to intercept POST requests when offline.
     */
    function installFetchInterceptor() {
        const originalFetch = window.fetch;

        window.fetch = async function (input, init) {
            const url = typeof input === 'string' ? input : (input?.url ?? String(input));
            const method = ((init?.method) || (typeof input !== 'string' ? input?.method : null) || 'GET').toUpperCase();
            const nextInit = { ...(init || {}) };
            const headers = new Headers(nextInit.headers || (typeof input !== 'string' ? input?.headers : undefined) || {});

            // For non-POST requests, just pass through and check for auth errors
            if (method !== 'POST') {
                try {
                    const response = await originalFetch.apply(this, arguments);
                    if (response.status === 401 || response.status === 403) {
                        handleUnauthorized();
                    }
                    return response;
                } catch {
                    return originalFetch.apply(this, arguments);
                }
            }

            // Check if this is a bill/payment/installment request
            const isPayment = /\/customers\/(\d+)\/payments(?:\?.*)?$/.test(url);
            const isInstallment = /\/installments\/from-bill(?:\?.*)?$/.test(url);
            const isBill = /\/bills(?:\/.*)?$/.test(url);

            if (!isPayment && !isInstallment && !isBill) {
                try {
                    const response = await originalFetch.apply(this, arguments);
                    if (response.status === 401 || response.status === 403) {
                        handleUnauthorized();
                    }
                    return response;
                } catch {
                    return originalFetch.apply(this, arguments);
                }
            }

            if (isBill && nextInit.body instanceof FormData) {
                const localId = ensureClientUuid(nextInit.body.get('client_uuid') || generateLocalId(), nextInit.body.get('client_uuid'));
                nextInit.body.set('client_uuid', localId);
                headers.set('X-Idempotency-Key', headers.get('X-Idempotency-Key') || localId);
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

            // Try the real request first
            try {
                const response = await originalFetch.call(this, input, nextInit);
                if (response.status === 401 || response.status === 403) {
                    handleUnauthorized();
                }
                return response;
            } catch {
                // Network failed: save offline
                setOfflineState();

                if (isPayment) {
                    return handlePaymentOffline(url, nextInit);
                }
                if (isInstallment) {
                    return handleInstallmentOffline(nextInit);
                }
                if (isBill) {
                    return handleBillOffline(nextInit);
                }

                return originalFetch.call(this, input, nextInit);
            }
        };
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
            return window.fetch.apply(this, arguments);
        }
    }

    async function handleInstallmentOffline(init) {
        const rawBody = init?.body;
        const body = typeof rawBody === 'string' ? JSON.parse(rawBody) : {};
        const localId = `inst_${Date.now()}_${generateRandomSuffix()}`;
        const operationKey = init?.headers instanceof Headers
            ? (init.headers.get('X-Idempotency-Key') || `inst_${localId}`)
            : ((init?.headers && (init.headers['X-Idempotency-Key'] || init.headers['x-idempotency-key'])) || `inst_${localId}`);

        try {
            await saveRecord(CONFIG.stores.installments, { ...body, localId, client_uuid: localId, operation_key: operationKey });
            await updateSyncButton();
            notify(translate('installment_saved_offline'), 'success');

            return new Response(
                JSON.stringify({ success: true, offline: true }),
                { status: 200, headers: { 'Content-Type': 'application/json' } }
            );
        } catch {
            return window.fetch.apply(this, arguments);
        }
    }

    async function handleBillOffline(init) {
        const formData = init.body instanceof FormData ? init.body : new FormData();
        const localId = ensureClientUuid(formData.get('client_uuid') || generateLocalId(), formData.get('client_uuid'));
        formData.set('client_uuid', localId);
        const billData = extractBillDataFromFormData(formData);

        try {
            await saveRecord(CONFIG.stores.bills, billData);
            await updateSyncButton();
            notify(translate('bill_saved_offline'), 'success');

            return new Response(
                JSON.stringify({ success: true, offline: true, local_id: billData.localId }),
                { status: 200, headers: { 'Content-Type': 'application/json' } }
            );
        } catch {
            return window.fetch.apply(this, arguments);
        }
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
    window.spSyncNow = syncAll;

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

    window.spClearOfflineData = async function (targetUserId) {
        try {
            await clearUserRecords(targetUserId);
            await updateSyncButton();
        } catch {
            // Ignore cleanup failures during logout/user switching.
        }
    };

    // ── Initialization ─────────────────────────────────────────────────────

    async function initialize() {
        userId = window.spCurrentUserId;
        if (!userId) return;

        try {
            await getDatabase();
        } catch (error) {
            return;
        }

        // Install interceptors early so they wrap fetch before other scripts use it
        installFetchInterceptor();
        interceptBillForm();
        await updateSyncButton();
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
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data?.type === 'SP_TRIGGER_SYNC') {
                syncAll();
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
