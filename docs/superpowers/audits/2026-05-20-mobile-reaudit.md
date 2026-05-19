# Mobile re-audit (375 px) — 2026-05-20

**Milestone:** M5 T30.
**Predecessor:** [2026-05-16-mobile-audit.md](./2026-05-16-mobile-audit.md) — the initial static pass that produced the M5 fix backlog (B1–B4, I1–I3, CardBackgrounds tap-targets).
**Scope:** confirm every blocker and important finding from the initial audit is resolved at 375 × 667 px before tagging `v1.1.0`.

## Method

Mixed pass:

1. **Live 375 × 667 capture** via Playwright MCP at the deployed dev stack — ten primary user-facing routes screenshotted with the layout shell + bottom-tab nav rendered. Screenshots live alongside this file under [`2026-05-20-mobile-reaudit-shots/`](./2026-05-20-mobile-reaudit-shots/).
2. **Source verification** via `grep` against `templates/` and `webroot/css/theme.css` — for each finding, confirm the specific code change called out in the resolution map of the original audit is present on `master`.
3. Cross-check against the resolution map of the predecessor audit; anything still unresolved gets surfaced.

The captured routes are:

| # | Route | Screenshot |
|---|-------|------------|
| 01 | `/login` | `01-login.png` |
| 02 | `/` (guest landing) | `02-landing.png` |
| 03 | `/qsos/quick` | `03-qsos-quick.png` |
| 04 | `/qsos/new` | `04-qsos-new.png` |
| 05 | `/qsos` (logbook) | `05-qsos-index.png` |
| 06 | `/profile` | `06-profile.png` |
| 07 | `/activations` | `07-activations.png` |
| 08 | `/dashboard` | `08-dashboard.png` |
| 09 | `/help` | `09-help-index.png` |
| 10 | `/help/mobile/quick-add` | `10-help-quick-add.png` |

The designer (`templates/Templates/edit.php`) is intentionally not in the live capture set — per I2 it's documented as desktop-only at < 992 px; the verification is the banner rendering, which is a static-check item.

---

## Resolution status, in priority order

### Blocking (B1–B4)

#### B1. Navbar dropdown unusable on phones → **RESOLVED**

- **Fix that landed:** T3 — sticky bottom-tab nav under 992 px. See `templates/layout/default.php:244` (`<nav class="mobile-tabbar">`) and `webroot/css/theme.css:1695` (`.mobile-tabbar` rules).
- **Live evidence:** every captured route (01–10) shows the five-tab strip at the bottom — Home / Logbook / Quick add (primary accent) / Cards / More. The original DaisyUI nested `<details>` dropdown is no longer reachable from a phone; admin destinations live in the More sheet as a flat list.
- **Safe-area handling:** `padding-bottom: env(safe-area-inset-bottom, 0)` on the tab bar + `padding-bottom: calc(56px + env(safe-area-inset-bottom, 0) + 16px)` on `body` (theme.css:1692) means content stops cleanly above the tab bar on notched / pill-screen devices.

#### B2. QSO add form too tall, primary action below fold → **RESOLVED**

- **Fixes that landed:** T4 (single-column polish on `/qsos/new`) + T7 (Quick-add route at `/qsos/quick`).
- **Live evidence — `/qsos/new`** (`04-qsos-new.png`): every field stacks single-column, labels above inputs, the `Add QSO` primary action sits cleanly inside the viewport above the bottom-tab nav. No squashed multi-column grids remain.
- **Live evidence — `/qsos/quick`** (`03-qsos-quick.png`): the stripped portable form (Their callsign / Frequency / Mode / RST sent/received / Notes) lands in a viewport that also accommodates the LAST LOGGED panel, the active-activation banner, and the sticky `Log contact` button above the on-screen keyboard / bottom-tab nav. This is the strategic fix the original audit called for under T7.

#### B3. Logbook table at 375 px → **RESOLVED**

- **Fix that landed:** T5 — stacked card view at < 768 px.
- **Live evidence** (`05-qsos-index.png`): each QSO renders as a self-contained card with the callsign + transport badge as the heading, `DATE/TIME UTC`, `RST`, `FREQ`, `BAND`, `MODE` as labelled rows below, and `View` / `Render` (or `View card`) as a side-by-side action row. Horizontal scrolling is no longer required to reach any action.
- A 4-column compact summary table reappears on `/dashboard` (`08-dashboard.png` — Recent QSOs panel), but the columns are tight (Callsign / UTC / Band / Mode) and the page is a glance-surface, not the working logbook. Out of scope for B3.

#### B4. Date filter `<input type="date">` lacking pattern/placeholder → **RESOLVED**

- **Fix that landed:** T5 (bundled).
- **Source verification:** `templates/Qsos/index.php:52` and `:57` — both date inputs now carry `placeholder="YYYY-MM-DD" pattern="\d{4}-\d{2}-\d{2}"` plus a `title="..."` so the validation message is operator-readable. Native picker still renders where the OS supports it (`05-qsos-index.png` shows the calendar glyph at the right of each input).

### Important (I1–I3 + new-pages addenda)

#### I1. Public form band/mode/RST 1 → 4 jump at 768 px → **RESOLVED**

- **Fix that landed:** T4 (bundled — `col-6 col-md-3` ladder).
- **Source verification:** `templates/Public/index.php:72,83,94,103` — the four narrow inputs (Band, Mode, RST sent, RST received) now use `col-6 col-md-3`, giving a 1-up → 2-up → 4-up ladder across 375 px / 576 px / 768 px breakpoints. The original 1 → 4 squash is gone.
- **Live evidence** (`02-landing.png`): at 375 px the inputs stack single-column as expected.

#### I2. Designer not mobile-usable → **RESOLVED (info banner approach)**

- **Fix that landed:** T6 — `<div class="designer-desktop-only-banner">` info banner shown at < 992 px, plus the designer itself remains in place above for users who switch to landscape / desktop.
- **Source verification:** `templates/Templates/edit.php:57–61` — banner block with `role="status"` content ("Best on desktop. ... it works on screens 992 px or wider. On a phone you can still browse your templates and edit metadata, but the canvas editor needs more room.") The original 50-line preamble comment in the file (lines 48–56) documents the rationale.
- Not captured in live screenshots because per the original audit this page is explicitly out of the M5 portable-ops surface; verification is the banner rendering.

#### I3. Card view `<dl>` inner breakpoint awkwardness → **RESOLVED**

- **Fix that landed:** T6 — `col-md-5 / col-md-7` so the dt/dd pairs stack everywhere below `md`.
- **Source verification:** `templates/Cards/view.php:18–28` — every dt/dd pair on the QSO metadata `<dl>` uses `col-md-5` / `col-md-7`. The awkward 376–575 px in-between zone is gone; dt/dd stack vertically up to the `md` breakpoint, then go side-by-side.

#### CardBackgrounds per-template tap targets → **RESOLVED**

- **Fix that landed:** T6 — wrap each per-template link in a `.template-chip` pill with explicit ≥ 44 px tap padding.
- **Source verification:** `templates/CardBackgrounds/index.php:32–34` — links are wrapped as `<li><a class="template-chip" href="...">` inside a `<ul class="template-chip-list">`. The theme.css block at lines 1928+ ("M5 T6 — Polish: designer banner + tap-targets + dl breakpoint") supplies the pill styling and tap-target floor.

---

## New since the initial audit (sanity check on M5 additions)

Phases B–E added new pages that didn't exist when the initial audit ran. Each gets a brief 375 px check:

- **`/qsos/quick`** (`03-qsos-quick.png`) — primary M5 deliverable. Active-activation banner, LAST LOGGED panel with `Tap to reuse` hint, sticky save button, chip row under Notes, optional GPS pin button (visible on activations page, not on quick-add per-design). Everything reachable with one thumb.
- **`/activations`** (`07-activations.png`) — single-column. Active activation card → start-new form → recent activations list. The recent-activations row uses the same stacked-card pattern as the logbook — consistent and readable at 375 px.
- **`/profile`** (`06-profile.png`) — Quick-add safety (block-dupe toggle) and Quick-add voice input (Web Speech mic toggle) both appear as labelled checkboxes with explanatory paragraphs. Single-column. No layout regression.
- **`/help`** (`09-help-index.png`) — category cards stack single-column. Mobile & portable ops section currently shows the four phase-A/B/C/D articles (Bottom-tab navigation / Quick-add / Activations / Install PWA). The T31 PR (in flight at audit time) adds the four catch-up articles + landing page; once merged this screenshot's mobile category will gain four entries, no layout change.
- **`/help/mobile/quick-add`** (`10-help-quick-add.png`) — long-form article renders cleanly at 375 px: H1 sized to the viewport, no horizontal scroll, code/table elements wrap or scroll inside their own scroll-port.

No new mobile-blocking regressions introduced by Phases B–E.

---

## Residual observations (non-blocking)

1. **Bottom-tab nav is shown to logged-out users on the guest landing page** (`02-landing.png`). Strictly speaking some of the destinations (Logbook, Cards) are auth-required and will bounce to `/login`. Acceptable for v1.1.0 — the alternative (auth-aware tab strip on the guest surface) is a refactor not a polish, and the rebound to `/login` is graceful. Worth a follow-up issue but not a release blocker.
2. **Recent QSOs summary table on `/dashboard`** keeps the small 4-column tabular form rather than stacked cards. Glanceability wins here — the dashboard is a survey, not a working list. Not regressed by M5; deliberately left.
3. **Screenshots show a connection / dev artifact at the right edge** of several captures (a small overlay icon roughly where the "More" tab label sits). This is the Playwright MCP capture overlay, not a product issue — the same routes accessed in a normal browser at the same viewport show the "More" label rendered in full. No fix required.

---

## Conclusion

Every blocker (B1–B4) and important finding (I1–I3 + CardBackgrounds) from the 2026-05-16 audit is resolved on `master`. The portable-ops surface at 375 px is fit for the v1.1.0 release.

**Recommendation:** proceed to T33 — tag `v1.1.0` and rebuild the release zip.

---

## Out of scope for this re-audit

- Real-device validation on physical iOS Safari / Android Chrome — the predecessor audit's footer flagged this; still wants a phone-in-hand pass at some point but is not gating M5.
- Performance metrics (LCP / FID / TBT). Separate concern.
- Tablet portrait (768 px) and landscape (1024 px). The original audit ranged 375 → 991 px; the bottom-tab nav is active up to 992 px so the tablet range is partially covered, but a dedicated 768 px pass would be a good Phase G item if one exists.
- Designer UX rework — explicitly out of M5 scope per the design spec §13.7.
