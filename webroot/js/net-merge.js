// Pure, framework-free roster store. Imported by net-cockpit.js / net-live.js
// and unit-tested in isolation.
export class RosterStore {
  constructor() { this._byId = new Map(); this._byTemp = new Map(); }

  upsert(row) {
    if (row.id != null) {
      this._byId.set(row.id, { ...this._byId.get(row.id), ...row });
    } else if (row.tempId != null) {
      this._byTemp.set(row.tempId, row);
    }
  }

  reconcile(tempId, serverRow) {
    this._byTemp.delete(tempId);
    const { tempId: _drop, ...clean } = serverRow;
    this._byId.set(serverRow.id, clean);
  }

  remove(id) { this._byId.delete(id); this._byTemp.delete(id); }

  rows() {
    const all = [...this._byId.values(), ...this._byTemp.values()];
    return all.sort((a, b) => String(b.updated || b.at || '').localeCompare(String(a.updated || a.at || '')));
  }
}
