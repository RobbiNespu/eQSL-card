/**
 * Convert a signal-strength distribution object to an array of bar descriptors
 * for the net-session signal chart. Pure function, unit-tested in isolation.
 *
 * @param {{ [signal: string]: number }} dist
 *   Keys are S-unit strings ('1'–'9') or 'unknown'; values are check-in counts.
 * @returns {{ label: string, key: string, count: number, heightPct: number }[]}
 *   Bars sorted by signal key, with `heightPct` scaled relative to the tallest bar.
 */
export function signalBars(dist) {
  const entries = Object.entries(dist)
    .filter(([, c]) => c > 0)
    .map(([k, c]) => ({ label: k === 'unknown' ? '?' : 'S' + k, key: k, count: c }));
  const max = Math.max(1, ...entries.map(e => e.count));
  return entries.map(e => ({ ...e, heightPct: Math.round((e.count / max) * 100) }));
}

/**
 * Render the signal-distribution bar chart into a DOM container.
 * Replaces the container's innerHTML with a `.net-chart` div.
 *
 * @param {HTMLElement} container - element to render into
 * @param {{ [signal: string]: number }} dist - signal distribution (see signalBars)
 */
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
    if (el && data) {
      try {
        renderSignalChart(el, JSON.parse(data.textContent));
      } catch (err) {
        console.error('[net-charts] failed to parse signal JSON', err);
      }
    }
  });
  document.addEventListener('net:updated', (e) => {
    const el = document.querySelector('[data-signal-chart]');
    if (el && e.detail && e.detail.stats && e.detail.stats.signal) renderSignalChart(el, e.detail.stats.signal);
  });
}
