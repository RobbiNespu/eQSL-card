/**
 * M5 T20 — Offline QSO queue, IndexedDB-backed.
 *
 * One DB ("eqsl-card-offline") with one object store ("qsos") keyed
 * by client-generated UUID. Schema:
 *
 *   uuid          string PK (client-generated v4 UUID)
 *   data          object  — the quick-add form payload, ready to POST
 *   queued_at     number  — epoch ms when the user tapped Save
 *   pending_sync  boolean — true while waiting for server to ACK
 *   retry_count   number  — bumped on each failed sync attempt
 *   last_error    string  — message from the last failed attempt (null on success)
 *
 * Why client-UUID PK rather than auto-increment: the same UUID must
 * survive across browser sessions, get sent to the server during
 * sync, and let the server dedup on retries. The server's
 * qsos.client_uuid column (M5 T21) is uniquely indexed per user.
 *
 * Pure module. No DOM, no Alpine. The Alpine component in app.js
 * imports it via the global `window.OfflineQueue` set at bottom.
 *
 * Tested under Vitest with fake-indexeddb. Browsers without
 * IndexedDB (very old Safari, IE) get a stub that always reports
 * the queue as empty — offline logging won't work but the rest of
 * the app does.
 */

const DB_NAME = 'eqsl-card-offline';
const DB_VERSION = 1;
const STORE = 'qsos';

/**
 * Monotonic-increment guard for queued_at. Date.now() returns
 * millisecond resolution; two rapid-fire enqueue() calls can produce
 * the same timestamp (especially on fast CI hosts), and IndexedDB
 * cursors walk tied keys in secondary-index (UUID, random) order —
 * so the drain would receive rows out of insertion order.
 * _nextQueuedAt() ensures every assigned timestamp is strictly
 * greater than the previous one, even if the wall clock hasn't ticked.
 */
let _lastQueuedAt = 0;
/**
 * Return a strictly-increasing timestamp for `queued_at`. Guarantees
 * chronological drain order even when two enqueue() calls land within
 * the same millisecond.
 * @returns {number} epoch milliseconds, always > previous call's result
 */
function _nextQueuedAt() {
    const now = Date.now();
    _lastQueuedAt = (now > _lastQueuedAt) ? now : (_lastQueuedAt + 1);
    return _lastQueuedAt;
}

/**
 * @returns {boolean} true if the IndexedDB API is available in this context
 */
function hasIndexedDb() {
    return typeof indexedDB !== 'undefined';
}

/** Generate a v4 UUID using crypto.randomUUID where available, else manual. */
function generateUuid() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    // Fallback: manual v4 (RFC 4122 §4.4) for older Safari that has
    // crypto.getRandomValues but not randomUUID.
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;  // version 4
    bytes[8] = (bytes[8] & 0x3f) | 0x80;  // variant 10
    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/**
 * Open (or create) the offline IndexedDB database. Creates the `qsos`
 * object store with a `queued_at` secondary index on first use.
 * @returns {Promise<IDBDatabase>}
 */
function openDb() {
    return new Promise((resolve, reject) => {
        if (!hasIndexedDb()) {
            reject(new Error('IndexedDB not available'));
            return;
        }
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE)) {
                const store = db.createObjectStore(STORE, { keyPath: 'uuid' });
                // Secondary index for chronological draining during sync.
                store.createIndex('queued_at', 'queued_at', { unique: false });
            }
        };
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror = (e) => reject(e.target.error);
    });
}

/**
 * Open the DB, run a synchronous callback `fn(store)` inside a transaction,
 * and resolve with the callback's return value when the transaction commits.
 * @param {'readonly'|'readwrite'} mode
 * @param {function(IDBObjectStore): any} fn - synchronous store callback
 * @returns {Promise<any>}
 */
async function withStore(mode, fn) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, mode);
        const store = tx.objectStore(STORE);
        let result;
        try {
            result = fn(store);
        } catch (e) {
            db.close();
            reject(e);
            return;
        }
        tx.oncomplete = () => {
            db.close();
            resolve(result);
        };
        tx.onerror = () => {
            db.close();
            reject(tx.error);
        };
        tx.onabort = () => {
            db.close();
            reject(tx.error);
        };
    });
}

/**
 * Enqueue a QSO for later sync. Returns the assigned UUID so the
 * caller can render it immediately (with a "queued" marker) without
 * a round-trip.
 */
async function enqueue(data) {
    const uuid = generateUuid();
    const row = {
        uuid,
        data: { ...data, client_uuid: uuid },
        queued_at: _nextQueuedAt(),
        pending_sync: true,
        retry_count: 0,
        last_error: null,
    };
    await withStore('readwrite', (store) => {
        store.add(row);
    });
    return row;
}

/**
 * Fetch every queued row from IndexedDB, ordered oldest-first by `queued_at`.
 * @returns {Promise<object[]>}
 */
async function getAll() {
    return withStore('readonly', (store) => {
        return new Promise((resolve, reject) => {
            const rows = [];
            const idx = store.index('queued_at');
            const req = idx.openCursor();
            req.onsuccess = (e) => {
                const cursor = e.target.result;
                if (cursor) {
                    rows.push(cursor.value);
                    cursor.continue();
                } else {
                    resolve(rows);
                }
            };
            req.onerror = () => reject(req.error);
        });
    });
}

/**
 * Return the total number of rows currently in the queue.
 * @returns {Promise<number>}
 */
async function count() {
    return withStore('readonly', (store) => {
        return new Promise((resolve, reject) => {
            const req = store.count();
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    });
}

/**
 * Delete a queued row by its UUID.
 * @param {string} uuid - the client-generated UUID assigned at enqueue time
 * @returns {Promise<void>}
 */
async function remove(uuid) {
    return withStore('readwrite', (store) => {
        store.delete(uuid);
    });
}

/**
 * Increment `retry_count` and record the error message on a row that failed
 * to sync. The row stays in the queue for manual retry or future drain.
 * @param {string} uuid         - row UUID
 * @param {string} errorMessage - human-readable error from the failed attempt
 * @returns {Promise<void>}
 */
async function markError(uuid, errorMessage) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        const store = tx.objectStore(STORE);
        const getReq = store.get(uuid);
        getReq.onsuccess = () => {
            const row = getReq.result;
            if (!row) {
                tx.abort();
                return;
            }
            row.retry_count = (row.retry_count || 0) + 1;
            row.last_error = String(errorMessage || 'unknown');
            store.put(row);
        };
        tx.oncomplete = () => { db.close(); resolve(); };
        tx.onerror = () => { db.close(); reject(tx.error); };
        tx.onabort = () => { db.close(); resolve(); };  // row missing is fine
    });
}

/** Drop every queued row. Used by "Clear all" affordance + reset for tests. */
async function clear() {
    return withStore('readwrite', (store) => {
        store.clear();
    });
}

const OfflineQueue = {
    enqueue, getAll, count, remove, markError, clear, generateUuid,
    hasIndexedDb,
};

if (typeof module !== 'undefined' && module.exports) {
    module.exports = OfflineQueue;
}
if (typeof window !== 'undefined') {
    window.OfflineQueue = OfflineQueue;
}
