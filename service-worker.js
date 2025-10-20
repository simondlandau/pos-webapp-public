'use strict';

var CACHE_NAME = 'company-scanner-v6';
var urlsToCache = [
  '/svp/inventory_scanner.html',
  'https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js',
  'https://cdn.jsdelivr.net/npm/eruda/eruda.min.js'
];

console.log('SW script loaded');

self.addEventListener('install', function(event) {
  console.log('SW installing');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('SW caching files');
        // Add files one by one with error handling
        return Promise.all(
          urlsToCache.map(function(url) {
            return cache.add(url).catch(function(error) {
              console.log('Failed to cache:', url, error);
            });
          })
        );
      })
      .then(function() {
        console.log('SW caching complete');
        return self.skipWaiting();
      })
  );
});

self.addEventListener('activate', function(event) {
  console.log('SW activating');
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            console.log('SW deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(function() {
      console.log('SW claiming clients');
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', function(event) {
  var url = event.request.url;
  
  // Handle API requests - network only
  if (url.indexOf('inventory_api.php') !== -1) {
    console.log('SW intercepting API');
    
    event.respondWith(
      fetch(event.request)
        .then(function(response) {
          console.log('API online');
          return response;
        })
        .catch(function(error) {
          console.log('API offline');
          return new Response(
            JSON.stringify({ 
              success: false, 
              error: 'Offline',
              offline: true 
            }),
            { 
              status: 200,
              headers: { 'Content-Type': 'application/json' }
            }
          );
        })
    );
    return;
  }
  
  // For everything else - cache first, then network
  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        if (response) {
          console.log('SW serving from cache');
          return response;
        }
        
        console.log('SW fetching from network');
        return fetch(event.request).then(function(response) {
          // Don't cache API responses
          if (response && response.status === 200 && url.indexOf('.php') === -1) {
            var responseToCache = response.clone();
            caches.open(CACHE_NAME).then(function(cache) {
              cache.put(event.request, responseToCache);
            });
          }
          return response;
        });
      })
      .catch(function(error) {
        console.log('SW offline and not in cache');
        return new Response('Offline - page not cached', { 
          status: 503,
          statusText: 'Service Unavailable'
        });
      })
  );
});
