/**
 * Public-facing net-session live view.
 *
 * Maintains its own RosterStore and polls the delta feed independently of
 * the NCS cockpit (net-cockpit.js + net-poll.js). Renders the roster table
 * inline and updates the stats header elements on every tick. For live
 * sessions polling fires every 4 seconds; for closed sessions a single
 * initial fetch populates the view and no further polling occurs.
 *
 * Requires `window.NET = { feedUrl, status }` to be set by the server.
 */
import { RosterStore, renderRoster, applyStats, startPollLoop } from './net-merge.js';

(function () {
  const cfg = window.NET;
  if (!cfg) return;
  const store = new RosterStore();
  const tbody = document.querySelector('[data-net-roster] tbody');
  let since = '';
  let lastEtag = '';

  /** Re-render the roster tbody from the in-memory RosterStore, newest first. */
  function render() { renderRoster(tbody, store.rows()); }

  /** Fetch the latest check-in delta, merge into the store, and re-render. */
  async function tick() {
    if (document.hidden) return;
    try {
      const headers = { 'Accept': 'application/json' };
      if (lastEtag) headers['If-None-Match'] = lastEtag;
      const res = await fetch(cfg.feedUrl + (since ? ('?since=' + encodeURIComponent(since)) : ''), { headers });
      if (res.status === 304) return;
      lastEtag = res.headers.get('ETag') || lastEtag;
      const json = await res.json();
      since = json.server_time || since;
      (json.checkins || []).forEach(r => store.upsert(r));
      (json.removed || []).forEach(id => store.remove(id));
      render(); applyStats(json.stats);
      document.dispatchEvent(new CustomEvent('net:updated', { detail: json }));
    } catch (err) {
      console.error('[net-live] feed fetch failed', err);
    }
  }

  // Seed from any server-rendered rows (none for public, but harmless).
  document.querySelectorAll('[data-net-roster] tbody tr[data-checkin-id]').forEach(tr => {
    const id = Number(tr.dataset.checkinId); if (id) store.upsert({ id, callsign: tr.children[1]?.textContent?.trim() });
  });

  startPollLoop(cfg, tick);
})();
