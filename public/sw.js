// SalesPoint Service Worker
// Strategy: network-first for protected pages, stale-while-revalidate for shell,
// cache-first for static assets. All API calls bypass the SW.

const SHELL_CACHE = 'sp-shell-v9';
const ASSET_CACHE = 'sp-assets-v9';
const AUTH_CACHE = 'sp-auth-v1';
const ALL_CACHES = [SHELL_CACHE, ASSET_CACHE, AUTH_CACHE];

const PRECACHE_URLS = [
    '/dashboard',
    '/bills/create',
    '/js/salespoint-offline.js',
];

const BYPASS_TESTS = [
    (url, req) => req.method !== 'GET',
    (url) => url.origin !== self.location.origin,
    (url) => /\.(png|jpe?g|gif|webp|ico|svg|woff2?|ttf|eot)(\?|$)/i.test(url.pathname),
    (url) => url.href.includes('_sp_probe'),
    (url) => /^\/(api\/|auth\/check|offline\/sync|bills\/store|products\/search|customers\/search|customers\/\d+\/payments|products\/searchWithoutBarcode|products\/searchAll|products\/search-barcode|api\/tags|api\/active-sales|bills\/quick-stats|tags|sales|installments|suppliers|purchase-bills|payments-receipts|settings|profile|admin|shopowner|products\/\d+|customers\/\d+|bills\/\d+|uploads\/)/i.test(url.pathname),
];

const isViteAsset = (url) => url.pathname.startsWith('/build/assets/');
const isCriticalStatic = (url) => /^\/(js|css)\//i.test(url.pathname);
const PROTECTED_PAGES = ['/dashboard', '/bills/create'];

// -- Install ----------------------------------------------------------------
self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(SHELL_CACHE);
            await Promise.allSettled(
                PRECACHE_URLS.map(async (url) => {
                    try {
                        const response = await fetch(url, { credentials: 'include' });
                        const requestedUrl = new URL(url, self.location.origin).href;
                        if (response && response.ok && response.url === requestedUrl) {
                            await cache.put(url, response.clone());
                        }
                    } catch (_) {
                        // Non-fatal: runtime caching will still populate entries.
                    }
                })
            );
            await self.skipWaiting();
        })()
    );
});

// -- Activate ---------------------------------------------------------------
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => !ALL_CACHES.includes(k)).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

// -- Fetch ------------------------------------------------------------------
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (BYPASS_TESTS.some((fn) => fn(url, event.request))) return;

    if (isViteAsset(url)) {
        event.respondWith(cacheFirst(event.request, ASSET_CACHE));
        return;
    }

    if (event.request.mode === 'navigate') {
        if (PROTECTED_PAGES.includes(url.pathname)) {
            event.respondWith(protectedPageNetworkFirst(event.request, SHELL_CACHE));
        } else {
            event.respondWith(offlineRedirectToDashboard(event.request, SHELL_CACHE));
        }
        return;
    }

    if (isCriticalStatic(url)) {
        event.respondWith(cacheFirst(event.request, SHELL_CACHE));
        return;
    }

    // Bypass everything else (API calls, etc.)
});

// -- Auth State --------------------------------------------------------------
async function setAuthState(authenticated) {
    const cache = await caches.open(AUTH_CACHE);
    const response = new Response(JSON.stringify({ authenticated }), {
        headers: { 'Content-Type': 'application/json' }
    });
    await cache.put('/state', response);
}

async function getAuthState() {
    try {
        const cache = await caches.open(AUTH_CACHE);
        const response = await cache.match('/state');
        if (!response) return null;
        const data = await response.json();
        return data.authenticated === true ? true : false;
    } catch {
        return null;
    }
}

// -- Caching Strategies -----------------------------------------------------

// Cache-first: return cached copy; fetch & cache on miss
async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;
    try {
        const response = await fetch(request);
        if (response.ok) (await caches.open(cacheName)).put(request, response.clone());
        return response;
    } catch {
        return new Response('', { status: 503 });
    }
}

// Network-first for protected pages: always try server first when online,
// fall back to cache only on network failure.
async function protectedPageNetworkFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const requestedUrl = new URL(request.url).href;

    try {
        const response = await fetch(request);
        // Don't cache redirect responses (e.g., /dashboard -> /login)
        if (response.ok && response.url === requestedUrl) {
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        // Network failed: try cache if user was previously authenticated
        const cached = await cache.match(request);
        if (cached && (await getAuthState()) === true) {
            return cached;
        }
        // No valid cache or user not authenticated
        return authRequiredResponse();
    }
}

// Stale-while-revalidate for shell pages that are not protected
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    if (cached) {
        fetch(request).then((res) => {
            if (res.ok) cache.put(request, res.clone());
        }).catch(() => {});
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) cache.put(request, response.clone());
        return response;
    } catch {
        return offlineOrAuthRequiredResponse(request, cache);
    }
}

// Network-first with dashboard fallback for all other navigation requests
async function offlineRedirectToDashboard(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const fallback = await caches.match('/dashboard');
        if (fallback && (await getAuthState()) !== true) {
            return authRequiredResponse();
        }
        if (fallback) return fallback;
        const salesFallback = await caches.match('/bills/create');
        if (salesFallback && (await getAuthState()) !== true) {
            return salesFallback;
        }
        return offlineResponse();
    }
}

// -- Response Helpers -------------------------------------------------------

function authRequiredResponse() {
    return new Response(
        '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Login Required</title>' +
        '<style>body{font-family:sans-serif;text-align:center;padding:60px;background:#f8fafc}' +
        'h2{color:#1e40af}p{color:#6b7280}</style></head>' +
        '<body><h2>Login Required</h2><p>You have been logged out. Please go to the login page.</p></body></html>',
        { headers: { 'Content-Type': 'text/html' } }
    );
}

function offlineResponse() {
    return new Response(
        '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Offline</title>' +
        '<style>body{font-family:sans-serif;text-align:center;padding:60px;background:#f8fafc}' +
        'h2{color:#1e40af}button{margin-top:16px;padding:10px 24px;background:#3b82f6;color:#fff;' +
        'border:none;border-radius:8px;font-size:1rem;cursor:pointer}</style></head>' +
        '<body><h2>You are offline</h2><p>Check your internet connection and try again.</p>' +
        '<button onclick="location.reload()">Retry</button></body></html>',
        { headers: { 'Content-Type': 'text/html' } }
    );
}

async function offlineOrAuthRequiredResponse(request, cache) {
    if (request.mode !== 'navigate') return new Response('', { status: 503 });

    const fallback = await cache.match('/dashboard');
    if (fallback && (await getAuthState()) !== true) {
        return authRequiredResponse();
    }
    if (fallback) return fallback;

    const salesFallback = await cache.match('/bills/create');
    if (salesFallback && (await getAuthState()) !== true) {
        return salesFallback;
    }
    return offlineResponse();
}

// -- Background Sync --------------------------------------------------------
self.addEventListener('sync', (event) => {
    if (event.tag === 'sp-sync-bills') {
        event.waitUntil(
            self.clients.matchAll({ type: 'window' }).then((clients) =>
                clients.forEach((c) => c.postMessage({ type: 'SP_TRIGGER_SYNC' }))
            )
        );
    }
});

// -- Message Handler --------------------------------------------------------
self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
        return;
    }

    if (event.data?.type === 'SP_SET_AUTH') {
        event.waitUntil(setAuthState(!!event.data.authenticated));
        return;
    }

    if (event.data?.type === 'SP_WARM_CACHE') {
        const urls = Array.isArray(event.data.urls) ? event.data.urls : [];
        event.waitUntil(
            (async () => {
                const cache = await caches.open(SHELL_CACHE);
                await Promise.allSettled(urls.map(async (url) => {
                    try {
                        const response = await fetch(url, { credentials: 'include' });
                        const requestedUrl = new URL(url, self.location.origin).href;
                        if (response && response.ok && response.url === requestedUrl) {
                            await cache.put(url, response.clone());
                        }
                    } catch (_) {
                        // Ignore warm-up failures; runtime caching will retry.
                    }
                }));
            })()
        );
    }
});
