# NCS Dashboard — design spec

**Date:** 2026-05-22
**Status:** Draft, pending owner review (finalized unattended; revamp expected).
**Author:** Robbi Nespu (with Claude)
**Milestone:** proposed M6.

---

## 1. Summary

A web-based **Net Control Station (NCS) dashboard** for running amateur-radio nets: an operational cockpit for logging check-ins in real time, watching a net fill live, and reviewing performance afterward. It extends the existing net-check-in support (each check-in is already a `qso_type='net'` QSO) with a first-class **net session** entity, a collaborative live cockpit, a public read-only live view, three analytics tools (signal-strength distribution, geospatial participant map, participation/retention metrics), and per-session PDF + ADIF export.

The system targets the same deployment envelope as the rest of eQSL Card: PHP 8.1 + CakePHP 5 + MariaDB on modest/shared hosting, offline-tolerant, no persistent daemons.

## 2. Goals

- Let an NCS create, run (start → end), and review a net session.
- Log check-ins fast, keyboard-first, with returning-operator awareness — reusing the quick-add / dupe-check ergonomics already in the app.
- Allow **collaborative logging**: the owner plus invited co-loggers writing into the same live session.
- Offer a **public read-only live view** (shareable link, no login) so participants/club can watch the roster and stats fill in.
- Provide live + post-net analytics: signal-strength distribution, participant map, participation & retention.
- Export a net session to **ADIF** (reusing the existing exporter) and to a formatted **PDF report**.
- Keep cards + existing ADIF flows working unchanged (check-ins are normal QSOs).

## 3. Non-goals (YAGNI)

- No WebSocket/SSE infrastructure or persistent daemon (see §7).
- No in-app voice/audio, no rig control, no spotting-network integration.
- No public *write* access — the public view is strictly read-only.
- No cross-club global leaderboard; retention metrics are scoped to the owner's own nets.
- No scheduled-net automation (reminders, auto-start). `scheduled` is a manual pre-start state only.
- No realtime presence/cursors for co-loggers beyond seeing each other's logged rows.

## 4. Decisions log (resolved during brainstorming)

| # | Decision | Choice |
|---|----------|--------|
| 1 | Spec scope | Everything in one spec: live cockpit + all analytics + both exports. |
| 2 | Operating model | Collaborative loggers **and** public live view. |
| 3 | Check-in ↔ logbook | Each check-in is also a QSO row (`qso_type='net'`, linked to the session). |
| 4 | Signal-strength source | Derive S1–S9 from the strength digit of `rst_received`; no new field. |
| 5 | Real-time mechanism | Short polling with delta responses (`?since` cursor). |
| 6 | Pre-start state | Keep `scheduled` → `live` → `ended`. |
| 7 | Co-loggers | Owner adds registered users **and** a per-session invite link for self-join; membership in `net_session_loggers`. |

## 5. Data model

### 5.1 New table: `net_sessions`

Mirrors the proven `activations` session pattern.

| Column | Type | Notes |
|--------|------|-------|
| `id` | int PK | |
| `owner_id` | int FK → users | The NCS who created the session. Server-set. |
| `net_title` | varchar(120) | e.g. "MARTS Daily Net". |
| `net_organisation` | varchar(120) null | e.g. "MARTS". |
| `frequency_mhz` | decimal(10,5) null | Net frequency. |
| `band` | varchar(8) null | Auto-derivable from frequency. |
| `mode` | varchar(20) null | Default SSB. |
| `status` | varchar(12) | `scheduled` \| `live` \| `ended`. |
| `public_slug` | varchar(40) | Random, unique; powers the public read-only URL. |
| `is_public` | tinyint(1) | Default 1. Toggle the public view on/off. |
| `logger_token` | varchar(40) null | Random; powers the co-logger self-join invite link. |
| `started_at` | datetime null | Set when status → live. |
| `ended_at` | datetime null | Set when status → ended. |
| `notes` | text null | |
| `created_at` / `updated_at` | datetime | |

Indexes: `owner_id`, `status`, unique `public_slug`, `net_title` (for cross-session retention queries).

### 5.2 New table: `net_session_loggers` (join)

| Column | Type | Notes |
|--------|------|-------|
| `id` | int PK | |
| `net_session_id` | int FK → net_sessions | |
| `user_id` | int FK → users | A co-logger. |
| `added_via` | varchar(10) | `owner` \| `invite`. |
| `created_at` | datetime | |

Unique on (`net_session_id`, `user_id`). The owner is implicitly a logger and is **not** stored here.

### 5.3 `qsos` change

Add one nullable column: `net_session_id` int FK → net_sessions, indexed — exactly parallel to the existing `activation_id`. Each check-in is a QSO row with:

- `qso_type='net'`, `net_session_id` set, `ncs_callsign`/`net_title`/`net_organisation` copied from the session at insert time (so historic rows stay correct if the session is later edited),
- `call_worked` = participant callsign, `operator_name`, `grid_square`, `rst_received` (strength digit drives the chart), `rst_sent`, `qso_datetime_utc` = check-in time, `notes`,
- `user_id` = the **owner** (so the net's QSOs land in the NCS's logbook regardless of which co-logger entered them); a co-logger's identity is tracked separately (see §5.4),
- check-in **sequence** is derived by ordering on `qso_datetime_utc, id` within the session — no stored sequence column.

### 5.4 "Logged by" attribution

To show which co-logger entered a row (cockpit "By" column) without changing `qsos` ownership, add a nullable `logged_by_user_id` int column to `qsos` (null for non-net rows). Small, additive, and keeps `user_id` = net owner for logbook/card semantics.

### 5.5 "Role" of a check-in

Net rows carry a role: `NCS` | `Relay` | `Check-in` | `Traffic`. Stored in a new nullable `net_role` varchar(12) on `qsos` (null for contacts). Kept on `qsos` rather than a lookup table — fixed small enum, queried alongside the row.

> Net-specific additive columns on `qsos`: `net_session_id`, `logged_by_user_id`, `net_role`. All nullable, all ignored by contact-mode rows.

## 6. Session lifecycle

```
scheduled ──Start──▶ live ──End──▶ ended
   │                  │
   └── edit/delete    └── log check-ins (owner + co-loggers)
```

- **scheduled** — created ahead of time; appears in an "Upcoming nets" list. Editable, deletable. No check-ins yet.
- **live** — `started_at` stamped. Check-in entry enabled for owner + co-loggers. Public view active (if `is_public`). The cockpit and public view poll for deltas.
- **ended** — `ended_at` stamped. Entry disabled; everything becomes read-only/review. Analytics and exports available. Polling stops (one final fetch).

Transitions are owner-only POST actions guarded by CSRF + auth. Re-opening an ended net is out of scope (create a new session).

## 7. Real-time architecture (short polling + deltas)

Chosen over SSE/WebSocket because the public view can have many concurrent viewers and SSE would pin one PHP-FPM worker per viewer — unviable on shared hosting. Polling reuses the app's existing offline-sync polling idiom.

### 7.1 Endpoints

- **Authenticated logger feed:** `GET /net-sessions/{id}/checkins.json?since={cursor}`
  Returns `{ server_time, status, stats, checkins:[…], removed:[ids] }` where `checkins` includes only rows with `updated_at > since` (and `removed` lists deletions since the cursor). `since` is the prior response's `server_time` (server clock, avoids skew).
- **Public feed:** `GET /net/{public_slug}/live.json?since={cursor}`
  Same shape, read-only, only served when `is_public` and status ≠ scheduled; exposes only public-safe fields (callsign, name, grid, signal bucket, role, seq, time — no `logged_by`, no internal IDs beyond row id).
- **Write (log a check-in):** `POST /net-sessions/{id}/checkins.json` (owner/co-logger). Creates the QSO row; returns the canonical row. Reuses the existing dupe-check service to flag a callsign already checked in this session (warning, not a hard block — duplicates are sometimes legitimate, e.g. recheck).
- **Edit/delete check-in:** `PUT`/`DELETE /net-sessions/{id}/checkins/{qsoId}.json` (owner/co-logger).

### 7.2 Client polling

- Poll every **4 s** while `live`; pause when the tab is hidden (Page Visibility API) and resume on focus; stop after `ended`.
- Empty deltas return `[]` (+ HTTP ETag → 304 when nothing changed) so idle polls are cheap.
- Client merges deltas into the in-memory roster, reconciling rows it optimistically inserted (match on returned row id / client temp id).
- Public rate-limit via the existing `RateLimitMiddleware` keyed on slug+IP.

## 8. Live cockpit UI

Route `GET /net-sessions/{id}/cockpit` (owner/co-logger). Layout captured in the mockup (`.superpowers/brainstorm/.../cockpit-layout.html`).

- **Top bar:** LIVE badge (pulsing) / title / org · freq · band · mode / elapsed timer / "Public link" / "End net".
- **Fast entry bar:** callsign (uppercase, autocomplete + returning-regular badge via dupe-check), name, grid, RST, role select, **+ Log**. Keyboard-first; Enter logs and refocuses callsign (the quick-add "save & next" loop). Returning-operator hint line under the input.
- **Live roster:** newest-first table (#, callsign, name, grid, signal Sx, role, logged-by); inline edit/delete; new rows fade in as co-loggers add them.
- **Right rail (stat tiles + mini charts):** check-ins, unique calls, new-tonight, check-ins/min; mini signal-strength bar chart; mini participant map.
- **Mobile:** single column — entry bar sticky on top, roster below, stats in a collapsible drawer; reuses the bottom-tab nav and 44 px tap-target rules.

The public view (`GET /net/{public_slug}`) renders the same roster + stat tiles **without** the entry bar or edit controls, and without the "logged-by" column.

## 9. Analytics

A `NetMetrics` service computes everything from `qsos` scoped by `net_session_id` (per-session) or by `owner_id + net_title` (cross-session). All metrics are plain SQL aggregations — no extra storage.

### 9.1 Signal-strength distribution
- `SignalReport::strength(rst)` parses the strength digit (1–9) from `rst_received` (e.g. `"59"`→9, `"5x9"` tolerant). Unparseable → "unknown" bucket.
- Rendered as an S1–S9 bar chart, colour-graded (red→amber→green). Dependency-free inline **SVG/CSS** chart (no charting library) to keep the bundle lean.

### 9.2 Geospatial participant map
- `grid_square` → lat/lon centroid via the existing Maidenhead utility (`webroot/js/maidenhead.js` / `App\Service\GridSquare`).
- **Leaflet + OpenStreetMap** tiles, client-side, markers per participant (cluster when dense).
- **Graceful degradation:** if tiles fail to load (offline / constrained network) or a participant has no grid, fall back to a grid-grouped list. Leaflet loaded from a vendored asset (consistent with how fabric.js is vendored) so it works without a CDN.

### 9.3 Participation & retention
- **Per-session:** total check-ins, unique callsigns, new-vs-returning (first appearance in any of this owner's nets), busiest minute, average signal.
- **Cross-session** (grouped by `owner_id + net_title`, over the last 8 sessions by default): attendance over time (sparkline of unique calls per session), **regulars** (callsigns appearing in ≥ 50% of those sessions), **retention rate** (share of the previous session's callsigns that appear in the current one), **longest streak** (max consecutive sessions a callsign has attended). The window (8) and regular threshold (50%) are named constants in `NetMetrics` so the numbers are reproducible and tunable in one place.

## 10. Exports

- **ADIF:** `GET /net-sessions/{id}/export.adi` — reuses `App\Service\AdifExporter`, scoped to `net_session_id` (same pattern as the activation export). Owner/co-logger only.
- **PDF report:** `GET /net-sessions/{id}/export.pdf` — a `NetReportPdf` service renders an HTML report template → PDF via **dompdf/dompdf** (pure-PHP, composer-installable, shared-host friendly; no system binaries). Report contents: net header (title/org/freq/band/mode/date/duration), summary stats, signal-distribution chart (server-rendered SVG → embedded), full check-in roster table, and a static participant summary. Owner/co-logger only.

New composer dependency: `dompdf/dompdf`.

## 11. Component / file map (isolation)

Each unit has one clear purpose:

**Model**
- `src/Model/Table/NetSessionsTable.php`, `src/Model/Entity/NetSession.php`
- `src/Model/Table/NetSessionLoggersTable.php`, `src/Model/Entity/NetSessionLogger.php`
- `qsos` entity/table extended for `net_session_id`, `logged_by_user_id`, `net_role`.

**Controllers**
- `src/Controller/NetSessionsController.php` — owner/co-logger surface: index (mine + upcoming + recent), add/edit, start/end, cockpit, check-in JSON CRUD + delta feed, analytics page, exports.
- `src/Controller/NetController.php` — public read-only: `/net/{slug}` view + `/net/{slug}/live.json` delta feed. (Separate controller keeps the public, unauthenticated surface small and auditable — same rationale as `PublicController`.)

**Services**
- `src/Service/NetMetrics.php` — all aggregations (per-session + cross-session).
- `src/Service/SignalReport.php` — RST → strength digit / bucket.
- `src/Service/NetReportPdf.php` — HTML→PDF via dompdf.
- Reuse `AdifExporter`, `GridSquare`, dupe-check service, `RateLimitMiddleware`.

**Views**
- `templates/NetSessions/index.php`, `add.php`, `edit.php`, `cockpit.php`, `analytics.php`
- `templates/Net/live.php` (public)
- `templates/element/net/*` — entry bar, roster row, stat tiles, signal chart, map container (shared between cockpit and public view where read-only).

**JS** (`webroot/js/`)
- `net-cockpit.js` — entry loop + optimistic insert + polling/delta merge + roster reactivity + stat tiles.
- `net-live.js` — public view polling/merge (read-only subset).
- `net-charts.js` — SVG signal-distribution chart.
- `net-map.js` — Leaflet init + grid→latlon markers + list fallback.

**Migrations** (`config/Migrations/`)
- create `net_sessions`, create `net_session_loggers`, alter `qsos` (+3 nullable columns).

**Routes** (`config/routes.php`) — see §12.

## 12. Routes

```
# Owner / co-logger (auth required)
GET    /net-sessions                          index (mine / upcoming / recent)
GET    /net-sessions/new                      create form
POST   /net-sessions                          create
GET    /net-sessions/{id}/edit                edit form
PUT    /net-sessions/{id}                      update
POST   /net-sessions/{id}/start               scheduled → live
POST   /net-sessions/{id}/end                 live → ended
GET    /net-sessions/{id}/cockpit             live cockpit
GET    /net-sessions/{id}/analytics           analytics page
GET    /net-sessions/{id}/checkins.json       delta feed (?since=)
POST   /net-sessions/{id}/checkins.json       log check-in
PUT    /net-sessions/{id}/checkins/{qsoId}.json   edit
DELETE /net-sessions/{id}/checkins/{qsoId}.json   delete
GET    /net-sessions/{id}/export.adi          ADIF export
GET    /net-sessions/{id}/export.pdf          PDF report
POST   /net-sessions/{id}/loggers             add co-logger (owner)
DELETE /net-sessions/{id}/loggers/{userId}    remove co-logger (owner)
GET    /net-sessions/join/{logger_token}      self-join as co-logger (auth)

# Public (no auth)
GET    /net/{public_slug}                     read-only live view
GET    /net/{public_slug}/live.json           public delta feed (?since=)
```

## 13. Security & permissions

- All `/net-sessions/*` actions require authentication; check-in write/edit/delete and cockpit require the user to be the **owner or a co-logger** of that session.
- Start/end/edit/delete-session and logger management are **owner-only**.
- Public endpoints (`/net/{slug}`, `/net/{slug}/live.json`) serve only when `is_public` and status ≠ `scheduled`; they expose a whitelisted, read-only field subset (no `logged_by`, no co-logger PII, no internal user ids).
- `logger_token` and `public_slug` are random, unguessable; the token can be rotated by the owner (regenerate) to revoke pending invites.
- Public delta feed is rate-limited via `RateLimitMiddleware` (slug + IP).
- Mass-assignment lockdown: `owner_id`, `status`, `started_at`, `ended_at`, `public_slug`, `logger_token` are server-controlled, not accessible from request data (follows the `Activation`/`AuditLog` entity convention).
- Net lifecycle and check-in mutations emit audit-log events via the existing `AuditLogger`.

## 14. Testing strategy

- **Unit:** `SignalReport` digit parsing (incl. malformed RST); `NetMetrics` per-session + cross-session math on fixtures; `NetReportPdf` produces a non-empty PDF; ADIF scoping returns only the session's rows.
- **Integration:** lifecycle transitions (scheduled→live→ended) with owner-only guards; check-in CRUD authorization matrix (owner / co-logger / stranger / public); delta endpoint returns only rows changed since `since` and lists removals; public feed hides non-public sessions and restricted fields; rate-limit on public feed.
- **JS (Vitest):** delta-merge reconciliation (optimistic insert + server canonical row, dedupe, removals); signal-chart bucket rendering; maidenhead→latlon (existing) feeding the map; polling pause/resume on visibility.
- Coverage target consistent with the project (80 %+), following the existing PHPUnit + Vitest split.

## 15. Help docs (ship with the feature)

Per project rule, Help articles ship in the same change as the feature. Add under `templates/Help/` a "Running a net" category (or extend `logging/net-checkins`):
- Running a net with the NCS dashboard (create/start/end, the cockpit).
- Collaborative logging (adding co-loggers, the invite link).
- The public live view (sharing the link, what viewers see).
- Net analytics & reports (signal distribution, map, retention, PDF/ADIF export).

Register all in `HelpCatalog::TREE`.

## 16. Suggested implementation phasing (for the plan, not separate specs)

Although designed whole, build in dependency order:
1. Data model + migrations + `NetSession` CRUD + lifecycle (scheduled/live/ended).
2. Check-in write/edit/delete (QSO rows) + cockpit entry bar + roster (no realtime yet).
3. Polling delta feed + client merge (cockpit live) + collaborative co-loggers.
4. Public view + public delta feed + rate-limit.
5. Analytics: signal distribution → retention → map.
6. Exports: ADIF → PDF.
7. Help docs + mobile polish + tests throughout.

## 17. Open items to revisit on revamp

- Co-logger model: confirm "owner-added registered users + invite link" is the right friction level, vs. invite-link-only.
- Whether the public view should be password-protectable (reuse the card share-password gate) for private club nets.
- Chart approach: confirm dependency-free SVG is acceptable vs. adopting a small charting lib.
- Whether to keep `net_title`/`org` copied onto each QSO row, or always read through the session (denormalization trade-off).
- `logged_by_user_id` / `net_role` column names and whether `net_role` should be an enum constraint.
