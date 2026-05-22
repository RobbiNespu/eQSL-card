import { RosterStore } from './net-merge.js';

(function () {
  const cfg = window.NET;
  if (!cfg) return;
  const store = new RosterStore();
  const tbody = document.querySelector('[data-net-roster] tbody');
  let since = '';

  function render() {
    if (!tbody) return;
    const rows = store.rows();
    tbody.innerHTML = rows.map((r, i) => `
      <tr data-checkin-id="${r.id ?? ''}">
        <td>${rows.length - i}</td>
        <td class="callsign">${r.callsign ?? ''}</td>
        <td>${r.name ?? ''}</td>
        <td>${r.grid ?? ''}</td>
        <td>${r.signal != null ? 'S' + r.signal : ''}</td>
        <td>${r.role ?? ''}</td>
        <td></td>
      </tr>`).join('');
  }
  function setStats(s) {
    if (!s) return;
    const set = (k, v) => { const el = document.querySelector(`[data-stat="${k}"] [data-stat-value]`); if (el && v != null) el.textContent = v; };
    set('checkins', s.checkins); set('unique', s.unique); set('new', s.new); set('rate', s.rate);
  }

  async function tick() {
    if (document.hidden) return;
    try {
      const res = await fetch(cfg.feedUrl + (since ? ('?since=' + encodeURIComponent(since)) : ''), { headers: { 'Accept': 'application/json' } });
      const json = await res.json();
      since = json.server_time || since;
      (json.checkins || []).forEach(r => store.upsert(r));
      (json.removed || []).forEach(id => store.remove(id));
      render(); setStats(json.stats);
    } catch (_) {}
  }

  // Seed from any server-rendered rows (none for public, but harmless).
  document.querySelectorAll('[data-net-roster] tbody tr[data-checkin-id]').forEach(tr => {
    const id = Number(tr.dataset.checkinId); if (id) store.upsert({ id, callsign: tr.children[1]?.textContent?.trim() });
  });

  const live = cfg.status === 'live';
  tick();
  if (live) {
    const timer = setInterval(tick, 4000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
    window.addEventListener('beforeunload', () => clearInterval(timer));
  }
})();
