// Islamic Sales PWA Service Worker
// Network-first strategy with cache fallback

const CACHE_NAME = 'islamic-sales-v2';

self.addEventListener('install', (event) => {
    console.log('[SW] Installing Service Worker...');
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log('[SW] Activating Service Worker...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => {
                        console.log('[SW] Deleting old cache:', name);
                        return caches.delete(name);
                    })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then((networkResponse) => {
                // Clone the response before caching
                const responseClone = networkResponse.clone();

                // Cache the response for next time
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(event.request, responseClone);
                });

                return networkResponse;
            })
            .catch(() => {
                // Network failed, try cache
                return caches.match(event.request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }

                    // Return offline page for navigation requests
                    if (event.request.mode === 'navigate') {
                        return caches.match('/islam');
                    }

                    // Return a basic response for other requests
                    return new Response('Offline', {
                        status: 503,
                        statusText: 'Service Unavailable'
                    });
                });
            })
    );
});

console.log('[SW] Service Worker loaded');
