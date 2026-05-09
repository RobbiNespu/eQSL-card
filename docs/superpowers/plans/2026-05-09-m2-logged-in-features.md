# M2 Logged-In Features — Plan Outline

> **Status:** OUTLINE ONLY. Expand into a fully detailed TDD plan (in the same shape as M1) before starting execution. This file lists the task breakdown so the milestone is plan-able and traceable, not yet executable.

> **For agentic workers:** Do NOT execute tasks from this file as-is. The skill `superpowers:writing-plans` must be re-invoked against this outline to produce a runnable plan first. Steps below intentionally lack the per-step TDD scaffolding required for safe execution.

**Prerequisite:** `docs/superpowers/plans/2026-05-09-m1-foundation.md` complete and tagged `v0.1.0`.

**Goal:** Bring the logged-in user experience to parity with the spec — QSO library, ADIF/CSV import, multi-select bulk render, public share links with optional password.

**Spec reference:** `docs/superpowers/specs/2026-05-09-eqsl-card-design.md`, Section 11 → M2.

---

## Task list

### Phase A: Logbook foundation
- **T1** — Migration `qsos` table (matches spec §6.3) + `cards.qso_id` FK (deferred from M1's Task 4) + ORM Table/Entity classes + fixtures.
- **T2** — `QsosController` index/list with search/filter (band, mode, date range, callsign substring) + view templates.
- **T3** — Manual QSO CRUD: add / edit / soft-delete a single QSO with validation (UTC datetime parsing, callsign format).

### Phase B: Bulk import
- **T4** — `AdifParser` service (TDD with at least three real-world fixture files: N1MM export, Log4OM export, JS8Call/FT8 ADIF). Whitelist tag names; reject unknown gracefully.
- **T5** — `CsvParser` service (TDD with quoted fields, BOMs, comma vs semicolon vs tab delimiters; column-mapping support).
- **T6** — `/qsos/import` upload page with file-type detection, validation summary screen (`{valid, invalid, duplicate}` counts), and final transactional batch insert.

### Phase C: Card library
- **T7** — `CardsController` index for the logged-in user (paginated list with thumbnail, callsign, date, share-status indicator).
- **T8** — `CardsController` view (single card detail with re-download links).
- **T9** — `CardsController` delete (soft-delete; storage cleanup happens in M4 sweep tool).

### Phase D: Render-from-QSO
- **T10** — `CardsController::renderFromQso(qsoId)` flow: pick template + background → render → persist with `qso_id` set + `qso_data_json` snapshot.
- **T11** — `/qsos/multi/render` endpoint: accept selected QSO IDs + one template + one background → render sequentially in chunks of 5 with progress polling. Reuse Alpine.js `bulkRender` component.
- **T12** — Progress modal UI + polling endpoint that returns `{done, total}`.

### Phase E: Public sharing
- **T13** — `CardsController::share(cardId)` POST that mints a `share_slug`, optional password, returns share URL. UI toggle in card detail view.
- **T14** — Public share landing `/qsl/{slug}` with revoke check, password gate, OG meta tags, embedded card image, QSO summary, download buttons.
- **T15** — `/qsl/{slug}/unlock` password form + per-slug session cookie.
- **T16** — `CardsController::revoke(cardId)` POST sets `share_revoked_at`.
- **T17** — Rate limit on `/qsl/*/unlock` (5 attempts / 15 min per slug) — extend the M1 `RateLimitMiddleware` rule table.

### Phase F: Update path
- **T18** — `/admin/upgrade` route (admin-only) that re-runs `Migrations::migrate()` and clears `tmp/cache/` so post-FTP-upload deployments can be finalized via the browser.

### Phase G: Tests + release
- **T19** — Integration tests for parser fixtures, share-unlock with bad password, permission boundary checks (user-A cannot view user-B's cards).
- **T20** — Tag `v0.2.0` and rebuild `dist/eqsl-card-0.2.0.zip` via `scripts/build-release.sh 0.2.0`.

---

## Spec coverage check (do before expanding)

Map each item in spec §11 → M2 to a task above; if any are missing, add tasks. Mapped:

- Dashboard → handled inline as part of T7's UI shell (a small "recent cards + quick actions" panel).
- Card history list / detail / re-download / delete → T7, T8, T9.
- QSOs table + manual CRUD → T1, T3.
- ADIF + CSV import → T4, T5, T6.
- Multi-select bulk render → T11, T12.
- Render-from-QSO → T10.
- Public share links + password + revoke + OG → T13–T16.
- `/admin/upgrade` → T18.
- Tests → T19.

When expanding into a runnable plan, each Task above becomes one or more bite-sized TDD tasks following the M1 pattern (write failing test → run → implement → run → commit).
