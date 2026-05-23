// Renders participant grid squares on a Leaflet map; falls back to a
// grouped list if Leaflet/tiles are unavailable (offline / constrained net).
(function () {
  const el = document.querySelector('[data-net-map]');
  const data = document.querySelector('[data-map-json]');
  if (!el || !data) return;
  let points = [];
  try { points = JSON.parse(data.textContent) || []; } catch (_) {}

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
  } catch (_) { listFallback(); }
})();
