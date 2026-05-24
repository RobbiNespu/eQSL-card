/**
 * Net-session cockpit — NCS (Net Control Station) entry and live roster.
 *
 * Wires the check-in entry form (`data-net-entry`) to the server check-in
 * endpoint (`window.NET.postUrl`). Optimistically adds a temp row to the
 * roster on submit, then reconciles with the server response (or removes
 * the temp row on failure). Shares `window.__netStore` with net-poll.js
 * so the polling tick and the form submission both write to the same
 * RosterStore instance.
 *
 * Requires `window.NET = { postUrl, feedUrl, status }` to be set by the
 * server-rendered page before this module executes.
 */
import { RosterStore } from './net-merge.js';

(function () {
  const cfg = window.NET;
  if (!cfg) return;
  const store = new RosterStore();
  const form = document.querySelector('[data-net-entry]');
  const tbody = document.querySelector('[data-net-roster] tbody');

  /** @returns {string} current CSRF token from the page meta tag */
  function csrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  /** Re-render the roster tbody from the in-memory RosterStore, newest first. */
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

  // Seed from server-rendered rows so a refresh keeps state.
  document.querySelectorAll('[data-net-roster] tbody tr[data-checkin-id]').forEach(tr => {
    const id = Number(tr.dataset.checkinId);
    if (id) store.upsert({ id, callsign: tr.children[1]?.textContent?.trim() });
  });

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(form).entries());
      const tempId = 't' + Date.now();
      store.upsert({ tempId, callsign: (data.call_worked || '').toUpperCase(), name: data.operator_name, grid: data.grid_square, role: data.net_role, updated: new Date().toISOString() });
      render();
      form.reset();
      const rstField = form.querySelector('[name="rst_received"]'); if (rstField && !rstField.value) rstField.value = '59';
      form.querySelector('[name="call_worked"]')?.focus();
      try {
        const res = await fetch(cfg.postUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
          body: JSON.stringify(data),
        });
        const json = await res.json();
        if (json.ok) store.reconcile(tempId, json.checkin);
        else store.remove(tempId);
      } catch (err) {
        console.error('[net-cockpit] check-in POST failed', err);
        store.remove(tempId);
      }
      render();
    });
  }

  render();
  window.__netStore = store; // shared with net-poll.js (Task 15)
  document.addEventListener('net:updated', (e) => {
    render();
    const s = e.detail && e.detail.stats;
    if (s) {
      const set = (k, v) => { const el = document.querySelector(`[data-stat="${k}"] [data-stat-value]`); if (el && v != null) el.textContent = v; };
      set('checkins', s.checkins);
      set('unique', s.unique);
      set('new', s.new);
      set('rate', s.rate);
    }
  });
})();
