/**
 * Render participant grid-square locations on a Leaflet map.
 *
 * Falls back to a grouped text list (`listFallback`) when Leaflet is not
 * available (e.g. offline or a constrained network that can't load tiles)
 * or when no grid-square data is present. The fallback is also used if the
 * Leaflet initialisation throws (e.g. invalid lat/lon in the data).
 *
 * Two rendering paths:
 *   1. Static (analytics): reads point data from the inline `data-map-json`
 *      script element on DOMContentLoaded.
 *   2. Live (cockpit / public view): listens for `net:updated` events and
 *      renders from `e.detail.map` into `[data-net-map]`.
 */
(function () {
  /**
   * Render map points into a container element.
   *
   * Uses Leaflet when available; falls back to a grouped text list if Leaflet
   * is absent, if `points` is empty, or if Leaflet initialisation throws.
   *
   * @param {HTMLElement} el     The container element (must have an explicit height).
   * @param {Array}       points Array of `{ callsign, grid, lat, lon, signal? }` objects.
   */
  function renderInto(el, points) {
    function listFallback() {
      if (points.length === 0) { el.innerHTML = '<p class="net-map-empty">No grid squares logged.</p>'; return; }
      const byGrid = {};
      points.forEach(p => { (byGrid[p.grid] = byGrid[p.grid] || []).push(p.callsign); });
      el.innerHTML = '<ul class="net-map-fallback">' +
        Object.entries(byGrid).map(([g, cs]) => `<li><strong>${g}</strong>: ${cs.join(', ')}</li>`).join('') +
        '</ul>';
    }

    if (typeof L === 'undefined' || points.length === 0) { listFallback(); return; }

    // If a Leaflet map was already initialised in this element, destroy it
    // before re-rendering so the container can be re-used across live ticks.
    if (el._leafletMap) {
      try { el._leafletMap.remove(); } catch (_) {}
      el._leafletMap = null;
    }

    try {
      const map = L.map(el).setView([points[0].lat, points[0].lon], 4);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap', maxZoom: 12,
      }).addTo(map);
      points.forEach(p => L.marker([p.lat, p.lon]).addTo(map)
        .bindPopup(`<strong>${p.callsign}</strong><br>${p.grid}${p.signal ? ' · S' + p.signal : ''}`));
      el._leafletMap = map;
    } catch (err) {
      console.error('[net-map] Leaflet initialisation failed', err);
      listFallback();
    }
  }

  // ── Static path (analytics page) ──────────────────────────────────────────
  // Reads `[data-map-json]` on DOMContentLoaded and renders into `[data-net-map]`.
  (function staticPath() {
    const el = document.querySelector('[data-net-map]');
    const data = document.querySelector('[data-map-json]');
    if (!el || !data) return;
    let points = [];
    try {
      points = JSON.parse(data.textContent) || [];
    } catch (err) {
      console.error('[net-map] failed to parse map JSON', err);
    }
    renderInto(el, points);
  })();

  // ── Live path (cockpit + public view) ─────────────────────────────────────
  // Listens for `net:updated` events dispatched by net-poll.js / net-live.js
  // and re-renders the map whenever the feed delivers a fresh `map` array.
  document.addEventListener('net:updated', (e) => {
    const el = document.querySelector('[data-net-map]');
    if (!el || !e.detail || !Array.isArray(e.detail.map)) return;
    renderInto(el, e.detail.map);
  });
})();
