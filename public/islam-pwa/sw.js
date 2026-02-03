// Islamic Sales PWA Service Worker
// Cache-first strategy for offline functionality

const CACHE_NAME = 'islamic-sales-v1';
const ASSETS_TO_CACHE = [
    '/islam',
    '/islam/',
    '/islam/index',
    '/islam-pwa/manifest.json',
    '/css/app.css',
    '/js/app.js',
    '/images/logo.png',
    'https://cdnjs.cloudflare.com/ajax/libs/sql.js/1.10.3/sql-wasm.js',
    'https://cdnjs.cloudflare.com/ajax/libs/sql.js/1.10.3/sql-wasm.wasm'
];

// Install event - cache all assets
self.addEventListener('install', (event) => {
    console.log('[SW] Installing Service Worker...');
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[SW] Caching app assets');
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
    self.skipWaiting();
});

// Activate event - clean up old caches
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

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Skip cross-origin requests (like CDN)
    if (!event.request.url.startsWith(self.location.origin)) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            if (cachedResponse) {
                // Return cached version
                console.log('[SW] Serving from cache:', event.request.url);
                return cachedResponse;
            }

            // Not in cache, fetch from network
            console.log('[SW] Fetching from network:', event.request.url);
            return fetch(event.request).then((networkResponse) => {
                // Cache the new resource for next time
                if (networkResponse.ok) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return networkResponse;
            }).catch((error) => {
                console.log('[SW] Fetch failed:', error);

                // Return offline page for navigation requests
                if (event.request.mode === 'navigate') {
                    return caches.match('/islam');
                }

                throw error;
            });
        })
    );
});

// Background sync for when coming back online
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync:', event.tag);
    if (event.tag === 'sync-transactions') {
        event.waitUntil(syncTransactions());
    }
});

async function syncTransactions() {
    // This can be extended to sync with server when needed
    console.log('[SW] Syncing transactions...');
}

// Push notifications (optional)
self.addEventListener('push', (event) => {
    console.log('[SW] Push notification received');

    const options = {
        body: event.data ? event.data.text() : 'New sales update',
        icon: '/images/logo.png',
        badge: '/images/logo.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            { action: 'view', title: 'View' },
            { action: 'close', title: 'Close' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification('Islamic Sales', options)
    );
});

self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification click:', event.action);
    event.notification.close();

    if (event.action === 'view') {
        event.waitUntil(
            clients.openWindow('/islam')
        );
    }
});

console.log('[SW] Service Worker loaded');
