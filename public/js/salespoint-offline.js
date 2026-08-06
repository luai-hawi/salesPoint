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
        dbVersion: 2,
        stores: {
            bills: 'pending_bills',
            payments: 'pending_payments',
            installments: 'pending_installments',
        },
        syncUrl: '/offline/sync',
        syncTag: 'sp-sync-bills',
        probePath: '/?_sp_probe=',
        connectivityInterval: 5000,
        probeTimeout: 5000,
    };

    const STORE_NAMES = Object.values(CONFIG.stores);

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
                userId,
                status: 'pending',
                savedAt: new Date().toISOString(),
            });

            transaction.oncomplete = () => resolve();
            transaction.onerror = (event) => reject(event.target.error);
        });
    }

    /**
     * Get all pending records for the current user from a store.
     */
    async function getPendingRecords(storeName) {
        const database = await getDatabase();

        return new Promise((resolve, reject) => {
            const transaction = database.transaction(storeName, 'readonly');
            const store = transaction.objectStore(storeName);
            const index = store.index('byUser');
            const request = index.getAll(IDBKeyRange.only(userId));

            request.onsuccess = (event) => {
                const results = event.target.result || [];
                resolve(results.filter((record) => record.status === 'pending'));
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
        const [bills, payments, installments] = await Promise.all([
            getPendingRecords(CONFIG.stores.bills),
            getPendingRecords(CONFIG.stores.payments),
            getPendingRecords(CONFIG.stores.installments),
        ]);

        return bills.length + payments.length + installments.length;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    function generateLocalId() {
        const timestamp = Date.now();
        const random = Math.random().toString(36).slice(2, 9);
        return `rec_${timestamp}_${random}`;
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
     * Uses a HEAD request to bypass HTTP cache and service worker cache.
     */
    async function probeConnectivity() {
        let offline = !navigator.onLine;

        if (!offline) {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), CONFIG.probeTimeout);

                await fetch(`${CONFIG.probePath}${Date.now()}`, {
                    method: 'HEAD',
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

        return {
            localId: generateLocalId(),
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
        };
    }

    function extractBillDataFromFormData(formData) {
        return {
            localId: generateLocalId(),
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
        };
    }

    // ── Sync ───────────────────────────────────────────────────────────────

    /**
     * Sync all pending records to the server.
     */
    async function syncAll() {
        if (isSyncing || !navigator.onLine || !userId) return;

        const [bills, payments, installments] = await Promise.all([
            getPendingRecords(CONFIG.stores.bills),
            getPendingRecords(CONFIG.stores.payments),
            getPendingRecords(CONFIG.stores.installments),
        ]);

        if (!bills.length && !payments.length && !installments.length) return;

        isSyncing = true;
        setSyncButtonLoading(true);
        notify(translate('syncing'), 'info');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        try {
            const response = await fetch(CONFIG.syncUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
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

            await updateSyncButton();

            if (syncedCount) {
                notify(translate('synced_success', { count: syncedCount }), 'success');
            }
            if (failedCount) {
                notify(translate('sync_partial_fail', { count: failedCount }), 'warning');
            }

        } catch (error) {
            console.error('[SP Offline] sync error:', error);
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
                console.error('[SP Offline] bill save error:', error);
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

            // Try the real request first
            try {
                const response = await originalFetch.apply(this, arguments);
                if (response.status === 401 || response.status === 403) {
                    handleUnauthorized();
                }
                return response;
            } catch {
                // Network failed: save offline
                setOfflineState();

                if (isPayment) {
                    return handlePaymentOffline(url, init);
                }
                if (isInstallment) {
                    return handleInstallmentOffline(init);
                }
                if (isBill) {
                    return handleBillOffline(init);
                }

                return originalFetch.apply(this, arguments);
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

        try {
            await saveRecord(CONFIG.stores.payments, {
                localId,
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

        try {
            await saveRecord(CONFIG.stores.installments, { ...body, localId });
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

    // ── Initialization ─────────────────────────────────────────────────────

    async function initialize() {
        userId = window.spCurrentUserId;
        if (!userId) return;

        try {
            await getDatabase();
        } catch (error) {
            console.warn('[SP Offline] IndexedDB unavailable:', error);
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
