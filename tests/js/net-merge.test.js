import { describe, it, expect } from 'vitest';
import { RosterStore } from '../../webroot/js/net-merge.js';

describe('RosterStore', () => {
  it('inserts newest first', () => {
    const s = new RosterStore();
    s.upsert({ id: 1, callsign: 'A', updated: '2026-05-22T12:00:00Z' });
    s.upsert({ id: 2, callsign: 'B', updated: '2026-05-22T12:01:00Z' });
    expect(s.rows().map(r => r.id)).toEqual([2, 1]);
  });

  it('upsert replaces by id (no duplicates)', () => {
    const s = new RosterStore();
    s.upsert({ id: 1, callsign: 'A', updated: '2026-05-22T12:00:00Z' });
    s.upsert({ id: 1, callsign: 'A2', updated: '2026-05-22T12:05:00Z' });
    expect(s.rows().length).toBe(1);
    expect(s.rows()[0].callsign).toBe('A2');
  });

  it('reconciles an optimistic temp row when the server id arrives', () => {
    const s = new RosterStore();
    s.upsert({ tempId: 't1', callsign: 'A', updated: '2026-05-22T12:00:00Z' });
    s.reconcile('t1', { id: 9, callsign: 'A', updated: '2026-05-22T12:00:01Z' });
    expect(s.rows().length).toBe(1);
    expect(s.rows()[0].id).toBe(9);
    expect(s.rows()[0].tempId).toBeUndefined();
  });

  it('remove deletes by id', () => {
    const s = new RosterStore();
    s.upsert({ id: 1, callsign: 'A', updated: '2026-05-22T12:00:00Z' });
    s.remove(1);
    expect(s.rows().length).toBe(0);
  });
});
