# Responsive structural audit — 2026-05-12

Method: static grep of `templates/` + `webroot/css/theme.css`. No real
browser was used — this is a backstop, not a substitute for the manual
viewport sweep at 320 / 640 / 768 / 1024 / 1440 px called out in
`docs/superpowers/plans/2026-05-12-ui-audit-plan.md`.

## Existing responsive baseline (good)

Verified via grep against `templates/layout/default.php` and
`webroot/css/theme.css`:

- `<meta name="viewport" content="width=device-width, initial-scale=1">`
  is present in `templates/layout/default.php` (count: 1).
- `@media (max-width: 640px)` in `webroot/css/theme.css:1289` handles:
  - `h1/h2` font-size step-down
  - `.btn / .form-control / .form-select` min-height 44px (touch target)
  - `.table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }`
  - `.btn-group { flex-wrap: wrap; }` + `.btn-check + .btn { flex: 1 1 50%; }`
  - `.row.g-2 > [class*="col-md-"], .row.g-2 > [class*="col-sm-"] { margin-bottom: var(--s-2); }`
  - `.modal-dialog { margin: var(--s-3); max-width: none; }`
- `@media (max-width: 991.98px)` in `webroot/css/theme.css:990` handles:
  - Collapsed mobile navbar (vertical nav, hamburger toggler, full-width primary CTA)
  - `webroot/css/theme.css:1064` — dropdowns expand inline inside collapsed navbar
- `@media (max-width: 991.98px)` in `webroot/css/theme.css:1305` forces
  `input.form-control / select.form-select / textarea.form-control` to
  `font-size: 16px` (iOS zoom-on-focus fix).
- `@media (min-width: 576/768/992/1200px)` rules ladder `.container`
  max-width and the `.col-sm-* / .col-md-* / .col-lg-*` grid.
- Below 576px, no grid breakpoint applies, so `.row > * { width: 100%; }`
  forces single-column stacking. This is the correct mobile default.

## Findings

### Critical

(none) — no template structure was found that would visibly break the
page at the audited viewports beyond the existing baseline rules.

### Minor

1. **`templates/Templates/edit.php:25,77,90` — designer columns lack
   `col-sm-*` fallback.** Layout is `col-md-3 / col-md-6 / col-md-3`.
   - 320–575px: all three stack full-width (good — fine on phones).
   - 576–767px: same as above (no col-sm- set, so falls back to 100% each).
   - 768–991px: each side panel is `0.25 * ~720px ≈ 180px` wide. The
     "Selected field" right panel has number inputs, color pickers, and a
     row split as `col-7 / col-5` and `col-6 / col-6` inside it
     (`Templates/edit.php:112,132`). At ~180px container × 50% = ~90px
     per inner column. Inputs become very cramped but do not overflow.
   - Suggested fix (Frontend): add `col-lg-3 col-md-12` (or stack only at
     md and below) so the three-column designer layout is only used at
     `lg+`, e.g. `class="col-lg-3 col-md-12"` on the side panels and
     `class="col-lg-6 col-md-12"` on the canvas.
   - Severity: aesthetic; functionality is preserved.

2. **`templates/Qsos/index.php:136` — inline `style="margin: 5rem auto;"`
   on `.modal-dialog` overrides the `<=640px` rule.** theme.css sets
   `.modal-dialog { margin: var(--s-3); max-width: none; }` at <=640px so
   the modal hugs viewport edges with a 12px gutter. The inline style on
   line 136 wins (inline > @media unless `!important`) and leaves a 5rem
   top margin on phones. The `max-width: none` still applies, so the
   modal width collapses to its content because no explicit width is
   set. Result: a content-sized modal floating with 80px top margin on
   a phone — usable, but inconsistent with the responsive intent.
   - Suggested fix (Frontend): remove the inline `style="margin: 5rem auto;"`
     and let theme.css handle responsive sizing. The default
     `.modal-dialog { margin: 5rem auto; }` already lives in theme.css
     (line 1081), so the inline duplicate is redundant anyway.
   - Severity: minor — inline duplicates default style that already
     exists in theme.css and prevents the <=640px override from firing.

3. **`templates/Public/index.php:46–85` — four `col-md-3` form fields
   (Band / Mode / RST sent / RST received) jump from 1-up below 768px
   to 4-up at 768px.** No `col-sm-6` is set, so between 576–767px the
   form is single-column. At exactly 768px four narrow selects/inputs
   appear in one row. Functional, just visually abrupt.
   - Suggested fix (Frontend, optional): add `col-sm-6` so tablet
     portrait shows 2-up before snapping to 4-up at md+.
   - Severity: aesthetic.

4. **`templates/Admin/Audit/index.php:40` — `metadata_json` displayed as
   raw text in a table cell.** Long JSON strings can be wide, but the
   <=640px rule on `.table` puts the entire table into `overflow-x: auto`,
   so this is contained. Above 640px the cell can balloon and stretch
   the row. Acceptable in admin-only UI but worth noting.
   - Suggested fix (Frontend, optional): wrap in a `<details>`
     disclosure, or set `max-width: 30ch; overflow: hidden;
     text-overflow: ellipsis;` on the cell with a title attribute.
   - Severity: aesthetic (admin-only).

### Acceptable (not worth fixing)

- `templates/Auth/{login,forgot,register,reset}.php`,
  `templates/Public/{unlock,share_gone}.php` — `style="max-width: NNNpx;
  margin: 0 auto"`. These cap the form width on desktop; at narrow
  viewports the inner content shrinks to viewport minus container
  padding. No overflow risk.
- `templates/Admin/{Users/edit,Settings/index}.php:179` — `style="max-width:
  320px"` on form `.field` wrappers. Same pattern, safe.
- `templates/Profile/index.php:8`, `templates/Admin/Settings/index.php:14,21`,
  `templates/Admin/Templates/pending.php:31` — `style="max-width: 200/240px"`
  on `img.img-fluid`. `.img-fluid` already provides `max-width: 100%`;
  the inline cap further constrains on wide viewports. Safe.
- `templates/Admin/Uploads/index.php:39` — `style="height: 60px; width:
  90px"` on a thumbnail `<img>` inside a `<table>`. The table itself
  becomes horizontally scrollable at <=640px, so the fixed cell content
  cannot push the page wider than viewport.
- `templates/Cards/view.php:36` — `<code style="word-break: break-all">`
  on the public share URL. Already handled.
- `templates/Qsos/import.php:33` — `<pre style="white-space: pre-wrap">`
  on import errors. Already handled.
- `templates/Public/index.php:133`, `templates/Qsos/add.php:124` —
  `.btn-group` instances both have ≤3 buttons, and `<=640px` rule wraps
  the group with `flex: 1 1 50%` per button. Safe.
- Filter forms (`templates/Qsos/index.php:11`,
  `templates/Admin/{Cleanup,Cards,Audit,Users,Uploads,CallsignDirectory}/index.php`):
  several mix `col-md-*` without `col-sm-*`. Below 576px all stack
  (base `.row > *` is 100%). At 576–767px they also stack because no
  `col-sm-*` is present. At 768px+ the layout snaps to multi-column.
  Functional, just no in-between tablet layout — acceptable for an
  admin-flavored filter strip.
- Tables: every `<table>` found uses `class="table"` or `class="table
  table-sm"` or `class="table table-striped"` — all picked up by the
  <=640px overflow-x rule. No table appears without a `.table` class.

## Recommendations

A follow-up Frontend task could pick up the four minor items above. None
are blocking for M1. The most-visible would be **finding #1 (designer
panel widths at md)** — the designer is the most complex page on the
app and the 768–991px band squeezes it noticeably.

A manual browser sweep at 320 / 640 / 768 / 1024 / 1440 px is still
recommended for visual parity confirmation. This static audit confirms
no structural pattern is missing the existing responsive baseline.
