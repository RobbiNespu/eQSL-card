/**
 * M5 T22 — Sync engine. Drains the IndexedDB offline queue back to
 * the server when connectivity returns.
 *
 * Triggers:
 *   - `online` window event (browser detected network came back)
 *   - Manual `OfflineSync.drain()` call (status pill retry button)
 *   - On page load if the queue is non-empty (first run after a
 *     reload during an offline session)
 *   - Periodic 60-second timer while the queue has pending rows
 *
 * Algorithm per drain:
 *   1. Read all rows from OfflineQueue.getAll() (oldest-first).
 *   2. For each row, POST to /qsos/quick with `client_uuid` in the
 *      body. Server-side dedup (M5 T21) makes the call idempotent.
 *   3. On 2xx: remove the row from IndexedDB.
 *   4. On 4xx (other than 422 validation): mark error, leave row
 *      for manual retry.
 *   5. On network error / 5xx: mark error, abort the rest of the
 *      drain (preserves chronological order on next attempt).
 *
 * Status changes broadcast via window event 'eqsl-sync-status' so
 * the status pill (T23) can update without polling. Detail shape:
 *   { state: 'idle' | 'syncing' | 'error',
 *     pending: number, syncing: number, lastError: string|null }
 */

const SYNC_ENDPOINT_PATH = '/qsos/quick';
const POLL_INTERVAL_MS = 60_000;
let _pollTimer = null;
let _inFlight = false;
let _lastError = null;

/**
 * Return the app base-path prefix injected by the layout (e.g. '/qsl' for
 * subfolder deploys, '' for root). Used to build absolute fetch URLs.
 * @returns {string}
 */
function getBase() {
    return (typeof window !== 'undefined' && typeof window.EQSL_BASE === 'string')
        ? window.EQSL_BASE
        : '';
}

/**
 * Read the CSRF token via the shared window.eqslCsrf helper.
 * Returns '' when running outside a browser context (SSR/test).
 * @returns {string}
 */
function getCsrf() {
    if (typeof window === 'undefined' || typeof window.eqslCsrf !== 'function') return '';
    return window.eqslCsrf();
}

/**
 * Dispatch an `eqsl-sync-status` CustomEvent on `window` so the sync-status
 * pill Alpine component can update without polling.
 * @param {'idle'|'syncing'|'error'} state
 * @param {number} pending - total rows still in the queue
 * @param {number} syncing - rows currently being processed in this drain pass
 */
function broadcast(state, pending, syncing) {
    if (typeof window === 'undefined') return;
    window.dispatchEvent(new CustomEvent('eqsl-sync-status', {
        detail: {
            state,
            pending,
            syncing,
            lastError: _lastError,
        },
    }));
}

/**
 * Read the current queue count and broadcast a status event. Silently
 * no-ops when IndexedDB is unavailable.
 * @param {'idle'|'syncing'|'error'} state
 * @param {number} [syncing=0]
 */
async function broadcastCurrent(state, syncing = 0) {
    if (!window.OfflineQueue) return;
    try {
        const pending = await window.OfflineQueue.count();
        broadcast(state, pending, syncing);
    } catch (e) { /* indexeddb missing — silently skip */ }
}

/**
 * Try to sync every pending row. Returns a summary object describing
 * what happened. Idempotent if called concurrently — second call
 * returns immediately while the first is in flight.
 */
async function drain() {
    if (_inFlight) {
        return { skipped: true, reason: 'already-syncing' };
    }
    if (!window.OfflineQueue) {
        return { skipped: true, reason: 'no-indexeddb' };
    }
    _inFlight = true;
    let succeeded = 0;
    let failed = 0;
    let aborted = false;
    try {
        const rows = await window.OfflineQueue.getAll();
        if (rows.length === 0) {
            await broadcastCurrent('idle', 0);
            return { skipped: true, reason: 'empty' };
        }
        await broadcastCurrent('syncing', rows.length);

        const url = getBase() + SYNC_ENDPOINT_PATH;
        const csrf = getCsrf();

        for (const row of rows) {
            try {
                const body = new URLSearchParams(row.data);
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept':       'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrf,
                    },
                    body,
                });
                if (resp.status >= 200 && resp.status < 300) {
                    await window.OfflineQueue.remove(row.uuid);
                    succeeded += 1;
                } else if (resp.status === 422) {
                    // Server rejected this specific row's data. No retry
                    // will fix it — keep the row but mark the error so
                    // the operator can manually delete it from the
                    // status-pill UI (T23).
                    const respData = await resp.json().catch(() => null);
                    const errMsg = respData?.errors
                        ? 'Validation failed: ' + JSON.stringify(respData.errors)
                        : `HTTP ${resp.status}`;
                    await window.OfflineQueue.markError(row.uuid, errMsg);
                    _lastError = errMsg;
                    failed += 1;
                } else {
                    // Other HTTP error (auth, 5xx, etc). Probably retryable
                    // but abort the rest of the drain so we don't burn
                    // every queued row on a transient server issue.
                    await window.OfflineQueue.markError(row.uuid, `HTTP ${resp.status}`);
                    _lastError = `HTTP ${resp.status}`;
                    failed += 1;
                    aborted = true;
                    break;
                }
            } catch (e) {
                // Network error during this row. Mark + abort so order
                // is preserved on the next attempt.
                await window.OfflineQueue.markError(row.uuid, String(e.message || e));
                _lastError = String(e.message || e);
                failed += 1;
                aborted = true;
                break;
            }
        }
    } finally {
        _inFlight = false;
    }

    if (failed === 0) _lastError = null;
    await broadcastCurrent(failed > 0 ? 'error' : 'idle', 0);

    return { succeeded, failed, aborted };
}

/**
 * Register all the trigger hooks. Idempotent — safe to call multiple
 * times (the listeners check a registration guard).
 */
let _initialised = false;
async function init() {
    if (_initialised) return;
    if (typeof window === 'undefined') return;
    _initialised = true;

    window.addEventListener('online', () => { drain(); });
    window.addEventListener('eqsl-sync-trigger', () => { drain(); });

    // Periodic poll while the queue is non-empty. Resets each time
    // it ticks; if the queue empties out, the next tick is a no-op
    // and we stop scheduling further ticks until something gets
    // enqueued again (T21's enqueue path calls broadcastCurrent +
    // schedules a tick via 'eqsl-sync-trigger').
    function schedulePoll() {
        if (_pollTimer) clearTimeout(_pollTimer);
        _pollTimer = setTimeout(async () => {
            const pending = await window.OfflineQueue.count();
            if (pending > 0 && navigator.onLine !== false) {
                await drain();
            }
            const remaining = await window.OfflineQueue.count();
            if (remaining > 0) schedulePoll();
        }, POLL_INTERVAL_MS);
    }

    // First-load drain: if any rows survived a page reload, try them now.
    if (navigator.onLine !== false) {
        await drain();
    } else {
        await broadcastCurrent('idle', 0);
    }
    const pending = await window.OfflineQueue.count();
    if (pending > 0) schedulePoll();
}

const OfflineSync = { init, drain };

if (typeof module !== 'undefined' && module.exports) {
    module.exports = OfflineSync;
}
if (typeof window !== 'undefined') {
    window.OfflineSync = OfflineSync;
    window.addEventListener('DOMContentLoaded', () => { init(); });
}
