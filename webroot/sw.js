/**
 * M5 T19 — eQSL Card service worker.
 *
 * Three caching strategies, chosen per-URL pattern:
 *
 *   cache-first      static assets (long-lived, content-hashed by mtime)
 *                    /css/*, /js/*, /img/*, /font/*, /files/*
 *
 *   network-first    HTML pages + JSON APIs
 *                    everything else under /qsos, /cards, /templates,
 *                    /dashboard, /activations, etc.
 *                    Falls back to cached copy if offline.
 *
 *   network-only     admin + auth + install — never cache
 *                    /admin/*, /login, /logout, /password/*, /install*
 *                    Admin work happens at a desk on wifi; caching is
 *                    a footgun. Auth pages must always be live so a
 *                    stale session doesn't show.
 *
 * Versioning: bump CACHE_VERSION when you ship a release. activate()
 * sweeps caches whose names don't match the current version, so old
 * shells get pruned automatically.
 *
 * Scope: registered at "/" so it intercepts every request on the
 * origin. The fetch handler narrows by path before deciding strategy.
 *
 * Phase D continues:
 *   T20 — IndexedDB schema for offline qsos queue
 *   T21 — POST intercept (stash qsos in IndexedDB when offline)
 *   T22 — Sync engine (drain queue on 'online' event)
 *   T23 — Status pill
 */

const CACHE_VERSION = 'eqsl-v1.1.0-pwa-2026-05-16';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;

/**
 * Static assets we want available offline immediately on first install.
 * The fetched URLs land in STATIC_CACHE. Listing nothing here keeps
 * install fast; the cache-first handler picks up subsequent loads.
 */
const PRECACHE_URLS = [];

/** URL patterns by strategy. order matters: first match wins. */
const NETWORK_ONLY_PATTERNS = [
  /^\/admin\b/,
  /^\/login\b/,
  /^\/logout\b/,
  /^\/password\b/,
  /^\/install\b/,
  /^\/email\/verify\b/,
];

const CACHE_FIRST_PATTERNS = [
  /^\/css\//,
  /^\/js\//,
  /^\/img\//,
  /^\/font\//,
  /^\/files\/(fonts|templates|help)\//,  // user/card files NOT cached — they change per user
  /^\/manifest\.webmanifest$/,
];

self.addEventListener('install', (event) => {
  // Activate immediately on first install — no waiting for old SW to
  // release the tab. New installs always run the latest worker.
  self.skipWaiting();
  if (PRECACHE_URLS.length === 0) return;
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE_URLS))
  );
});

self.addEventListener('activate', (event) => {
  // Sweep old version caches. The pattern matches anything starting
  // with 'eqsl-v' but NOT the current CACHE_VERSION prefix.
  event.waitUntil(
    caches.keys().then((names) =>
      Promise.all(
        names
          .filter((name) => name.startsWith('eqsl-v') && !name.startsWith(CACHE_VERSION))
          .map((name) => caches.delete(name))
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  // We only intercept same-origin GET — POST/PUT/PATCH/DELETE always
  // go straight through to the network (T21 will add offline queueing
  // for the specific /qsos/quick POST endpoint).
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  const path = url.pathname;

  if (NETWORK_ONLY_PATTERNS.some((re) => re.test(path))) {
    // Don't even touch the cache — let the request fall through.
    return;
  }

  if (CACHE_FIRST_PATTERNS.some((re) => re.test(path))) {
    event.respondWith(cacheFirst(req));
    return;
  }

  // Default: network-first for HTML / JSON pages. Cache stays warm so
  // the app shell loads instantly on the next visit, even offline.
  event.respondWith(networkFirst(req));
});

/**
 * cache-first: return cached copy if present, otherwise fetch and
 * cache. Network failure on a cache miss → throw, browser shows the
 * standard offline page.
 */
async function cacheFirst(req) {
  const cache = await caches.open(STATIC_CACHE);
  const cached = await cache.match(req);
  if (cached) return cached;
  const resp = await fetch(req);
  if (resp && resp.status === 200 && resp.type === 'basic') {
    cache.put(req, resp.clone());
  }
  return resp;
}

/**
 * network-first: try the network. On success, update the runtime
 * cache. On failure (offline / 5xx), fall back to the cached copy
 * if any. If both fail, throw — Cake's standard error page kicks in.
 */
async function networkFirst(req) {
  const cache = await caches.open(RUNTIME_CACHE);
  try {
    const resp = await fetch(req);
    if (resp && resp.status === 200 && resp.type === 'basic') {
      cache.put(req, resp.clone());
    }
    return resp;
  } catch (err) {
    const cached = await cache.match(req);
    if (cached) return cached;
    throw err;
  }
}
