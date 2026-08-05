// SalesPoint Enhanced Service Worker v4
// Pages: stale-while-revalidate | Vite assets: cache-first | Images: skip

const SHELL_CACHE = 'sp-shell-v4';
const ASSET_CACHE = 'sp-assets-v4';
const ALL_CACHES = [SHELL_CACHE, ASSET_CACHE];

// Pre-cache these on install so the dashboard loads offline immediately
const PRECACHE_URLS = ['/dashboard', '/bills/create'];

// Never cache these (mutations, images, external CDN)
const BYPASS_TESTS = [
    (url, req) => req.method !== 'GET',
    (url) => url.origin !== self.location.origin,
    (url) => /\.(png|jpe?g|gif|webp|ico|svg|woff2?|ttf|eot)(\?|$)/i.test(url.pathname),
    (url) => /\/(offline\/sync|bills\/store|products\/search|api\/)/i.test(url.pathname),
];

// Vite build assets have content hashes ? safe for long-lived cache
const isViteAsset = (url) => url.pathname.startsWith('/build/assets/');

// -- Install ----------------------------------------------------------------
self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(SHELL_CACHE);
            await Promise.allSettled(
                PRECACHE_URLS.map(async (url) => {
                    try {
                        const response = await fetch(url, { credentials: 'include' });
                        if (response && response.ok) {
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

    // Skip anything in the bypass list
    if (BYPASS_TESTS.some((fn) => fn(url, event.request))) return;

    if (isViteAsset(url)) {
        // Content-hashed assets: serve from cache forever, fetch if missing
        event.respondWith(cacheFirst(event.request, ASSET_CACHE));
        return;
    }

    // Everything else (navigation + misc GETs): stale-while-revalidate
    event.respondWith(staleWhileRevalidate(event.request, SHELL_CACHE));
});

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

// Stale-while-revalidate: return cached immediately and refresh in background
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    // Always kick off a background network fetch to keep cache fresh
    const networkFetch = fetch(request).then((res) => {
        if (res.ok) cache.put(request, res.clone());
        return res;
    }).catch(() => null);

    if (cached) {
        networkFetch.catch(() => {}); // background update, do not await
        return cached;
    }

    // No cache � must wait for network
    const response = await networkFetch;
    if (response) return response;

    // Network failed and no cache � return offline fallback for navigation
    if (request.mode === 'navigate') {
        const fallback = await caches.match('/dashboard');
        if (fallback) return fallback;
        const salesFallback = await caches.match('/bills/create');
        if (salesFallback) return salesFallback;
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
    return new Response('', { status: 503 });
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

    if (event.data?.type === 'SP_WARM_CACHE') {
        const urls = Array.isArray(event.data.urls) ? event.data.urls : [];
        event.waitUntil(
            (async () => {
                const cache = await caches.open(SHELL_CACHE);
                await Promise.allSettled(urls.map(async (url) => {
                    try {
                        const response = await fetch(url, { credentials: 'include' });
                        if (response && response.ok) {
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
