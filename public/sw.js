const SW_VERSION = 'v11';
const AUTH_CACHE = `sp-auth-${SW_VERSION}`;
const ASSET_CACHE = `sp-assets-${SW_VERSION}`;
const STATIC_CACHE = `sp-static-${SW_VERSION}`;
const IMAGE_CACHE = `sp-images-${SW_VERSION}`;
const OPAQUE_CACHE = `sp-opaque-${SW_VERSION}`;
const CACHE_PREFIXES = ['sp-pages-', 'sp-data-', 'sp-auth-', 'sp-assets-', 'sp-static-', 'sp-images-', 'sp-opaque-'];
const REQUEST_TIMEOUT_MS = 8000;
const IMAGE_MAX_ENTRIES = 80;
const PAGE_MAX_ENTRIES = 60;
const DATA_MAX_ENTRIES = 120;
const STATIC_MAX_ENTRIES = 60;
const OPAQUE_MAX_ENTRIES = 40;
const OPAQUE_HOSTS = ['fonts.bunny.net', 'cdn.jsdelivr.net', 'unpkg.com'];

const PRECACHE_URLS = [
    '/js/sp-offline-shared.js',
    '/js/sp-offline-core.js',
    '/js/sp-offline-sync-center.js',
];

const SAFE_READ_PATTERNS = [
    /^\/api\/(?:tags|categories|active-sales)(?:\?|$)/i,
    /^\/api\/(?:suppliers\/search|purchase-bills\/search|customers\/search|employees\/search|suppliers\/search-payment)(?:\?|$)/i,
    /^\/products\/(?:search|searchAll|searchWithoutBarcode|search-barcode|get-suppliers|categories|next-id)(?:\?|$)/i,
    /^\/products\/imei\/(?:check|search)(?:\?|$)/i,
    /^\/products\/\d+\/imeis(?:\/available)?(?:\?|$)/i,
    /^\/customers\/\d+\/recent-payments(?:\?|$)/i,
    /^\/bills\/quick-stats(?:\?|$)/i
];

const NAVIGATION_SKIP_PATTERNS = [
    /^\/(?:login|logout)(?:\/|$)/i,
    /^\/auth(?:\/|$)/i,
    /^\/up(?:\?|$)/i,
    /^\/admin(?:\/|$)/i,
    /^\/staff(?:\/|$)/i,
    /^\/password(?:\/|$)/i,
    /^\/profile(?:\/|$)/i,
];

CACHE_PREFIXES.push('sp-shell-');

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);

        await Promise.allSettled(PRECACHE_URLS.map(async (url) => {
            try {
                const response = await fetch(url, { credentials: 'include' });
                const requestedUrl = new URL(url, self.location.origin).href;
                if (response && response.ok && response.url === requestedUrl) {
                    await safeCachePut(STATIC_CACHE, url, response.clone(), STATIC_MAX_ENTRIES);
                }
            } catch (error) {
                // Runtime caching will retry later.
            }
        }));

        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter((key) => {
            if (CACHE_PREFIXES.every((prefix) => !key.startsWith(prefix))) {
                return false;
            }

            return !key.includes(SW_VERSION);
        }).map((key) => caches.delete(key)));

        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || request.headers.has('range')) {
        return;
    }

    if (isOpaqueHost(url)) {
        event.respondWith(cacheFirst(request, OPAQUE_CACHE, true, OPAQUE_MAX_ENTRIES));
        return;
    }

    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.href.includes('_sp_probe=')) {
        return;
    }

    if (isViteAsset(url)) {
        event.respondWith(cacheFirst(request, ASSET_CACHE, false, STATIC_MAX_ENTRIES));
        return;
    }

    if (isVersionedStatic(url)) {
        event.respondWith(staleWhileRevalidate(request, STATIC_CACHE, STATIC_MAX_ENTRIES));
        return;
    }

    if (request.destination === 'image') {
        event.respondWith(staleWhileRevalidate(request, IMAGE_CACHE, IMAGE_MAX_ENTRIES));
        return;
    }

    if (request.mode === 'navigate') {
        if (!shouldHandleNavigation(url)) {
            return;
        }

        event.respondWith(handleNavigation(request, url));
        return;
    }

    if (isSafeReadPath(url.pathname)) {
        event.respondWith(handleSafeRead(request));
    }
});

self.addEventListener('sync', (event) => {
    if (event.tag === 'sp-sync-bills') {
        event.waitUntil(self.clients.matchAll({ type: 'window' }).then((clients) => {
            clients.forEach((client) => client.postMessage({ type: 'SP_TRIGGER_SYNC' }));
        }));
    }
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
        return;
    }

    if (event.data?.type === 'SP_SET_AUTH') {
        event.waitUntil(setAuthState(!!event.data.authenticated, event.data.userId || null));
        return;
    }

    if (event.data?.type === 'SP_WARM_CACHE') {
        event.waitUntil(warmCacheUrls(event.data.urls || [], event.data.requestId || null, event.source || null));
        return;
    }

    if (event.data?.type === 'SP_LIST_CACHED_PAGES' && event.ports && event.ports[0]) {
        event.waitUntil(listCachedPages().then((pages) => {
            event.ports[0].postMessage({ pages });
        }));
    }
});

async function setAuthState(authenticated, userId) {
    const cache = await caches.open(AUTH_CACHE);
    const existing = await getAuthState();

    if (existing && existing.userId && (!authenticated || existing.userId !== userId)) {
        await deleteUserPartitions(existing.userId);
    }

    await cache.put('/state', new Response(JSON.stringify({
        authenticated: authenticated,
        userId: userId,
    }), {
        headers: { 'Content-Type': 'application/json' }
    }));

    if (!authenticated && userId) {
        await deleteUserPartitions(userId);
    }
}

async function getAuthState() {
    try {
        const cache = await caches.open(AUTH_CACHE);
        const response = await cache.match('/state');
        if (!response) {
            return null;
        }

        return await response.json();
    } catch (error) {
        return null;
    }
}

function partitionName(kind, userId) {
    return `sp-${kind}-${SW_VERSION}-u-${userId}`;
}

async function deleteUserPartitions(userId) {
    const keys = await caches.keys();
    await Promise.all(keys.filter((key) => key.endsWith(`-u-${userId}`)).map((key) => caches.delete(key)));
}

function isViteAsset(url) {
    return url.pathname.startsWith('/build/assets/');
}

function isVersionedStatic(url) {
    return /^\/(?:js|css)\//i.test(url.pathname);
}

function isOpaqueHost(url) {
    return OPAQUE_HOSTS.includes(url.hostname);
}

function shouldHandleNavigation(url) {
    return !NAVIGATION_SKIP_PATTERNS.some((pattern) => pattern.test(url.pathname));
}

function isSafeReadPath(path) {
    return SAFE_READ_PATTERNS.some((pattern) => pattern.test(path));
}

async function cacheFirst(request, cacheName, allowOpaque, maxEntries) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    if (cached) {
        return cached;
    }

    const response = await fetch(request);
    if (canCacheResponse(response, allowOpaque)) {
        await safeCachePut(cacheName, request, response.clone(), maxEntries);
    }

    return response;
}

async function staleWhileRevalidate(request, cacheName, maxEntries) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const networkPromise = fetch(request).then(async (response) => {
        if (canCacheResponse(response, response.type === 'opaque')) {
            await safeCachePut(cacheName, request, response.clone(), maxEntries);
        }
        return response;
    }).catch(() => null);

    if (cached) {
        return cached;
    }

    const network = await networkPromise;
    return network || new Response('', { status: 503 });
}

async function fetchWithTimeout(request, timeoutMs) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
        return await fetch(request, { signal: controller.signal });
    } finally {
        clearTimeout(timer);
    }
}

async function handleNavigation(request) {
    const authState = await getAuthState();
    const userId = authState && authState.authenticated ? authState.userId : null;

    try {
        const response = await fetchWithTimeout(request, REQUEST_TIMEOUT_MS);
        if (userId && isCacheableHtml(request, response)) {
            const cache = await caches.open(partitionName('pages', userId));
            await safeCachePut(partitionName('pages', userId), request, response.clone(), PAGE_MAX_ENTRIES);
        }
        return response;
    } catch (error) {
        if (!userId) {
            return authRequiredResponse();
        }

        const cache = await caches.open(partitionName('pages', userId));
        const cached = await cache.match(request);
        if (cached) {
            return cached;
        }

        return offlinePageResponse(userId);
    }
}

async function handleSafeRead(request) {
    const authState = await getAuthState();
    const userId = authState && authState.authenticated ? authState.userId : null;

    if (!userId) {
        return fetch(request);
    }

    const cache = await caches.open(partitionName('data', userId));
    try {
        const response = await fetchWithTimeout(request, REQUEST_TIMEOUT_MS);
        if (response.ok && isCacheableJson(response)) {
            await safeCachePut(partitionName('data', userId), request, response.clone(), DATA_MAX_ENTRIES);
        }
        return response;
    } catch (error) {
        const cached = await cache.match(request);
        return cached || new Response('', { status: 503 });
    }
}

function isCacheableHtml(request, response) {
    const finalUrl = new URL(response.url || request.url);
    const originalUrl = new URL(request.url);
    const contentType = (response.headers.get('Content-Type') || '').toLowerCase();

    return response.status === 200 &&
        finalUrl.pathname === originalUrl.pathname &&
        finalUrl.search === originalUrl.search &&
        contentType.includes('text/html');
}

function isCacheableJson(response) {
    const contentType = (response.headers.get('Content-Type') || '').toLowerCase();
    return response.status === 200 && (contentType.includes('application/json') || contentType.includes('text/json'));
}

async function offlinePageResponse(userId) {
    const pagesCache = await caches.open(partitionName('pages', userId));
    const cached = await pagesCache.match('/offline');
    if (cached) {
        return cached;
    }

    const staticCache = await caches.open(STATIC_CACHE);
    const precached = await staticCache.match('/offline');
    if (precached) {
        return precached;
    }

    return new Response(
        '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">' +
        '<title>Offline</title><style>body{font-family:sans-serif;background:#f8fafc;padding:2rem;color:#111827}.card{max-width:42rem;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:1rem;padding:1.5rem;box-shadow:0 10px 30px rgba(15,23,42,.08)}button{background:#4f46e5;color:#fff;border:0;border-radius:.75rem;padding:.75rem 1rem;font-weight:600}</style></head>' +
        '<body><div class="card"><h1>You are offline / أنت غير متصل</h1><p>Please reconnect and try again. / يرجى إعادة الاتصال ثم المحاولة مرة أخرى.</p><button onclick="location.reload()">Retry / إعادة المحاولة</button></div></body></html>',
        { headers: { 'Content-Type': 'text/html; charset=UTF-8' } }
    );
}

function authRequiredResponse() {
    return new Response('', {
        status: 401,
        headers: { 'Content-Type': 'text/plain; charset=UTF-8' }
    });
}

async function warmCacheUrls(urls, requestId, source) {
    const authState = await getAuthState();
    const userId = authState && authState.authenticated ? authState.userId : null;
    if (!userId) {
        return;
    }

    const cache = await caches.open(partitionName('pages', userId));
    const warmUrls = Array.from(new Set((urls || []).filter((value) => typeof value === 'string'))).slice(0, 30);
    let done = 0;

    for (const value of warmUrls) {
        try {
            const url = new URL(value, self.location.origin);
            if (!shouldHandleNavigation(url)) {
                done += 1;
                await postWarmProgress(source, requestId, done, warmUrls.length);
                continue;
            }

            const response = await fetch(url.href, { credentials: 'include' });
            if (isCacheableHtml(new Request(url.href), response)) {
                await safeCachePut(partitionName('pages', userId), url.pathname + url.search, response.clone(), PAGE_MAX_ENTRIES);
            }
        } catch (error) {
            // Ignore warm-up failures.
        }

        done += 1;
        await postWarmProgress(source, requestId, done, warmUrls.length);
    }

    await postWarmComplete(source, requestId);
}

async function postWarmProgress(source, requestId, done, total) {
    if (!source || !requestId) {
        return;
    }

    source.postMessage({
        type: 'SP_WARM_PROGRESS',
        requestId: requestId,
        done: done,
        total: total,
    });
}

async function postWarmComplete(source, requestId) {
    if (!source || !requestId) {
        return;
    }

    source.postMessage({
        type: 'SP_WARM_COMPLETE',
        requestId: requestId,
        finishedAt: new Date().toISOString(),
    });
}

async function listCachedPages() {
    const authState = await getAuthState();
    const userId = authState && authState.authenticated ? authState.userId : null;
    if (!userId) {
        return [];
    }

    const cache = await caches.open(partitionName('pages', userId));
    const requests = await cache.keys();
    return requests
        .map((request) => {
            const url = new URL(request.url);
            return {
                url: url.pathname + url.search,
                label: formatPageLabel(url.pathname),
            };
        })
        .filter((page) => page.url !== '/offline')
        .sort((left, right) => left.label.localeCompare(right.label));
}

function formatPageLabel(pathname) {
    const segments = pathname.split('/').filter(Boolean).slice(0, 3);
    if (!segments.length) {
        return '/';
    }

    return segments.map((segment) => /^\d+$/.test(segment)
        ? '#'
        : segment.replace(/[-_]+/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()))
        .join(' / ');
}

async function trimCache(cacheName, maxEntries) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    while (keys.length > maxEntries) {
        const oldest = keys.shift();
        await cache.delete(oldest);
    }
}

function canCacheResponse(response, allowOpaque) {
    if (!response) {
        return false;
    }

    if (response.status === 206) {
        return false;
    }

    if (allowOpaque && response.type === 'opaque') {
        return true;
    }

    return response.ok;
}

async function safeCachePut(cacheName, request, response, maxEntries) {
    try {
        const cache = await caches.open(cacheName);
        await cache.put(request, response);
        if (maxEntries) {
            await trimCache(cacheName, maxEntries);
        }
    } catch (error) {
        // Never replace a live response just because caching failed.
    }
}
