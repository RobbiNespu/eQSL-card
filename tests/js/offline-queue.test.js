/**
 * Vitest suite for the IndexedDB offline queue (M5 T20).
 *
 * Uses fake-indexeddb to swap a Node-native IndexedDB into globalThis
 * before importing the module. The polyfill behaves identically to
 * the browser's IndexedDB at the API level.
 */
import { describe, it, expect, beforeEach } from 'vitest';
import 'fake-indexeddb/auto';
// Also need to fake crypto.randomUUID for the UUID generator.
if (typeof globalThis.crypto === 'undefined') {
    globalThis.crypto = await import('node:crypto').then(m => m.webcrypto);
}

// Import after the polyfill is registered.
import OfflineQueue from '../../webroot/js/offline-queue.js';

describe('OfflineQueue', () => {
    beforeEach(async () => {
        // Reset the DB between tests so state from one doesn't leak.
        try {
            await OfflineQueue.clear();
        } catch (e) {
            // First test may have no DB yet — fine.
        }
    });

    describe('generateUuid', () => {
        it('returns a v4 UUID-shaped string', () => {
            const id = OfflineQueue.generateUuid();
            expect(id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
        });

        it('produces unique values across many calls', () => {
            const set = new Set();
            for (let i = 0; i < 100; i++) set.add(OfflineQueue.generateUuid());
            expect(set.size).toBe(100);
        });
    });

    describe('enqueue + getAll + count', () => {
        it('starts empty', async () => {
            expect(await OfflineQueue.count()).toBe(0);
            expect(await OfflineQueue.getAll()).toEqual([]);
        });

        it('assigns a UUID and stamps queued_at on enqueue', async () => {
            const row = await OfflineQueue.enqueue({
                call_worked: 'W1AW', mode: 'SSB',
            });
            expect(row.uuid).toMatch(/^[0-9a-f-]{36}$/i);
            expect(row.queued_at).toBeTypeOf('number');
            expect(row.pending_sync).toBe(true);
            expect(row.retry_count).toBe(0);
            expect(row.last_error).toBeNull();
            // Original data is preserved AND client_uuid is added.
            expect(row.data.call_worked).toBe('W1AW');
            expect(row.data.client_uuid).toBe(row.uuid);
        });

        it('count + getAll reflect enqueued rows', async () => {
            await OfflineQueue.enqueue({ call_worked: 'A1A' });
            await OfflineQueue.enqueue({ call_worked: 'B1B' });
            expect(await OfflineQueue.count()).toBe(2);
            const rows = await OfflineQueue.getAll();
            expect(rows).toHaveLength(2);
        });

        it('getAll returns rows oldest-first by queued_at', async () => {
            // No setTimeout — enqueue() uses _nextQueuedAt() which
            // guarantees monotonic timestamps even when called in
            // rapid succession. Was previously brittle on fast CI
            // where consecutive Date.now() calls returned identical
            // values and IndexedDB walked tied keys in random
            // (UUID) order, causing the assertion to flake.
            await OfflineQueue.enqueue({ call_worked: 'OLDEST' });
            await OfflineQueue.enqueue({ call_worked: 'MIDDLE' });
            await OfflineQueue.enqueue({ call_worked: 'NEWEST' });

            const rows = await OfflineQueue.getAll();
            expect(rows.map(r => r.data.call_worked)).toEqual(['OLDEST', 'MIDDLE', 'NEWEST']);
        });
    });

    describe('remove', () => {
        it('removes a row by UUID', async () => {
            const row = await OfflineQueue.enqueue({ call_worked: 'TEMP' });
            expect(await OfflineQueue.count()).toBe(1);
            await OfflineQueue.remove(row.uuid);
            expect(await OfflineQueue.count()).toBe(0);
        });

        it('is a no-op for missing UUIDs', async () => {
            await expect(OfflineQueue.remove('not-a-real-uuid')).resolves.not.toThrow();
        });
    });

    describe('markError', () => {
        it('increments retry_count and stores the message', async () => {
            const row = await OfflineQueue.enqueue({ call_worked: 'FAILER' });
            await OfflineQueue.markError(row.uuid, 'HTTP 500');
            const rows = await OfflineQueue.getAll();
            expect(rows).toHaveLength(1);
            expect(rows[0].retry_count).toBe(1);
            expect(rows[0].last_error).toBe('HTTP 500');
        });

        it('accumulates retry_count across multiple calls', async () => {
            const row = await OfflineQueue.enqueue({ call_worked: 'FAIL' });
            await OfflineQueue.markError(row.uuid, 'attempt 1');
            await OfflineQueue.markError(row.uuid, 'attempt 2');
            await OfflineQueue.markError(row.uuid, 'attempt 3');
            const rows = await OfflineQueue.getAll();
            expect(rows[0].retry_count).toBe(3);
            expect(rows[0].last_error).toBe('attempt 3');
        });
    });

    describe('clear', () => {
        it('drops every row', async () => {
            for (let i = 0; i < 5; i++) {
                await OfflineQueue.enqueue({ call_worked: 'X' + i });
            }
            expect(await OfflineQueue.count()).toBe(5);
            await OfflineQueue.clear();
            expect(await OfflineQueue.count()).toBe(0);
        });
    });
});
