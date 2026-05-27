/**
 * Net-session polling client for the NCS cockpit view.
 *
 * Fetches the delta feed (`window.NET.feedUrl`) every 4 seconds while
 * the page is visible and the session is live. Merges updates into the
 * shared `window.__netStore` RosterStore (written by net-cockpit.js) and
 * dispatches a `net:updated` event so the cockpit and stats elements can
 * re-render without coupling to this module.
 *
 * Only runs when `window.NET.status === 'live'`; silently no-ops for
 * closed/archived sessions so the page stays static.
 */
import { RosterStore, startPollLoop } from './net-merge.js';

(function () {
  const cfg = window.NET;
  if (!cfg || cfg.status !== 'live') return;
  const store = window.__netStore || new RosterStore();
  let since = '';
  let lastEtag = '';

  /** Fetch the latest check-in delta and merge it into the roster. */
  async function tick() {
    if (document.hidden) return;
    try {
      const headers = { 'Accept': 'application/json' };
      if (lastEtag) headers['If-None-Match'] = lastEtag;
      const res = await fetch(cfg.feedUrl + (since ? ('?since=' + encodeURIComponent(since)) : ''), {
        headers,
      });
      if (res.status === 304) return;
      lastEtag = res.headers.get('ETag') || lastEtag;
      const json = await res.json();
      since = json.server_time || since;
      (json.checkins || []).forEach(r => store.upsert(r));
      (json.removed || []).forEach(id => store.remove(id));
      document.dispatchEvent(new CustomEvent('net:updated', { detail: json }));
    } catch (err) {
      console.error('[net-poll] feed fetch failed', err);
    }
  }

  startPollLoop(cfg, tick);
})();
