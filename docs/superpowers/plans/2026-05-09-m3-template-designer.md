# M3 Template Designer — Plan Outline

> **Status:** OUTLINE ONLY. Expand into a fully detailed TDD plan (in the same shape as M1) before starting execution.

**Prerequisite:** M2 complete and tagged `v0.2.0`.

**Goal:** Replace the M1 hard-coded card layout with a drag-and-drop template designer (Fabric.js canvas), template gallery (My / Public-approved / System), clone-and-edit, and admin moderation queue.

**Spec reference:** Sections 7.5, 6.4, 11 → M3.

---

## Task list

### Phase A: Designer wiring
- **T1** — Pull Fabric.js into the asset pipeline (CDN drop, hash-pinned in CSP). Document fallback to vendored copy under `webroot/js/vendor/` for sites blocking jsDelivr.
- **T2** — `TemplatesController::new` and `::edit` actions + view shell with toolbar, canvas area, right-side properties pane.
- **T3** — Field placeholder palette component (Alpine.js dropdown) — supplies the list of `{field}` placeholders defined by the renderer.

### Phase B: Template persistence
- **T4** — `TemplatesController::save` POST: validates `layout_json` shape (canvas size, every field has placeholder/x/y/font/size/color/rotation), serializes Fabric.js output to the schema's `layout_json`.
- **T5** — Backround upload field on the designer (reuses M1 `ImageOptimizer`); stored as a regular `uploads` row with `user_id` set.
- **T6** — Server-side thumbnail generation on save: instantiate `CardRenderer` with sample data fixture (callsign W1AW, sample QSO) → write 400-px PNG to `webroot/files/templates/{templateId}.png` → store `thumbnail_path`.

### Phase C: Gallery + clone
- **T7** — Template gallery view at `/templates` with three tabs: My Templates, Public, System. Each card shows thumbnail + name + description + clone button.
- **T8** — Clone-and-edit: `TemplatesController::clone(id)` duplicates the row (drops user_id ownership change, resets `is_public`/`is_approved`/`is_system` to `false`), redirects to `/templates/{newId}/edit`.
- **T9** — Make-public toggle: `is_public=true` queues the template into the moderation table, sends a notification to admin email.

### Phase D: Admin moderation
- **T10** — `Admin/TemplatesController::pending` lists templates where `is_public=true && is_approved=false`. Each row has Approve / Reject buttons.
- **T11** — Approve action: sets `is_approved=true`, fires audit log entry (M4 audit_logs not yet — temporary placeholder logger writes to `logs/moderation.log` until M4 ships).
- **T12** — Reject action: sets `is_public=false`, optionally records reason.

### Phase E: Render integration
- **T13** — `CardRenderer` already accepts arbitrary `layout_json`; just verify regression suite covers a designer-produced template render (parity test: render via designer-produced JSON should be byte-equivalent to render via M1 hard-coded JSON for an equivalent layout).
- **T14** — Update guest form (M1 Task 19's view) to include a template picker once M3 ships, populated from `templates WHERE is_public=true AND is_approved=true`.

### Phase F: Migration of M1 default
- **T15** — Migration that re-flags the seeded M1 template as `is_system=true, is_public=true, is_approved=true` (it already is, per the M1 seed). No-op confirmation, but adds a regression test that the seed still applies cleanly.

### Phase G: Tests + release
- **T16** — Vitest harness for designer JS helpers (Fabric.js coordinate conversion, snap-to-grid, JSON serialization).
- **T17** — Renderer parity test: identical inputs → identical outputs after a JSON round-trip.
- **T18** — Tag `v0.3.0` and rebuild release zip.

---

## Spec coverage check

- Templates table + migration → already in M1 (T4 of M1 plan); M3 only adds rows / behavior.
- Drag-drop designer → T1–T6.
- Field palette → T3.
- Server-side thumbnail → T6.
- Template gallery (My / Public / System) → T7.
- Clone-and-edit → T8.
- Make-public + admin moderation → T9, T10, T11, T12.
- Migration of M1 hard-coded layout → T15.
- Tests: layout-JSON renderer parity, designer JS via Vitest → T16, T17.

When expanding, each Task becomes multiple bite-sized TDD tasks following the M1 pattern.
