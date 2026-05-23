// Dependency-free signal-distribution bars. Pure fn is unit-tested;
// renderSignalChart() paints into a container.
export function signalBars(dist) {
  const entries = Object.entries(dist)
    .filter(([, c]) => c > 0)
    .map(([k, c]) => ({ label: k === 'unknown' ? '?' : 'S' + k, key: k, count: c }));
  const max = Math.max(1, ...entries.map(e => e.count));
  return entries.map(e => ({ ...e, heightPct: Math.round((e.count / max) * 100) }));
}

export function renderSignalChart(container, dist) {
  const bars = signalBars(dist);
  container.innerHTML = `<div class="net-chart">${bars.map(b => `
    <div class="net-chart__col">
      <div class="net-chart__bar" style="height:${b.heightPct}%" title="${b.label}: ${b.count}"></div>
      <div class="net-chart__lbl">${b.label}</div>
    </div>`).join('')}</div>`;
}

if (typeof document !== 'undefined') {
  document.addEventListener('DOMContentLoaded', () => {
    const el = document.querySelector('[data-signal-chart]');
    const data = document.querySelector('[data-signal-json]');
    if (el && data) { try { renderSignalChart(el, JSON.parse(data.textContent)); } catch (_) {} }
  });
  document.addEventListener('net:updated', (e) => {
    const el = document.querySelector('[data-signal-chart]');
    if (el && e.detail && e.detail.stats && e.detail.stats.signal) renderSignalChart(el, e.detail.stats.signal);
  });
}
