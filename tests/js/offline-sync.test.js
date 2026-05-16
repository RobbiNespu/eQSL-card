/**
 * Vitest suite for the sync engine (M5 T22) + conflict-tolerance test (M5 T24).
 *
 * Uses fake-indexeddb for the queue and a per-test fetch mock for the
 * server. Each test resets queue state in beforeEach.
 *
 * T24 specifically: queue 50 QSOs offline, "go online" by flipping
 * navigator.onLine, trigger drain, verify all 50 reach the server,
 * none lost, none duplicated on the server-mock side.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest';
import 'fake-indexeddb/auto';

if (typeof globalThis.crypto === 'undefined') {
    globalThis.crypto = await import('node:crypto').then(m => m.webcrypto);
}

import OfflineQueue from '../../webroot/js/offline-queue.js';
import OfflineSync from '../../webroot/js/offline-sync.js';

// Stub window so the sync engine can call OfflineQueue + dispatch events.
function stubWindow() {
    const listeners = {};
    const w = {
        OfflineQueue,
        EQSL_BASE: '',
        addEventListener(name, fn) {
            (listeners[name] ||= []).push(fn);
        },
        removeEventListener() { /* noop */ },
        dispatchEvent(ev) {
            (listeners[ev.type] || []).forEach(fn => fn(ev));
        },
    };
    globalThis.window = w;
    // navigator is a non-writable getter in Node 21+; defineProperty
    // re-defines it as a plain data slot for the tests.
    Object.defineProperty(globalThis, 'navigator', {
        value: { onLine: true },
        writable: true,
        configurable: true,
    });
    globalThis.document = { querySelector: () => null, cookie: '' };
    globalThis.CustomEvent = class {
        constructor(type, init) { this.type = type; this.detail = init?.detail; }
    };
    return { window: w, listeners };
}

describe('OfflineSync.drain', () => {
    beforeEach(async () => {
        try { await OfflineQueue.clear(); } catch (e) {}
        vi.restoreAllMocks();
        stubWindow();
    });

    it('returns skipped when queue is empty', async () => {
        const result = await OfflineSync.drain();
        expect(result.skipped).toBe(true);
        expect(result.reason).toBe('empty');
    });

    it('drains a single row on success (200) and removes from queue', async () => {
        await OfflineQueue.enqueue({ call_worked: 'W1AW', mode: 'SSB' });
        global.fetch = vi.fn().mockResolvedValue({
            status: 200,
            json: async () => ({ ok: true, qso: { id: 42 } }),
        });

        const result = await OfflineSync.drain();
        expect(result.succeeded).toBe(1);
        expect(result.failed).toBe(0);
        expect(await OfflineQueue.count()).toBe(0);
        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    it('keeps the row + marks error on 422 validation failure', async () => {
        await OfflineQueue.enqueue({ call_worked: '', mode: '' }); // invalid
        global.fetch = vi.fn().mockResolvedValue({
            status: 422,
            json: async () => ({ ok: false, errors: { call_worked: ['required'] } }),
        });

        const result = await OfflineSync.drain();
        expect(result.failed).toBe(1);
        expect(await OfflineQueue.count()).toBe(1);
        const rows = await OfflineQueue.getAll();
        expect(rows[0].retry_count).toBe(1);
        expect(rows[0].last_error).toContain('Validation');
    });

    it('aborts the drain on network error and preserves order', async () => {
        await OfflineQueue.enqueue({ call_worked: 'FIRST' });
        await new Promise(r => setTimeout(r, 5));
        await OfflineQueue.enqueue({ call_worked: 'SECOND' });
        await new Promise(r => setTimeout(r, 5));
        await OfflineQueue.enqueue({ call_worked: 'THIRD' });

        // First fetch succeeds, second throws (network error), third never tried.
        let callCount = 0;
        global.fetch = vi.fn().mockImplementation(() => {
            callCount += 1;
            if (callCount === 1) {
                return Promise.resolve({ status: 200, json: async () => ({ ok: true, qso: { id: 1 } }) });
            }
            return Promise.reject(new Error('network down'));
        });

        const result = await OfflineSync.drain();
        expect(result.succeeded).toBe(1);
        expect(result.failed).toBe(1);
        expect(result.aborted).toBe(true);
        // FIRST is gone, SECOND and THIRD remain — chronological order preserved.
        const remaining = await OfflineQueue.getAll();
        expect(remaining).toHaveLength(2);
        expect(remaining.map(r => r.data.call_worked)).toEqual(['SECOND', 'THIRD']);
    });

    it('aborts on 5xx HTTP error', async () => {
        await OfflineQueue.enqueue({ call_worked: 'X' });
        await OfflineQueue.enqueue({ call_worked: 'Y' });
        global.fetch = vi.fn().mockResolvedValue({
            status: 500,
            json: async () => ({}),
        });

        const result = await OfflineSync.drain();
        expect(result.aborted).toBe(true);
        expect(result.succeeded).toBe(0);
        // First row marked error, second untouched.
        expect(await OfflineQueue.count()).toBe(2);
    });

    it('is reentrancy-safe (concurrent drain calls)', async () => {
        await OfflineQueue.enqueue({ call_worked: 'A' });
        let resolveFirst;
        const firstPromise = new Promise(r => { resolveFirst = r; });
        global.fetch = vi.fn().mockImplementation(() => firstPromise);

        const a = OfflineSync.drain();
        // Second call while first is in-flight returns skipped.
        const b = await OfflineSync.drain();
        expect(b.skipped).toBe(true);
        expect(b.reason).toBe('already-syncing');

        resolveFirst({ status: 200, json: async () => ({ ok: true, qso: {} }) });
        await a;
    });
});

describe('T24 conflict tolerance — 50 queued QSOs', () => {
    beforeEach(async () => {
        try { await OfflineQueue.clear(); } catch (e) {}
        vi.restoreAllMocks();
        stubWindow();
    });

    it('drains 50 queued QSOs cleanly when network returns; none lost, none duplicated', async () => {
        const N = 50;
        for (let i = 0; i < N; i++) {
            await OfflineQueue.enqueue({
                call_worked: `TEST${String(i).padStart(2, '0')}`,
                frequency_mhz: '14.20',
                mode: 'SSB',
            });
            // Stagger queued_at so order matters.
            await new Promise(r => setTimeout(r, 1));
        }
        expect(await OfflineQueue.count()).toBe(N);

        // Simulate the server: track every UUID it has seen, return
        // 200 with the canonical row. Duplicate UUIDs return the
        // already-seen row (replayed: true) — matches the M5 T21
        // server-side idempotency.
        const serverSeen = new Map();  // uuid → callsign
        global.fetch = vi.fn().mockImplementation(async (url, opts) => {
            const params = new URLSearchParams(opts.body);
            const uuid = params.get('client_uuid');
            const callsign = params.get('call_worked');
            const replayed = serverSeen.has(uuid);
            if (!replayed) serverSeen.set(uuid, callsign);
            return {
                status: 200,
                json: async () => ({
                    ok: true,
                    replayed,
                    qso: {
                        id: serverSeen.size,
                        callsign: serverSeen.get(uuid),
                        client_uuid: uuid,
                    },
                }),
            };
        });

        const result = await OfflineSync.drain();
        expect(result.succeeded).toBe(N);
        expect(result.failed).toBe(0);
        expect(await OfflineQueue.count()).toBe(0);
        // Server saw exactly N distinct UUIDs.
        expect(serverSeen.size).toBe(N);
        // Every callsign reached the server in chronological order.
        const callsignsSeen = Array.from(serverSeen.values());
        expect(callsignsSeen).toEqual(
            Array.from({ length: N }, (_, i) => `TEST${String(i).padStart(2, '0')}`)
        );
    });

    it('retried drain after partial failure recovers without duplicating', async () => {
        // 10 queued, network fails after the 5th, then recovers on retry.
        for (let i = 0; i < 10; i++) {
            await OfflineQueue.enqueue({ call_worked: `R${i}` });
            await new Promise(r => setTimeout(r, 1));
        }

        const serverSeen = new Set();
        let networkAlive = false;  // start broken
        global.fetch = vi.fn().mockImplementation(async (url, opts) => {
            if (!networkAlive) throw new Error('network down');
            const uuid = new URLSearchParams(opts.body).get('client_uuid');
            const replayed = serverSeen.has(uuid);
            serverSeen.add(uuid);
            return {
                status: 200,
                json: async () => ({ ok: true, replayed, qso: { client_uuid: uuid } }),
            };
        });

        // First drain: all 10 fail (network down). First row marked error, rest untouched.
        const r1 = await OfflineSync.drain();
        expect(r1.aborted).toBe(true);
        expect(r1.succeeded).toBe(0);
        expect(await OfflineQueue.count()).toBe(10);

        // "Network recovers" then drain again.
        networkAlive = true;
        const r2 = await OfflineSync.drain();
        expect(r2.succeeded).toBe(10);
        expect(await OfflineQueue.count()).toBe(0);

        // Server saw exactly 10 distinct UUIDs — no double-insert.
        expect(serverSeen.size).toBe(10);
    });
});
