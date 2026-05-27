# M7 — NCS dashboard hardening + backlog cleanup — design spec

**Date:** 2026-05-26
**Status:** Draft, pending owner review.
**Author:** Robbi Nespu (with Claude)
**Milestone:** M7 (targets v1.3.0).

---

## 1. Summary

A stabilization milestone that closes every deferred item from M6 (the NCS
dashboard, shipped in v1.2.0): finishing the live participant map, adding
the missing retention metric, hardening security and live-update fidelity,
and paying down the internal refactors flagged during the M6 audits. No
user-facing regressions; the existing 534 PHPUnit / 119 Vitest suite is the
safety net, extended with new tests for the new behaviour.

Two parts: **A. Functional hardening** (user-visible / security) and
**B. Internal refactors** (no user-visible change).

## 2. Goals

- Make the net dashboard feature-complete: live map on cockpit + public,
  retention streaks, instant live removal of deleted check-ins.
- Harden: rotate invite tokens, POST-only invite-join, conditional
  (ETag/304) polling.
- Reduce duplication and tidy boundaries (DI, shared admin base, shorter
  methods, shared net-JS helpers, one CSRF reader).

## 3. Non-goals

- No external logbook sync (LoTW/eQSL.cc/QRZ) — separate future milestone.
- No new awards/analytics surfaces beyond the streak metric.
- No change to the QSO logbook's hard-delete semantics (see A4).

## 4. Decisions log (from brainstorming)

| # | Decision | Choice |
|---|----------|--------|
| 1 | M7 theme | Harden M6 + clear the deferred backlog. |
| 2 | Scope | Everything — functional hardening AND the internal refactors. |
| 3 | Live-removal mechanism | Dedicated `net_session_removals` tombstone table, NOT soft-deleting `qsos`. |

---

## 5. Part A — Functional hardening

### A1. Live participant map on cockpit + public

Today `net-map.js` + the `[data-net-map]` container only exist on the
analytics page. Bring the map to the live cockpit and the public view.

- The delta feed (`NetSessionsController::checkinsFeed` and
  `NetController::feed`) adds a `map` array to its JSON payload, built from
  `NetMetrics::mapPoints($sessionId)` — `[{callsign, grid, lat, lon, signal}]`.
- `templates/element/net/stat_tiles.php` regains a map container (a single
  `[data-net-map]` with a label), shared by cockpit + public.
- Cockpit (`cockpit.php`) and public (`Net/live.php`) load `net-map.js`
  and the vendored Leaflet CSS/JS.
- `net-map.js` is refactored to (a) render from a `net:updated` event's
  `detail.map` (live) in addition to its current `[data-map-json]`
  (analytics, static) path, and (b) re-render incrementally as points
  arrive. The existing offline list-fallback is preserved.
- Analytics keeps its own dedicated map block (unchanged).

### A2. Retention `longest_streak`

`NetMetrics::retention(ownerId, netTitle, window)` returns an added
`longest_streak` (and `streak_leaders` — the callsign(s) holding it):
the maximum run of consecutive sessions (within the ordered window) a
callsign attended. Surfaced on `templates/NetSessions/analytics.php`.
Pure computation over the existing per-session attendance arrays.

### A3. Logger-token rotation

- Route: `POST /net-sessions/{id}/rotate-token` (owner-only, CSRF).
- `NetSessionsController::rotateToken(int $id)` regenerates
  `logger_token` (`Security::randomString(20)`), saves, flashes the new
  invite link. Outstanding `/net-sessions/join/{old}` links then 404.
- `templates/NetSessions/view.php` gains a "Regenerate invite link"
  button next to the current link.
- AuditLogger `net.session.token_rotated` + `OperationLog::event`.

### A4. Soft-delete live-removal tombstones

So a deleted check-in disappears from other watchers' rosters within one
poll, instead of lingering until a full refresh.

- New table `net_session_removals`: `id`, `net_session_id` (FK), `qso_id`,
  `removed_at` (datetime), index `(net_session_id, removed_at)`.
- When `NetSessionsController::checkin()` DELETE removes a check-in, it
  first writes a removal row (qso_id + now), then deletes the QSO.
- Both feeds (`checkinsFeed`, `NetController::feed`) populate `removed[]`
  from `net_session_removals WHERE net_session_id = ? AND removed_at > since`.
- The clients already drain `removed[]` into `store.remove(id)`, so no
  client change beyond what A1 touches.
- `qsos` hard-delete is unchanged; the tombstone is a thin, net-scoped
  side record (no logbook semantics change). Old tombstones are pruned by
  the existing cleanup tooling (add a sweep of rows older than 7 days).

### A5. ETag / 304 on the polling feeds

- Both feeds compute a weak validator: `W/"<sessionId>-<count>-<maxUpdatedAtEpoch>-<maxRemovedAtEpoch>"`.
- If the request's `If-None-Match` matches, return `304 Not Modified`
  with no body; else return the JSON with the `ETag` header set.
- `net-poll.js` / `net-live.js` send `If-None-Match` (storing the last
  ETag) and already short-circuit on 304 (the dead 304 branch removed in
  the audit is re-introduced intentionally, now that the server emits it).
- Cuts payload on idle polls (the common case).

### A6. GET→POST invite-join (security)

- `/net-sessions/join/{token}` becomes GET = a confirm page (shows the net
  title + a "Join as logger" button) and POST = the actual join (CSRF).
  A link prefetch can no longer silently add the viewer as a co-logger.
- Keeps the auth requirement + token lookup + idempotent membership.

---

## 6. Part B — Internal refactors (behavior-preserving)

### B1. NetMetrics dependency injection
Constructor takes `(QsosTable $qsos, NetSessionsTable $netSessions)`;
`retention()`/`sessionStats()` use the injected table instead of
`TableRegistry::getTableLocator()->get('NetSessions')`. Update the 4
construction sites (logger feed, public feed, analytics, PDF) to pass
`$this->fetchTable('NetSessions')`.

### B2. Shared AdminController base
New abstract `App\Controller\Admin\AdminController extends AppController`
with the role-gate (`beforeFilter` → load identity, 404/redirect if not
admin, set `$actorId`). The ~10 admin controllers extend it and drop their
duplicated gate. Behaviour identical; verified by the existing admin
controller tests.

### B3. Split long methods
- `TemplatesController::saveTemplate()` → `validateTemplateInput()`,
  `applyBackgroundBinding()`, `renderThumbnailIfPossible()`.
- `QsosController::renderQsoCard()` → `fetchRenderDependencies()` +
  `writeCardRow()`.
No behaviour change; existing render/template tests guard them.

### B4. Net-JS dedup
Extract into `net-merge.js` (already the shared module):
`renderRoster(tbody, rows)`, `applyStats(stats)`, and
`startPollLoop({feedUrl, getSince, onData, isLive})`. `net-cockpit.js`,
`net-poll.js`, `net-live.js` consume these, removing 3 duplicated
`render()`/`setStats()`/`tick()` copies. Re-QA cockpit + public live.

### B5. Unified CSRF reader
`readCsrfToken()` (published as `window.eqslCsrf`) — reads the
`meta[name=csrf-token]`, falls back to the cookie, with one consistent
decode rule. Replace the 5 inline copies in app.js (bulk-render, quick-add),
net-cockpit.js, designer.js, offline-sync.js.

---

## 7. Data model changes

One new table: `net_session_removals` (A4). No other schema changes.
`qsos`, `net_sessions`, `net_session_loggers` unchanged.

## 8. Testing strategy

- **PHP unit:** `NetMetrics::retention` longest_streak; tombstone write on
  delete; feed `removed[]` since-cursor; ETag validator + 304 path.
- **PHP integration:** rotate-token (old join token 404s, new works);
  join GET renders confirm + POST joins + GET no longer mutates; feed
  returns `map` + `removed[]`; admin base-class gate still enforces 404
  for non-admins across the admin controllers.
- **JS (Vitest):** the extracted `renderRoster`/`applyStats` pure-ish
  helpers; CSRF reader; existing net-merge tests stay green.
- **Visual QA:** cockpit + public live map renders + updates; live removal
  disappears within a poll; analytics streak shows; both themes, 375px.
- Full PHPUnit + Vitest green; reproducible `dist.css` (no CSS change
  expected beyond a possible map-container tweak).

## 9. Component / file map

**Migrations:** `config/Migrations/20260526000001_CreateNetSessionRemovals.php`.
**Model:** `NetSessionRemoval` entity + `NetSessionRemovalsTable`; `Qso`/net
tables unchanged.
**Services:** `NetMetrics` (DI + longest_streak).
**Controllers:** `NetSessionsController` (rotateToken, checkin-delete writes
tombstone, feed map+removed+ETag), `NetController` (feed map+removed+ETag),
new abstract `Admin/AdminController` + the admin controllers reparented,
`TemplatesController`/`QsosController` method splits, `CleanupController`
tombstone sweep.
**Views:** `element/net/stat_tiles.php` (map container back), `cockpit.php`
+ `Net/live.php` (load map + Leaflet), `NetSessions/view.php` (rotate
button), `NetSessions/analytics.php` (streak).
**JS:** `net-merge.js` (+renderRoster/applyStats/startPollLoop),
`net-cockpit.js`/`net-poll.js`/`net-live.js` (consume shared), `net-map.js`
(live render via net:updated), `app.js`/`designer.js`/`offline-sync.js`
(use readCsrfToken).
**Routes:** rotate-token (POST), join (GET confirm + POST).

## 10. Rollout

Phased, in dependency order, one PR (or split A/B if preferred):
1. A4 data layer (tombstone table + delete writes it + feed `removed[]`).
2. A1 map (feed `map` + net-map live + cockpit/public wiring).
3. A2 streak, A3 token rotation, A5 ETag, A6 POST-join.
4. B1–B5 refactors (each behavior-preserving, suite green after each).
5. Help docs touch-ups + visual QA + version bump (v1.3.0).

## 11. Open items to revisit on revamp

- ETag granularity (per-session validator is coarse but cheap; fine for
  the 4 s poll).
- Tombstone retention window (7 days) — tune if needed.
- Whether the public view should expose the map at all for very large
  nets (privacy of participant locations is already public callsign+grid).
