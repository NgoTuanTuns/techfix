/* TechFix Progressive Web App (PWA) Service Worker
   Caches static HTML, CSS, JS, and image assets for offline access while letting
   authenticated /api/ requests pass directly to the network (with localStorage fallback). */

var CACHE_NAME = "techfix-pwa-v2";
var CORE_ASSETS = [
  "./index.html",
  "./booking.html",
  "./dashboard.html",
  "./login.html",
  "./contact.html",
  "./admin.html",
  "./admin-dashboard.html",
  "./admin-inventory.html",
  "./admin-technicians.html",
  "./manifest.json",
  "../css/style.css",
  "../css/admin-extra.css",
  "../js/motion.js",
  "../js/auth-ui.js",
  "../js/banner-slider.js",
  "../js/card-rail.js",
  "../js/admin-common.js",
  "../assets/logo.png"
];

self.addEventListener("install", function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(CORE_ASSETS).catch(function () {});
    })
  );
  self.skipWaiting();
});

self.addEventListener("activate", function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys
          .filter(function (k) {
            return k !== CACHE_NAME;
          })
          .map(function (k) {
            return caches.delete(k);
          })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener("fetch", function (event) {
  var req = event.request;
  if (req.method !== "GET") return;

  var url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Do not intercept dynamic PHP API endpoints in CacheStorage
  if (url.pathname.indexOf("/api/") !== -1 || url.pathname.slice(-4) === ".php") {
    return;
  }

  // Network-first strategy with cache fallback for static assets and HTML pages
  event.respondWith(
    fetch(req)
      .then(function (networkRes) {
        if (networkRes && networkRes.status === 200) {
          var copy = networkRes.clone();
          caches.open(CACHE_NAME).then(function (cache) {
            cache.put(req, copy);
          });
        }
        return networkRes;
      })
      .catch(function () {
        return caches.match(req).then(function (cached) {
          if (cached) return cached;
          return caches.match(req, { ignoreSearch: true });
        });
      })
  );
});
