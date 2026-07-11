const CACHE_NAME = 'cyno-erp-v2';
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

// Fetch Event - Cache static assets only; PHP pages are NEVER cached
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    
    const url = new URL(event.request.url);
    
    // NEVER cache dynamic PHP pages (prevents user data leakage between sessions)
    if (url.pathname.endsWith('.php') || url.pathname === '/' || url.search) {
        event.respondWith(
            fetch(event.request).catch(() => {
                if (event.request.mode === 'navigate') {
                    return caches.match('offline.php');
                }
            })
        );
        return;
    }
    
    // For static assets (CSS, JS, images, fonts): Cache First, fallback to Network
    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            if (cachedResponse) return cachedResponse;
            return fetch(event.request).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return networkResponse;
            }).catch(() => {
                // If not in cache and network fails, nothing to show
                return undefined;
            });
        })
    );
});
