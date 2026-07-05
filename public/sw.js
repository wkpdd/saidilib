/**
 * Saidi Papetrie — service worker.
 * Goal: work smoothly on Wi-Fi, 4G, LTE and weak/intermittent connections.
 *
 * Strategy
 *  - App shell + icons + offline page: precached on install.
 *  - Static build assets & fonts (hashed, immutable): cache-first.
 *  - Product images: stale-while-revalidate, capped in count.
 *  - Page navigations: network-first with a short timeout, falling back to the
 *    cached page, then to a branded offline page. This is what makes a spotty
 *    4G connection feel instant/robust instead of hanging.
 *  - Admin, cart/checkout writes and non-GET are never cached.
 */
const VERSION = 'v1';
const SHELL = `saidi-shell-${VERSION}`;
const STATIC = `saidi-static-${VERSION}`;
const IMAGES = `saidi-img-${VERSION}`;
const PAGES = `saidi-pages-${VERSION}`;
const IMAGE_LIMIT = 120;
const NAV_TIMEOUT = 4000;

const PRECACHE = [
  '/offline.html',
  '/img/icon-192.png',
  '/img/icon-512.png',
  '/logov2.jpeg',
  '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => !k.endsWith(VERSION)).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

const isStatic = (url) =>
  url.pathname.startsWith('/build/') ||
  url.pathname.startsWith('/img/') ||
  /\.(css|js|woff2?|ttf)$/.test(url.pathname) ||
  url.host.includes('fonts.googleapis.com') ||
  url.host.includes('fonts.gstatic.com');

const isImage = (url) =>
  url.pathname.startsWith('/storage/') ||
  url.pathname === '/logov2.jpeg' ||
  /\.(webp|jpe?g|png|gif|svg)$/.test(url.pathname);

async function trimCache(name, max) {
  const cache = await caches.open(name);
  const keys = await cache.keys();
  if (keys.length > max) {
    await cache.delete(keys[0]);
    return trimCache(name, max);
  }
}

// Cache-first (with background refresh) — great for immutable assets.
async function cacheFirst(request, cacheName, cap) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const fetching = fetch(request)
    .then((res) => {
      if (res && res.status === 200 && res.type !== 'opaque') {
        cache.put(request, res.clone());
        if (cap) trimCache(cacheName, cap);
      }
      return res;
    })
    .catch(() => null);
  return cached || (await fetching) || fetch(request);
}

// Network-first with timeout — resilient page loads on weak connections.
async function navigate(request) {
  const cache = await caches.open(PAGES);
  try {
    const network = await Promise.race([
      fetch(request),
      new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), NAV_TIMEOUT)),
    ]);
    if (network && network.status === 200) {
      cache.put(request, network.clone());
    }
    return network;
  } catch (e) {
    const cached = await cache.match(request);
    return cached || (await caches.match('/offline.html'));
  }
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return; // never cache cart/checkout writes

  const url = new URL(request.url);
  const sameOrigin = url.origin === self.location.origin;

  // Never interfere with the admin panel.
  if (sameOrigin && url.pathname.startsWith('/admin')) return;

  if (request.mode === 'navigate') {
    event.respondWith(navigate(request));
    return;
  }
  if (isStatic(url)) {
    event.respondWith(cacheFirst(request, STATIC));
    return;
  }
  if (sameOrigin && isImage(url)) {
    event.respondWith(cacheFirst(request, IMAGES, IMAGE_LIMIT));
    return;
  }
});
