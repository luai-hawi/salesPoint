/**
 * SalesPoint Offline Module v2
 * Covers: bills, customer payments, installment plans
 *
 * Security: every record is tagged with the server-rendered userId.
 * Only the current user's records are synced.
 * The server resolves ownership entirely from the auth session.
 */
(function () {
    'use strict';

    // ── Config ─────────────────────────────────────────────────────────────
    const DB_NAME            = 'sp_offline';
    const DB_VERSION         = 2;           // bump when adding stores
    const STORE_BILLS        = 'pending_bills';
    const STORE_PAYMENTS     = 'pending_payments';
    const STORE_INSTALLMENTS = 'pending_installments';
    const SYNC_URL           = '/offline/sync';
    const SYNC_TAG           = 'sp-sync-bills';

    // ── State ───────────────────────────────────────────────────────────────
    let db        = null;
    let isSyncing = false;
    let userId    = null;

    function getLocalId(record) {
        return record?.localId || record?.local_id || null;
    }

    function normalizeForSync(record) {
        const localId = getLocalId(record);
        return localId ? { ...record, local_id: localId } : { ...record };
    }

    // ── IndexedDB ───────────────────────────────────────────────────────────
    function openDB() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);

            req.onupgradeneeded = (e) => {
                const d = e.target.result;
                const oldV = e.oldVersion;

                // v1: bills store
                if (oldV < 1 && !d.objectStoreNames.contains(STORE_BILLS)) {
                    const s = d.createObjectStore(STORE_BILLS, { keyPath: 'localId' });
                    s.createIndex('byUser',   'userId', { unique: false });
                    s.createIndex('byStatus', 'status', { unique: false });
                }

                // v2: payments + installments stores
                if (oldV < 2) {
                    if (!d.objectStoreNames.contains(STORE_PAYMENTS)) {
                        const s = d.createObjectStore(STORE_PAYMENTS, { keyPath: 'localId' });
                        s.createIndex('byUser',   'userId', { unique: false });
                        s.createIndex('byStatus', 'status', { unique: false });
                    }
                    if (!d.objectStoreNames.contains(STORE_INSTALLMENTS)) {
                        const s = d.createObjectStore(STORE_INSTALLMENTS, { keyPath: 'localId' });
                        s.createIndex('byUser',   'userId', { unique: false });
                        s.createIndex('byStatus', 'status', { unique: false });
                    }
                }
            };

            req.onsuccess = (e) => resolve(e.target.result);
            req.onerror   = (e) => reject(e.target.error);
        });
    }

    async function getDB() {
        if (!db) db = await openDB();
        return db;
    }

    // Generic: save a record to any pending store
    async function saveRecord(storeName, data) {
        const d = await getDB();
        return new Promise((resolve, reject) => {
            const tx = d.transaction(storeName, 'readwrite');
            const localId = getLocalId(data) || ('rec_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9));
            tx.objectStore(storeName).put({
                ...data,
                localId,
                local_id: data?.local_id || localId,
                userId,
                status: 'pending',
                savedAt: new Date().toISOString(),
            });
            tx.oncomplete = () => resolve();
            tx.onerror    = (e) => reject(e.target.error);
        });
    }

    // Generic: get all pending records for current user from a store
    async function getPending(storeName) {
        const d = await getDB();
        return new Promise((resolve, reject) => {
            const tx  = d.transaction(storeName, 'readonly');
            const req = tx.objectStore(storeName).index('byUser').getAll(IDBKeyRange.only(userId));
            req.onsuccess = (e) => resolve((e.target.result || []).filter((r) => r.status === 'pending'));
            req.onerror   = (e) => reject(e.target.error);
        });
    }

    // Generic: mark a record as synced
    async function markSynced(storeName, localId) {
        const d = await getDB();
        return new Promise((resolve, reject) => {
            const tx  = d.transaction(storeName, 'readwrite');
            const st  = tx.objectStore(storeName);
            const req = st.get(localId);
            req.onsuccess = (e) => {
                const rec = e.target.result;
                if (rec) { rec.status = 'synced'; st.put(rec); }
            };
            tx.oncomplete = () => resolve();
            tx.onerror    = (e) => reject(e.target.error);
        });
    }

    async function getTotalPending() {
        const [b, p, i] = await Promise.all([
            getPending(STORE_BILLS),
            getPending(STORE_PAYMENTS),
            getPending(STORE_INSTALLMENTS),
        ]);
        return b.length + p.length + i.length;
    }

    // ── Translation + Notification helpers ─────────────────────────────────
    function t(key, replace) {
        const map = window.offlineTranslations || {};
        let str   = map[key] || key;
        if (replace) Object.keys(replace).forEach((k) => { str = str.replace(':' + k, replace[k]); });
        return str;
    }

    function notify(message, type) {
        if (typeof window.showNotification === 'function') window.showNotification(message, type || 'info');
    }

    // ── Offline Banner ──────────────────────────────────────────────────────
    function setBannerVisible(visible) {
        const el = document.getElementById('sp-offline-banner');
        if (el) el.style.display = visible ? 'flex' : 'none';
    }

    // ── Sync Button ─────────────────────────────────────────────────────────
    async function refreshSyncUI() {
        const count  = await getTotalPending();
        const btn    = document.getElementById('sp-sync-btn');
        const badge  = document.getElementById('sp-sync-badge');
        const label  = document.getElementById('sp-sync-label');
        if (!btn) return;
        if (count > 0) {
            btn.style.display = 'flex';
            if (badge) badge.textContent = count;
            if (label) label.textContent = t('pending_count', { count });
        } else {
            btn.style.display = 'none';
        }
    }

    function setSyncButtonState(syncing) {
        const btn     = document.getElementById('sp-sync-btn');
        const spinner = document.getElementById('sp-sync-spinner');
        const icon    = document.getElementById('sp-sync-icon');
        if (!btn) return;
        btn.disabled = syncing;
        if (spinner) spinner.style.display = syncing ? 'block' : 'none';
        if (icon)    icon.style.display    = syncing ? 'none'  : 'block';
    }

    // ── Collect bill form data ──────────────────────────────────────────────
    function populateReturnCosts(form) {
        const map = window.spReturnCostsMap;
        if (!map || !map.size) return;
        const pids  = [...form.querySelectorAll('input[name="product_ids[]"]')];
        const costs = [...form.querySelectorAll('input[name="return_costs[]"]')];
        pids.forEach((p, i) => {
            const pid = parseInt(p.value, 10);
            if (map.has(pid) && costs[i] !== undefined) costs[i].value = map.get(pid);
        });
    }

    function collectBillData(form) {
        populateReturnCosts(form);
        const fd = new FormData(form);
        return {
            localId        : 'bill_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9),
            product_ids    : fd.getAll('product_ids[]').filter(Boolean),
            quantities     : fd.getAll('quantities[]'),
            discounts      : fd.getAll('discounts[]'),
            cost_prices    : fd.getAll('cost_prices[]'),
            selling_prices : fd.getAll('selling_prices[]'),
            discount_types : fd.getAll('discount_types[]'),
            product_tags   : fd.getAll('product_tags[]'),
            return_costs   : fd.getAll('return_costs[]'),
            customer_id    : fd.get('customer_id') || null,
            note           : fd.get('note') || '',
            bill_date      : fd.get('bill_date') || new Date().toISOString().slice(0, 10),
            is_damaged     : !!(form.querySelector('#is_damaged') || { checked: false }).checked,
            is_returned    : !!(form.querySelector('#is_returned') || { checked: false }).checked,
        };
    }

    function collectBillDataFromFormData(fd) {
        return {
            localId        : 'bill_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9),
            product_ids    : fd.getAll('product_ids[]').filter(Boolean),
            quantities     : fd.getAll('quantities[]'),
            discounts      : fd.getAll('discounts[]'),
            cost_prices    : fd.getAll('cost_prices[]'),
            selling_prices : fd.getAll('selling_prices[]'),
            discount_types : fd.getAll('discount_types[]'),
            product_tags   : fd.getAll('product_tags[]'),
            return_costs   : fd.getAll('return_costs[]'),
            customer_id    : fd.get('customer_id') || null,
            note           : fd.get('note') || '',
            bill_date      : fd.get('bill_date') || new Date().toISOString().slice(0, 10),
            is_damaged     : fd.get('is_damaged') === 'on' || fd.get('is_damaged') === '1',
            is_returned    : fd.get('is_returned') === 'on' || fd.get('is_returned') === '1',
        };
    }

    // ── Sync all pending items ──────────────────────────────────────────────
    async function syncAll() {
        if (isSyncing || !navigator.onLine || !userId) return;

        const [bills, payments, installments] = await Promise.all([
            getPending(STORE_BILLS),
            getPending(STORE_PAYMENTS),
            getPending(STORE_INSTALLMENTS),
        ]);

        if (!bills.length && !payments.length && !installments.length) return;

        isSyncing = true;
        setSyncButtonState(true);
        notify(t('syncing'), 'info');

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        try {
            const billsPayload = bills.map(normalizeForSync);
            const paymentsPayload = payments.map(normalizeForSync);
            const installmentsPayload = installments.map(normalizeForSync);

            const res = await fetch(SYNC_URL, {
                method : 'POST',
                headers: {
                    'Content-Type'    : 'application/json',
                    'Accept'          : 'application/json',
                    'X-CSRF-TOKEN'    : csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    bills: billsPayload,
                    payments: paymentsPayload,
                    installments: installmentsPayload,
                }),
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            let synced = 0, failed = 0;

            // Process bills
            for (const r of (data.bills?.results || [])) {
                const localId = r.local_id || r.localId;
                if (r.success && localId) { await markSynced(STORE_BILLS, localId); synced++; }
                else failed++;
            }
            // Process payments
            for (const r of (data.payments?.results || [])) {
                const localId = r.local_id || r.localId;
                if (r.success && localId) { await markSynced(STORE_PAYMENTS, localId); synced++; }
                else failed++;
            }
            // Process installments
            for (const r of (data.installments?.results || [])) {
                const localId = r.local_id || r.localId;
                if (r.success && localId) { await markSynced(STORE_INSTALLMENTS, localId); synced++; }
                else failed++;
            }

            await refreshSyncUI();
            if (synced) notify(t('synced_success', { count: synced }), 'success');
            if (failed) notify(t('sync_partial_fail', { count: failed }), 'warning');

        } catch (err) {
            console.error('[SP Offline] sync error:', err);
            notify(t('sync_failed'), 'error');
        } finally {
            isSyncing = false;
            setSyncButtonState(false);
        }
    }

    // ── Bill form interception (capture phase — runs before existing handlers)
    function interceptBillForm() {
        const form = document.getElementById('create-bill');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            if (navigator.onLine) return;
            e.preventDefault();
            e.stopImmediatePropagation();

            if (!document.querySelectorAll('.product-row').length) {
                notify(t('no_products'), 'warning');
                return;
            }
            try {
                await saveRecord(STORE_BILLS, collectBillData(form));
                if (typeof window.clearBillForm === 'function') window.clearBillForm();
                await refreshSyncUI();
                notify(t('bill_saved_offline'), 'success');
            } catch (err) {
                console.error('[SP Offline] bill save error:', err);
                notify(t('save_failed'), 'error');
            }
        }, { capture: true });
    }

    // ── Fetch interceptor: catches customer payments + installment saves + bills ────
    function installFetchInterceptor() {
        const _fetch = window.fetch;

        window.fetch = async function (input, init) {
            const url = typeof input === 'string' ? input : (input?.url ?? String(input));
            const method = ((init?.method) || (typeof input !== 'string' ? input?.method : null) || 'GET').toUpperCase();

            if (method !== 'POST') {
                try {
                    const response = await _fetch.apply(this, arguments);
                    if (response.status === 401 || response.status === 403) {
                        handleUnauthorized(response);
                    }
                    return response;
                } catch {
                    return _fetch.apply(this, arguments);
                }
            }

            const payMatch = url.match(/\/customers\/(\d+)\/payments(?:\?.*)?$/);
            const instMatch = url.match(/\/installments\/from-bill(?:\?.*)?$/);
            const billMatch = url.match(/\/bills(?:\/.*)?$/);

            if (!payMatch && !instMatch && !billMatch) {
                try {
                    const response = await _fetch.apply(this, arguments);
                    if (response.status === 401 || response.status === 403) {
                        handleUnauthorized(response);
                    }
                    return response;
                } catch {
                    return _fetch.apply(this, arguments);
                }
            }

            try {
                const response = await _fetch.apply(this, arguments);
                if (response.status === 401 || response.status === 403) {
                    handleUnauthorized(response);
                    return response;
                }
                return response;
            } catch {
                setOfflineState();
                if (payMatch) {
                    try {
                        const customerId = parseInt(payMatch[1], 10);
                        const payData = {};
                        if (init.body instanceof FormData) {
                            for (const [k, v] of init.body.entries()) payData[k] = v;
                        }
                        const localId = 'pay_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9);
                        await saveRecord(STORE_PAYMENTS, {
                            localId,
                            customer_id : customerId,
                            amount      : payData.amount,
                            type        : payData.type        || 'cash',
                            note        : payData.note        || '',
                            payment_date: payData.payment_date || new Date().toISOString().slice(0, 10),
                        });
                        await refreshSyncUI();
                        notify(t('payment_saved_offline'), 'success');
                        return new Response(
                            JSON.stringify({ success: true, new_balance: null, offline: true }),
                            { status: 200, headers: { 'Content-Type': 'application/json' } }
                        );
                    } catch {
                        return _fetch.apply(this, arguments);
                    }
                }

                if (instMatch) {
                    try {
                        const rawBody = init?.body;
                        const body = typeof rawBody === 'string' ? JSON.parse(rawBody) : {};
                        const localId = 'inst_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9);
                        await saveRecord(STORE_INSTALLMENTS, { ...body, localId });
                        await refreshSyncUI();
                        notify(t('installment_saved_offline'), 'success');
                        return new Response(
                            JSON.stringify({ success: true, offline: true }),
                            { status: 200, headers: { 'Content-Type': 'application/json' } }
                        );
                    } catch {
                        return _fetch.apply(this, arguments);
                    }
                }

                if (billMatch) {
                    try {
                        const fd = init.body instanceof FormData ? init.body : new FormData();
                        const data = collectBillDataFromFormData(fd);
                        await saveRecord(STORE_BILLS, data);
                        await refreshSyncUI();
                        notify(t('bill_saved_offline'), 'success');
                        return new Response(
                            JSON.stringify({ success: true, offline: true, local_id: data.localId }),
                            { status: 200, headers: { 'Content-Type': 'application/json' } }
                        );
                    } catch {
                        return _fetch.apply(this, arguments);
                    }
                }

                return _fetch.apply(this, arguments);
            }
        };
    }

    function handleUnauthorized(response) {
        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({ type: 'SP_SET_AUTH', authenticated: false });
        }
        const path = window.location.pathname;
        const isProtected = path === '/dashboard' || path === '/bills/create' || path.startsWith('/bills/') || path.startsWith('/products/') || path.startsWith('/customers/') || path.startsWith('/settings') || path.startsWith('/installments') || path.startsWith('/purchase-bills');
        if (isProtected && !path.includes('/login')) {
            showNotification('You have been logged out', 'warning');
            setTimeout(() => {
                window.location.href = '/login';
            }, 1500);
        }
    }

    // ── Connectivity ────────────────────────────────────────────────────────
    let isOffline = false;

    function setOfflineState() {
        if (!isOffline) {
            isOffline = true;
            setBannerVisible(true);
        }
    }

    function setOnlineState() {
        if (isOffline) {
            isOffline = false;
            setBannerVisible(false);
            syncAll();
        }
    }

    function watchConnectivity() {
        const probe = async () => {
            let offline = !navigator.onLine;
            if (!offline) {
                try {
                    const controller = new AbortController();
                    const timeout = setTimeout(() => controller.abort(), 5000);
                    await fetch('/?_sp_probe=' + Date.now(), {
                        method: 'HEAD',
                        cache: 'no-store',
                        signal: controller.signal,
                    });
                    clearTimeout(timeout);
                } catch {
                    offline = true;
                }
            }
            if (offline) {
                setOfflineState();
            } else {
                setOnlineState();
            }
        };

        window.addEventListener('online',  probe);
        window.addEventListener('offline', probe);
        probe();
        setInterval(probe, 5000);
    }

    watchConnectivity();

    // Background sync from SW message
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (e) => {
            if (e.data?.type === 'SP_TRIGGER_SYNC') syncAll();
        });
    }

    // Register background sync tag when going offline
    window.addEventListener('offline', async () => {
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            try {
                const reg = await navigator.serviceWorker.ready;
                await reg.sync.register(SYNC_TAG);
            } catch (_) { /* fallback: online event handles it */ }
        }
    });

    // ── Public API ──────────────────────────────────────────────────────────
    window.spSyncNow = syncAll;

    window.spSaveBillOffline = async function(form) {
        const data = collectBillData(form);
        await saveRecord(STORE_BILLS, data);
        await refreshSyncUI();
        notify(t('bill_saved_offline'), 'success');
        return data.localId;
    };

    // ── Init ────────────────────────────────────────────────────────────────
    async function init() {
        userId = window.spCurrentUserId;
        if (!userId) return;

        try { await getDB(); } catch (err) {
            console.warn('[SP Offline] IndexedDB unavailable:', err);
            return;
        }

        installFetchInterceptor(); // must be early so it wraps fetch before page JS uses it
        interceptBillForm();
        await refreshSyncUI();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
