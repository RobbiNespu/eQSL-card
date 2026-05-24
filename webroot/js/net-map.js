/**
 * Render participant grid-square locations on a Leaflet map.
 *
 * Falls back to a grouped text list (`listFallback`) when Leaflet is not
 * available (e.g. offline or a constrained network that can't load tiles)
 * or when no grid-square data is present. The fallback is also used if the
 * Leaflet initialisation throws (e.g. invalid lat/lon in the data).
 *
 * Reads point data from the inline `data-map-json` script element
 * (array of `{ callsign, grid, lat, lon, signal? }` objects).
 */
(function () {
  const el = document.querySelector('[data-net-map]');
  const data = document.querySelector('[data-map-json]');
  if (!el || !data) return;
  let points = [];
  try {
    points = JSON.parse(data.textContent) || [];
  } catch (err) {
    console.error('[net-map] failed to parse map JSON', err);
  }

  /**
   * Render a grouped text list as a fallback when the map is unavailable.
   * Groups callsigns by grid square for a compact display.
   */
  function listFallback() {
    if (points.length === 0) { el.innerHTML = '<p class="net-map-empty">No grid squares logged.</p>'; return; }
    const byGrid = {};
    points.forEach(p => { (byGrid[p.grid] = byGrid[p.grid] || []).push(p.callsign); });
    el.innerHTML = '<ul class="net-map-fallback">' +
      Object.entries(byGrid).map(([g, cs]) => `<li><strong>${g}</strong>: ${cs.join(', ')}</li>`).join('') +
      '</ul>';
  }

  if (typeof L === 'undefined' || points.length === 0) { listFallback(); return; }
  try {
    const map = L.map(el).setView([points[0].lat, points[0].lon], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap', maxZoom: 12,
    }).addTo(map);
    points.forEach(p => L.marker([p.lat, p.lon]).addTo(map)
      .bindPopup(`<strong>${p.callsign}</strong><br>${p.grid}${p.signal ? ' · S' + p.signal : ''}`));
  } catch (err) {
    console.error('[net-map] Leaflet initialisation failed', err);
    listFallback();
  }
})();
