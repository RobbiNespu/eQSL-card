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
