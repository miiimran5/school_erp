const CACHE_NAME = 'oasis-erp-static-v1';
const STATIC_ASSETS = [
  'assets/css/app.css',
  'assets/css/adminlte.min.css',
  'assets/js/app.js',
  'assets/js/adminlte.min.js',
  'assets/plugins/jquery/jquery.min.js',
  'assets/plugins/bootstrap/js/bootstrap.bundle.min.js',
  'assets/plugins/fontawesome-free/css/all.min.css'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(STATIC_ASSETS);
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (event) {
  if (event.request.method !== 'GET') {
    return;
  }
  const requestUrl = new URL(event.request.url);
  if (requestUrl.origin !== self.location.origin || requestUrl.pathname.endsWith('.php')) {
    return;
  }
  event.respondWith(
    caches.match(event.request).then(function (cached) {
      return cached || fetch(event.request).then(function (response) {
        const copy = response.clone();
        caches.open(CACHE_NAME).then(function (cache) {
          cache.put(event.request, copy);
        });
        return response;
      });
    })
  );
});
