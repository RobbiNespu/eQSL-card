# Mobile audit (375 px) — 2026-05-16

**Milestone:** M5 T2.
**Scope:** every user-facing route at the primary mobile viewport (375 × 667 px = iPhone SE / 8). Targeted re-audit for M5 — not a full re-do of [2026-05-12-responsive-audit.md](./2026-05-12-responsive-audit.md), which established the structural baseline and is still valid.

## Method

- Static grep over `templates/` for known mobile anti-patterns: unwrapped `<table>`, non-responsive `.col-N` grids, fixed pixel widths, missing `inputmode` hints, `btn-sm` tap-target candidates, `<select>` where chips would be touch-friendlier.
- Cross-check against the existing M1-era responsive baseline in `webroot/css/theme.css` (`@media (max-width: 640px)` and `@media (max-width: 991.98px)` blocks).
- Live browser check at 375 × 667 (Chrome DevTools device mode) deferred to follow-up sweep — this static pass is the actionable input to T3–T6.

## Baseline (unchanged since 2026-05-12)

- `<meta viewport>` present in `templates/layout/default.php`.
- `.table { display: block; overflow-x: auto }` global rule means every table that uses `class="table"` is implicitly scrollable.
- 44 px min-height on `.btn / .form-control / .form-select` at < 640 px (touch-target rule already in place).
- 16 px font on form inputs at < 992 px (iOS zoom-on-focus fix).
- Inline `.btn-group` wrap rule with `flex: 1 1 50%` per button.

The baseline still holds. The findings below are everything new or unresolved on top.

---

## Findings, grouped by severity

### Blocking for M5 (must fix in Phase A)

**B1. Navbar collapse is `navbar-expand-lg` but Admin dropdown is unusable inside the collapsed menu on phones.**
- File: `templates/layout/default.php:43,68–87`
- Symptom: On < 992 px the navbar collapses to a hamburger. Tapping the Admin item opens a `<details>` dropdown whose `dropdown-content` uses `position: absolute` from DaisyUI's `dropdown-end`. Inside the collapsed vertical menu the absolute-positioned panel either clips behind the viewport edge or anchors awkwardly to the right.
- Fix path: **T3** (bottom-tab nav). The bottom-tab "More" button opens a sheet that lists Admin items as a flat list, sidestepping the dropdown-inside-dropdown problem.

**B2. `templates/Qsos/add.php:176–388` — the QSO add form has 17 `<input>` and 4 `<select>` fields across nine `col-md-*` columns.**
- At ≥ 768 px the form is 2–4 columns wide. At < 768 px every field stacks (which is correct), but the screen ends up 600+ px tall before the operator hits the Save button. Heavy scroll, primary action hidden below fold.
- Fix path: **T4** (single-column mobile form) tightens spacing, swaps `<select>` for chip rows where the option count is < 6 (mode, band), and pins the primary button. **T7** (Quick add) is the long-term answer — a separate route stripped to the essentials.

**B3. `templates/Qsos/index.php` table view at 375 px.**
- File: `templates/Qsos/index.php` (logbook listing)
- Symptom: table has 9 columns (Callsign / Date / Freq / Band / Mode / RST / Type / Notes / Actions). The `.table { overflow-x: auto }` rule keeps it from breaking layout but the operator now has to horizontally scroll for every action, including "Render". For portable use this is unworkable.
- Fix path: **T5** — at < 768 px swap the table for a stacked card layout: callsign + date as the heading, freq/band/mode as a small line below, actions in a chip row at the bottom.

**B4. Date filter on `templates/Qsos/index.php:51,54` uses `<input type="date">` without `inputmode`.**
- iOS Safari's native date picker is fine; Android Chrome's varies wildly by device. For a filter strip on the logbook, two date inputs are correct UX — no chip alternative makes sense.
- Fix path: leave the input type, but add `pattern="\d{4}-\d{2}-\d{2}"` and an explicit `placeholder="YYYY-MM-DD"`. Cheap. Covered by **T5** while we're in the file.

### Important (fix during Phase A polish)

**I1. `templates/Public/index.php:69–100` — Band / Mode / RST × 2 jump from 1-up to 4-up at 768 px.**
- Already flagged in 2026-05-12 audit finding #3 ("aesthetic"). Still unfixed. At 375 px the four fields stack vertically, fine; at 768–991 px they're four narrow squashed columns. Add `col-sm-6` so it goes 1 → 2 → 4 instead of 1 → 4.
- Severity bumped from "aesthetic" to "important" because the guest landing page is the most-visible page in the app and the 768 px Android-tablet portrait band is where it looks worst.
- Fix path: T4 sweep can include this.

**I2. `templates/Templates/edit.php:73,118` — Fabric designer at 375 px is fundamentally not usable.**
- File uses `col-lg-3` for the layers panel + `col-lg-6` for the canvas. Below 992 px the layers panel goes full-width above the canvas, pushing the canvas itself out of viewport on first paint. The designer assumes a desktop workspace.
- Decision: **do not fix for M5**. The designer is a desktop authoring tool, not a portable-ops surface. Document this in the Help portal (T31) — "Use the designer on a desktop / tablet in landscape; mobile is for using templates, not editing them."
- Fix path: add a `display: none` info banner at < 992 px telling the user to switch device. Tiny CSS change. Tag as **Phase A polish** (not blocking) under T6.

**I3. `templates/Cards/view.php` Card detail page uses `col-md-8 / col-md-4` (preview + metadata).**
- At < 768 px both stack to full width — correct. But the metadata `<dl class="row dl-stack">` uses `col-sm-5 / col-sm-7` inside the dl, which means at 376–575 px the dt/dd pairs are inline (5/7 split) but at < 576 px they stack. The 376–575 px in-between zone is awkward — the 5-col dt looks cramped next to a 7-col dd.
- Fix path: change the inner `dl` cols from `col-sm-5/col-sm-7` to `col-md-5/col-md-7` so the dt/dd stack everywhere below `md`. Single-line CSS-class edit. **Phase A polish** under T6.

### Acceptable (no M5 action needed)

- **Installer (`templates/Install/*.php`)** — one-time wizard, used on a desktop during initial setup. Not a portable surface.
- **Admin pages** (`templates/Admin/*.php`) — admin work happens at a desk; the existing < 640 px responsive rules suffice. The admin dashboard tile grid uses `col-md-3` and stacks at < 768 px, which is fine.
- **Admin Callsign Lookups `/all` table** — admin-only browsing surface; horizontal scroll is acceptable.
- **Profile, Auth pages** — short forms, already fit in a 375 px viewport without changes.

### New since 2026-05-12 audit

These pages either didn't exist or were significantly restructured after the prior audit; they get a brief specific note:

- **`templates/CardBackgrounds/index.php`** (renamed from `templates/Uploads/index.php` on 2026-05-16) — uses `col-md-3` cards, stacks 1-up below 768 px. New "Used by template" link list per card. Tap target on the template names is `<a>` text with no padding — verify ≥ 44 px tap area. **Fix:** wrap the per-template links in a chip-style span (CSS-only, theme.css). **Phase A polish.**
- **`templates/Admin/CardBackgrounds/index.php`** — admin browsing surface; table with thumbnail + 5 metadata columns. Acceptable (admin scrolls).
- **`templates/Admin/CallsignLookups/all.php`** — wide multi-column table, admin-only. Acceptable.
- **`templates/Admin/Dashboard/index.php`** — tile grid + Quick Links + audit table. The audit table has 4 columns and falls back to horizontal scroll at < 640 px. Acceptable for admin.

---

## Resolution map → M5 plan tasks

| Finding | Fixed in | Notes |
|---------|----------|-------|
| B1 navbar dropdown | T3 | Bottom-tab nav makes this moot. |
| B2 QSO add form length | T4 (mobile polish) + T7 (Quick add route) | T4 is the patch; T7 is the strategic answer. |
| B3 logbook table | T5 | Stacked card view at < 768 px. |
| B4 date inputs | T5 (bundled) | Add `pattern` + `placeholder`. |
| I1 Public band/mode/RST 1→4 jump | T4 (bundled) | Add `col-sm-6`. |
| I2 designer not mobile-usable | T6 | Info banner at < 992 px; no functional change. |
| I3 Card view inner dl breakpoint | T6 | Change `col-sm-*` → `col-md-*` on dl rows. |
| CardBackgrounds tap targets | T6 | Wrap inline links as chip pills. |

---

## Out of scope for this audit

- Visual contrast — already covered by `2026-05-12-contrast-audit.md`. No new contrast regressions introduced by the recent rename / admin nav cleanup.
- Performance (LCP / FID / TBT on a mid-tier Android) — separate concern, not what M5 is solving.
- Dark mode parity on the new mobile components — to be checked during T3/T4 implementation, not pre-audited.
- Designer UX rework — explicitly out of M5 scope per §13.7 of the design spec.

---

## Follow-up (real browser sweep)

The static pass above gives us the actionable to-do list. A real-device sweep should still happen during T3 implementation to confirm:

- Bottom-tab nav sits above the iOS Safari home-indicator bar.
- The PWA standalone-mode address bar doesn't repaint when keyboard appears (Phase D / T18 territory, but worth eyeballing during T3 too).
- `env(safe-area-inset-bottom)` padding renders correctly on notched / pill-screen devices.

These need a phone, not a desktop emulator.
