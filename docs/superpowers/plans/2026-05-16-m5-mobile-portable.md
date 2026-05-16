# M5 Mobile & Portable Ops — Plan Outline

> **Status:** OUTLINE ONLY. Expand into a fully detailed TDD plan before starting execution.

**Prerequisite:** M4 complete and tagged `v1.0.0`. M4-follow-up work (callsign auto-complete, template categories, frequency→band auto-fill, `card_backgrounds` rebrand, subfolder-deploy) all merged to `master`.

**Goal:** Ship `v1.1`. Make the app genuinely usable from a phone while operating portable — POTA / SOTA / IOTA activations, field day, mobile contacts. Today the UI is desktop-first Bootstrap; M5 makes one-handed phone use the default story without abandoning desktop.

**Spec reference:** New §13 (to be appended to the design doc as part of T1 of this milestone).

---

## Why this matters

The Malaysian amateur radio community does a lot of activations from kampung sites, hills, and islands where a laptop is impractical. The current QSO add form requires too much scrolling and tapping on a phone. Operators end up paper-logging then re-typing at home — which is exactly the pain we're trying to remove.

Three behaviours the milestone must support without compromise:

1. **One-thumb logging** — typing a callsign, picking band/mode, hitting Save, all reachable from a single hand.
2. **Offline operation** — out at a site with no cell signal, the operator can still log; QSOs sync when reception returns.
3. **Activation grouping** — every QSO logged at "Bukit Larut SOTA 9M2/PR-001 on 2026-06-15" can be exported as a single batch for the awards program upload, without manual filtering.

---

## Task list

### Phase A: Mobile-responsive audit + polish
Establish the foundation. Today many pages overflow horizontally on 375 px viewports or have tap targets smaller than the 44 × 44 px guideline.

- **T1** — Spec amendment §13 "Mobile & portable ops" appended to `docs/superpowers/specs/2026-05-09-eqsl-card-design.md`. Documents target viewports (320 px minimum, 375 px primary, 414 px secondary), tap-target rules, and the breakpoints already established.
- **T2** — Audit every page in `templates/` at 375 px (Chrome DevTools mobile emulation). Log overflows, illegible text, and unreachable buttons in `docs/superpowers/audits/2026-05-MM-mobile-audit.md`.
- **T3** — Tighten the navbar: on screens < 768 px collapse to a sticky **bottom-tab** of the five core screens (Dashboard / Logbook / Quick add / Cards / More). Admin menu lives behind "More". The top brand bar shrinks to logo + theme toggle only. **+ Help:** new `templates/Help/mobile/navigation.php` explaining the bottom-tab layout and what's under "More". Register in `HelpCatalog`.
- **T4** — QSO add form (`templates/Qsos/add.php`) — single-column on mobile, large date/time pickers, frequency keypad-mode (`inputmode="decimal"`), band select via touch-friendly chips not `<select>`. **+ Help:** update `templates/Help/logging/manual-entry.php` with a "On mobile" section covering the new chip picker.
- **T5** — Logbook listing — switch table → swipeable card view < 768 px; primary fields (callsign, freq, date) prominent, secondary fields collapsed under a tap-to-expand chevron. **+ Help:** update the relevant logbook article (or create one if missing) describing the swipe/expand interaction.
- **T6** — Cards listing — already mostly responsive; verify and tighten if needed. **+ Help:** only update if behaviour changes.

### Phase B: Fast QSO entry (Quick-add)
A dedicated portable-first entry path that the operator opens once and stays in for a whole activation.

- **T7** — Route `/qsos/quick` with `QsosController::quick()`. Renders a minimal form: callsign, freq, mode, signal report (sent/rcvd), notes. Everything else (band, date/time, grid square) auto-derived or carried over from the previous entry. **+ Help:** create `templates/Help/mobile/quick-add.php` covering the route, when to use it vs `/qsos/new`, and the auto-derived field rules. Register in `HelpCatalog`.
- **T8** — "Last 5 QSOs" panel pinned above the form so the operator sees recent contacts for context. Tap a row to clone its band/mode/notes into the form (useful when a net is rotating through check-ins on one freq). **+ Help:** extend `mobile/quick-add.php` with a "Recents panel" section + screenshot of the clone-from-recent gesture.
- **T9** — Submit clears the form and refocuses the callsign input — zero taps to log the next contact. Confirmation is a brief toast (1.5 s) not a redirect. **+ Help:** add a "Save & next loop" subsection — important muscle-memory pattern operators need to know.
- **T10** — Quick-fill chips for the notes field (configurable per user; defaults: "Net", "POTA", "SOTA", "Contest", "Ragchew"). One tap inserts the chip text. **+ Help:** extend with "Notes shortcuts" section explaining defaults and how to customise (route to the user prefs page).
- **T11** — Big primary "Save & next" button across the full width at the bottom of the screen, always above the keyboard via `inset-area: bottom` or `env(keyboard-inset-height)` polyfill. **+ Help:** no separate article needed — covered by the Save & next subsection from T9.

### Phase C: GPS + activation grouping
Without this, batch-exporting "all QSOs from yesterday's activation" requires manual SQL.

- **T12** — Migration `activations` table: `id`, `user_id`, `code` (e.g. `9M2/PR-001`, `POTA-K-1234`, free text), `name`, `grid_square`, `started_at`, `ended_at NULL`, `notes`, `created_at`. **+ Help:** no user-facing change yet (schema only) — Help update bundled into T14.
- **T13** — `qsos.activation_id` nullable FK. Migration backfills NULL for historic QSOs (intentional — no auto-grouping for the past, only forward). **+ Help:** no user-facing change yet — covered by T14.
- **T14** — `ActivationsController` CRUD: list active + recent, start (writes started_at = now), end (writes ended_at = now). Active activations are pinned in a banner across the top of /qsos/quick. **+ Help:** create `templates/Help/mobile/activations.php` covering when to start one, the active-activation banner, how to end, the recent-activations list. Register in `HelpCatalog`.
- **T15** — Browser geolocation prompt on activation start (opt-in). On grant, derive Maidenhead grid square from lat/lon (existing util — or add `App\Service\GridSquare::fromLatLon()`); pre-fill the activation's `grid_square`. User can override. **+ Help:** extend `mobile/activations.php` with a "GPS auto-fill" section + privacy note (geolocation is request-scoped, never stored beyond the resolved grid square).
- **T16** — Quick-add form automatically tags new QSOs with the active activation_id (if any). Visual indicator: "Logging for **POTA K-1234** · OJ06aa" above the form. **+ Help:** cross-link from `mobile/quick-add.php` to `mobile/activations.php` so operators know how to enable grouping.
- **T17** — Export view: `/activations/{id}/export.adi` returns ADIF of all QSOs in this activation, grid square stamped on every record. POTA/SOTA upload portals want this exact format. **+ Help:** extend `mobile/activations.php` with an "Exporting to POTA/SOTA" section describing the ADIF endpoint, what fields ship, and the upload step on the awards portal.

### Phase D: PWA + offline-first
The defining capability for portable use. Critical: a service worker that does the wrong thing is worse than none at all, so this phase needs careful TDD and a clearly bounded scope.

- **T18** — `webroot/manifest.webmanifest` with name, short_name, theme/background colour, icons (192/512), display: `standalone`, start_url: `/qsos/quick` (the most likely portable use case). **+ Help:** create `templates/Help/mobile/install-pwa.php` covering "Add to Home Screen" on iOS Safari + Chrome Android, what the standalone display looks like, and the differences from a browser tab. Register in `HelpCatalog`.
- **T19** — Service worker `webroot/sw.js` with three caching strategies: `cache-first` for static assets (`/css/`, `/js/`, `/files/`), `network-first` for HTML/JSON, `network-only` for `/admin/*` and `/login`. **+ Help:** create `templates/Help/mobile/offline.php` covering what works offline, what doesn't (admin, login), and the cache-update behaviour after a release. Register in `HelpCatalog`.
- **T20** — IndexedDB schema `eqsl-card-offline.qsos` mirroring the server `qsos` table (subset of columns relevant to quick-add). **+ Help:** no user-facing change yet — covered by T21.
- **T21** — Quick-add form intercepts POST when `navigator.onLine === false`: stash the QSO in IndexedDB with a `pending_sync = true` flag, show "Queued offline · will sync when reconnected" toast. **+ Help:** extend `mobile/offline.php` with a "Logging offline" section showing the toast + queued-state visual cues.
- **T22** — Sync engine `webroot/js/sync.js`: on `online` event, drain the pending queue chronologically, POST each to `/qsos/quick.json`. Last-write-wins on duplicate `(callsign, datetime, band)` triples — server returns 200 + canonical row, client deletes the local pending row. **+ Help:** extend `mobile/offline.php` with a "How sync works" section + the conflict rule (server is authoritative).
- **T23** — Top-of-screen status pill: "Online · 0 queued" / "Offline · 3 queued" / "Syncing · 1 of 5". Tapping it opens a list of pending QSOs with retry/delete. **+ Help:** extend `mobile/offline.php` with a "Status pill + manual retry" section explaining each state and the per-row retry/delete actions.
- **T24** — Conflict-tolerance test: queue 50 QSOs offline, go online, verify all land server-side, none lost, none duplicated. **+ Help:** no user-facing change (test only).

### Phase E: Dupe checking + entry polish
The last-mile polish that turns the form from "usable" into "delightful".

- **T25** — As the operator types a callsign, query `/api/qsos/dupe-check?callsign=X&band=Y` (debounced 200 ms). Server responds with `{ last_worked_at, same_band_today: bool, same_band_this_activation: bool, total_qsos: N }`. Render as inline badge under the callsign field. **+ Help:** create `templates/Help/mobile/dupe-checking.php` covering the badge meanings (grey/blue/yellow/red traffic-light), the API endpoint, and the debounce behaviour. Register in `HelpCatalog`.
- **T26** — Visual warning levels: grey "first contact", blue "worked before (different band/mode)", yellow "worked today same band", red "duplicate of QSO in this activation". **+ Help:** extend `mobile/dupe-checking.php` with a screenshot of each state + the rule that decides each one.
- **T27** — Optional dupe-blocking: setting `block_dupes_in_activation` in user prefs. When ON, the red state disables the Save button + shows the conflicting QSO inline. **+ Help:** extend with "Blocking duplicates" section — where to toggle, why this is opt-in, when to keep it OFF (DXpeditions, contests with allowed duplicates).
- **T28** — Haptic feedback on Save (where supported via `navigator.vibrate(30)`) so the operator gets non-visual confirmation. **+ Help:** small mention in `mobile/quick-add.php` Save & next section — note browser/OS support caveats.
- **T29** — Voice-input button on the callsign field using the Web Speech API. Tap-and-hold → speak callsign → release → phonetic letters resolved to characters (NATO alphabet: "alpha"→A, "bravo"→B). Behind a feature flag because browser support is patchy. **+ Help:** create `templates/Help/mobile/voice-input.php` covering the gesture, the NATO alphabet mapping, the feature flag location, and the browser-support matrix (Chrome/Safari only as of writing). Register in `HelpCatalog`.

### Phase F: Release polish
By the time we reach F, every feature should already be documented inline. F is for cross-cutting items, not catching up on docs.

- **T30** — `docs/superpowers/audits/2026-05-MM-mobile-audit.md` follow-up: re-audit at 375 px and confirm every blocker from T2 is resolved.
- **T31** — Help portal index check: every Help article added during phases A–E renders correctly under `/help`, the `mobile/` category appears in the catalogue navigation, search hits the new content. Add a top-level `templates/Help/mobile/index.php` landing page that links to each sub-article. Capture mobile screenshots and embed them in the Help articles (replace any placeholder image refs).
- **T32** — README: mention M5 features (mobile / PWA / offline / activations), update the screenshots, bump test-count badge.
- **T33** — Tag `v1.1.0`, rebuild release zip.

---

## Spec coverage check

This milestone introduces new spec content (the design doc currently stops at v1.0 = M4). Treat T1 as the spec amendment — every other task must trace back to a clause in §13.

Sub-goals per phase mapped to tasks:

- One-thumb logging → T3, T4, T7–T11, T25–T28.
- Offline operation → T18–T24.
- Activation grouping → T12–T17.
- Documentation parity → T1, T31, T32.

---

## Out of scope (explicitly)

- CAT control / rig integration via WebSerial — useful but a separate engineering domain; consider for M6.
- Two-way eQSL exchange (receive cards from other operators) — different milestone direction (External eQSL networks); consider for M6 or M7.
- Awards tracking dashboard — was the alternative direction for M5; keep as a candidate for the next milestone.
- Multi-station support (one user, multiple callsigns) — would conflate with activation grouping; revisit only if operator feedback demands it.

---

## Open questions to resolve during expansion

1. **Bottom-tab vs hamburger** — committing to bottom-tab nav on mobile is a strong UX statement. Verify with a paper-prototype walkthrough before T3 writes any CSS.
2. **Service worker scope** — does it intercept admin routes? Probably not (admin work happens at a desk on wifi); explicit network-only rule in T19. Re-confirm during T18/T19 design.
3. **IndexedDB schema versioning** — first version is simple, but future field additions need a migration story. Decide between Dexie.js (~10 KB) or hand-rolled `onupgradeneeded` handlers.
4. **Grid square precision** — 6 characters (OJ06aa) vs 4 (OJ06)? Awards typically want 4; some operators log 6 for hilltop SOTA accuracy. Default 6, allow user to truncate on export.
5. **Voice input ROI** — T29 is high-effort, browser-variable feature. Keep it if Phase A–E ships on schedule, drop if behind.

---

## Estimated phasing (rough)

| Phase | PR count | Effort |
|-------|----------|--------|
| A     | 2–3      | ~1 week |
| B     | 2        | ~1 week |
| C     | 2        | ~1 week |
| D     | 3        | ~2 weeks (PWA + offline is genuinely hard) |
| E     | 1–2      | ~1 week |
| F     | 1        | 2–3 days |

Total: ~6–7 weeks of focused work, broken into ~10–13 small PRs.
