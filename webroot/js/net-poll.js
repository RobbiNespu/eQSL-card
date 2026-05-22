import { RosterStore } from './net-merge.js';

(function () {
  const cfg = window.NET;
  if (!cfg || cfg.status !== 'live') return;
  const store = window.__netStore || new RosterStore();
  let since = '';
  let timer = null;

  async function tick() {
    if (document.hidden) return;
    try {
      const res = await fetch(cfg.feedUrl + '.json' + (since ? ('?since=' + encodeURIComponent(since)) : ''), {
        headers: { 'Accept': 'application/json' },
      });
      if (res.status === 304) return;
      const json = await res.json();
      since = json.server_time || since;
      (json.checkins || []).forEach(r => store.upsert(r));
      (json.removed || []).forEach(id => store.remove(id));
      document.dispatchEvent(new CustomEvent('net:updated', { detail: json }));
    } catch (_) { /* keep polling */ }
  }

  document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
  timer = setInterval(tick, 4000);
  tick();
  window.addEventListener('beforeunload', () => clearInterval(timer));
})();
