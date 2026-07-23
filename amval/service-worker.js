const CACHE_NAME = 'amval-v1.0.0';
const ASSETS_TO_CACHE = [
  '/',
  '/index.php',
  '/login.php',
  '/assets.php',
  '/css/app.css',
  '/css/style.css',
  '/css/mobile-app.css',
  '/js/main.js',
  '/manifest.json',
  '/includes/bottom_nav.php',
  '/includes/navbar.php'
];

// نصب Service Worker و کش کردن فایل‌های استاتیک
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[SW] Caching assets...');
        return cache.addAll(ASSETS_TO_CACHE).catch(err => {
          console.warn('[SW] Some assets failed to cache:', err);
        });
      })
      .then(() => self.skipWaiting())
  );
});

// فعال‌سازی و پاکسازی کش قدیمی
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            console.log('[SW] Deleting old cache:', cache);
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// استراتژی: Network First, fallback to Cache
self.addEventListener('fetch', (event) => {
  // فقط درخواست‌های GET را مدیریت کن
  if (event.request.method !== 'GET') return;

  // از کش کردن API‌ها و صفحه‌های داینامیک PHP صرف نظر کن
  const url = new URL(event.request.url);

  // ignore chrome-extension, etc.
  if (!url.protocol.startsWith('http')) return;

  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        // فقط پاسخ‌های موفق را کش کن
        if (networkResponse && networkResponse.status === 200) {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return networkResponse;
      })
      .catch(() => {
        // آفلاین: تلاش برای دریافت از کش
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          // اگر صفحه HTML درخواست شده و در کش نیست، صفحه fallback را نشان بده
          if (event.request.headers.get('accept')?.includes('text/html')) {
            return caches.match('/index.php');
          }
          return new Response('شما آفلاین هستید و این صفحه در کش موجود نیست.', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: new Headers({ 'Content-Type': 'text/plain; charset=utf-8' })
          });
        });
      })
  );
});

// Notification click (برای نسخه‌های بعدی)
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow('/')
  );
});
