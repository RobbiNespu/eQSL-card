/**
 * Pure, framework-free in-memory roster store for a net session.
 *
 * Maintains two maps: `_byId` for server-confirmed check-ins (keyed by the
 * server's integer `id`) and `_byTemp` for optimistic rows added by the NCS
 * form before the server has responded (keyed by a client-generated `tempId`
 * string). `reconcile()` promotes a temp row to a confirmed row once the
 * server ACKs it.
 *
 * Imported by net-cockpit.js and net-live.js; unit-tested in isolation.
 */
export class RosterStore {
  constructor() { this._byId = new Map(); this._byTemp = new Map(); }

  /**
   * Insert or update a check-in row. Rows with a numeric `id` go into
   * the confirmed map (fields are merged with any existing entry); rows
   * with only a `tempId` go into the optimistic map.
   * @param {{ id?: number, tempId?: string, [key: string]: any }} row
   */
  upsert(row) {
    if (row.id != null) {
      this._byId.set(row.id, { ...this._byId.get(row.id), ...row });
    } else if (row.tempId != null) {
      this._byTemp.set(row.tempId, row);
    }
  }

  /**
   * Promote an optimistic temp row to a confirmed server row. Removes the
   * temp entry and stores the server-returned row (minus its `tempId` key).
   * @param {string} tempId   - the client-generated temp key to retire
   * @param {{ id: number, [key: string]: any }} serverRow - full row from the server
   */
  reconcile(tempId, serverRow) {
    this._byTemp.delete(tempId);
    const { tempId: _drop, ...clean } = serverRow;
    this._byId.set(serverRow.id, clean);
  }

  /**
   * Remove a row by either its server id or its tempId.
   * @param {number|string} id
   */
  remove(id) { this._byId.delete(id); this._byTemp.delete(id); }

  /**
   * Return all rows sorted newest-first by the `updated` or `at` timestamp.
   * Confirmed rows and optimistic rows are merged into a single array.
   * @returns {object[]}
   */
  rows() {
    const all = [...this._byId.values(), ...this._byTemp.values()];
    return all.sort((a, b) => String(b.updated || b.at || '').localeCompare(String(a.updated || a.at || '')));
  }
}

/**
 * Render a net session roster <tbody> from an array of row objects.
 *
 * Rows are rendered newest-first (the feed returns ordered by datetime
 * desc); the row-number column counts down so the first row is the
 * highest number (total checkins).
 *
 * @param {HTMLElement|null} tbody Target <tbody> to fill.
 * @param {Array<{id?:number,callsign?:string,name?:string,grid?:string,signal?:number,role?:string}>} rows
 */
export function renderRoster(tbody, rows) {
  if (!tbody) return;
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

/**
 * Apply stats from a feed payload to the page's `[data-stat="*"]` chips.
 *
 * @param {{checkins?:number,unique?:number,new?:number,rate?:string|number}|null} stats
 */
export function applyStats(stats) {
  if (!stats) return;
  const set = (k, v) => {
    const el = document.querySelector(`[data-stat="${k}"] [data-stat-value]`);
    if (el && v != null) el.textContent = v;
  };
  set('checkins', stats.checkins);
  set('unique', stats.unique);
  set('new', stats.new);
  set('rate', stats.rate);
}

/**
 * Start the live-poll loop. Fires `onTick` immediately (one initial paint),
 * then on a 4-second interval while `cfg.status === 'live'`. Skips ticks
 * when the document is hidden and re-fires on visibilitychange. Clears the
 * interval on beforeunload.
 *
 * @param {{status:string}} cfg
 * @param {() => Promise<void>|void} onTick
 */
export function startPollLoop(cfg, onTick) {
  let timer = null;
  const fire = async () => { if (!document.hidden) await onTick(); };
  fire();
  if (cfg.status === 'live') {
    timer = setInterval(fire, 4000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) fire(); });
    window.addEventListener('beforeunload', () => clearInterval(timer));
  }
}
