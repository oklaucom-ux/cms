const CACHE_NAME = 'cyno-erp-v1';
const STATIC_ASSETS = [
    'assets/css/style.css',
    'assets/icons/icon-192x192.png',
    'assets/icons/icon-512x512.png',
    'offline.php',
    'https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css',
    'https://cdn.jsdelivr.net/npm/simple-datatables@latest',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11'
];

// Install Event - Cache Static Assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

// Activate Event - Clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Fetch Event - Network First, fallback to Cache, fallback to Offline page
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    
    // For API calls or dynamic PHP pages: Network First
    event.respondWith(
        fetch(event.request)
            .then((networkResponse) => {
                // If it's a valid response, cache a copy for offline use
                if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return networkResponse;
            })
            .catch(() => {
                // If network fails (offline), try to serve from cache
                return caches.match(event.request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // If not in cache and it's a page request, show offline page
                    if (event.request.mode === 'navigate') {
                        return caches.match('offline.php');
                    }
                });
            })
    );
});
