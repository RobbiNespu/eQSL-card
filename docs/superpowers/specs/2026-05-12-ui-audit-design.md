# eQSL Card — UI/UX Audit & Polish (Design Spec)

**Date:** 2026-05-12
**Status:** Draft — pending review
**Scope:** Consistency, accessibility, lazy loading, component reuse, dark mode, production CSS build

---

## Stack reality check

The user request references "Shadcn UI". For the record:

- shadcn/ui is a **React** component library that you copy-paste into a Tailwind-compiled codebase. It is **not** in this project.
- The codebase ships with **CakePHP 5 + Tailwind (Play CDN) + DaisyUI (CDN) + Alpine.js**, themed to be *visually* shadcn-inspired (Inter + Geist Mono, zinc neutrals, emerald accent, rounded-md, soft shadows).
- Adding real shadcn would mean a React rewrite, which conflicts with the explicit shared-hosting / no-compile constraint set earlier in the project.

Every reference to "shadcn" in this spec therefore means **"the shadcn-inspired aesthetic, delivered via Tailwind + DaisyUI."**

---

## Goals

1. Visual consistency across all 41 templates — buttons, badges, alerts, cards, tables, forms, navigation.
2. Accessibility quick wins everywhere, deeper audit on the bulk-render modal + designer.
3. `loading="lazy"` on every non-critical `<img>`.
4. Reusable Cake `element/` partials for the patterns that currently duplicate across templates.
5. Forms with consistent labels, placeholders, helper text, and required indicators.
6. Working dark mode with a toggle that persists per user, defaults to system preference, and avoids FOUC.
7. Production-grade CSS pipeline — pre-compiled bundle, no Play CDN.

## Non-goals

- React migration of any kind.
- Net-new features (this is polish, not new functionality).
- Full WCAG AAA conformance (target AA: 4.5:1 normal text, 3:1 large/UI).
- Native mobile app, PWA installability beyond what comes free.

---

## Phasing

Five phases, executed in order. Each phase is its own commit (or small batch), tests stay green after each, and every commit is independently reviewable / revertable.

### Phase 1 — Quick wins

Smallest, lowest-risk surface. Mostly add-only edits across many files.

#### 1.1 Image lazy loading

Audit every `<img>` in `templates/`. Add `loading="lazy"` everywhere except clearly above-the-fold images (the Card preview on `Cards/view`, `Public/share`, `Public/preview` — those are the page content). Currently 8 of 18 are lazy.

Targets (add `loading="lazy"`):
- `templates/Admin/Settings/index.php` — default background preview + bundled fallback preview
- `templates/Admin/Templates/pending.php` — already lazy ✓
- `templates/Admin/Uploads/index.php` — already lazy ✓
- `templates/Cards/index.php` — already lazy ✓
- `templates/Dashboard/index.php` — already lazy ✓ (recent cards thumbs)
- `templates/Profile/index.php` — avatar image
- `templates/Public/index.php` — template radio thumbnails
- `templates/Qsos/render.php` — existing uploads thumbnails
- `templates/Templates/index.php` — already lazy ✓
- `templates/Uploads/edit.php` — current upload preview
- `templates/Uploads/index.php` — already lazy ✓

#### 1.2 Form polish sweep

Every form input on every page must have:

- A visible `<label class="form-label">` with an explicit `for` attribute (already standardised, audit for stragglers).
- A `placeholder` attribute when the placeholder is genuinely different from the label (don't repeat the label).
- A `<p class="form-text">` helper note when the field has a non-obvious constraint or format. Examples:
  - Frequency MHz → "Up to 4 decimal places, e.g. 14.07415"
  - Grid square → "Maidenhead locator, 4 or 6 chars (e.g. OJ02wx)"
  - Date/Time UTC → "All log times are UTC, not local"
  - Email → "Used for password resets and verification"
  - Password → "At least 8 characters. A passphrase is stronger than a complex password."
- A `<span class="req">*</span>` marker on required inputs (`required` attribute + visible asterisk).
- A sensible `autocomplete` attribute (`email`, `username`, `current-password`, `new-password`, `off` for callsigns to prevent browser autofill polluting them).

Touch list (every template with a form):
- `Auth/login.php`, `register.php`, `forgot.php`, `reset.php` — already standardised, audit only.
- `Qsos/add.php`, `Qsos/render.php`, `Qsos/import.php`
- `Templates/edit.php` (the designer side panel — minimal changes needed)
- `Uploads/edit.php`
- `Profile/index.php`
- `Public/index.php`, `Public/unlock.php`
- `Admin/Settings/index.php`, `Admin/Users/edit.php`, `Admin/Cleanup/index.php`, `Admin/CallsignDirectory/index.php`
- `Cards/view.php` (share password input)

#### 1.3 Accessibility basics

- Wrap every flash message in `role="alert"` so screen readers announce it. Touch `templates/element/flash/*.php` (5 files).
- Add a "Skip to main content" link as the first focusable element in `templates/layout/default.php`. Hidden until focused (CSS).
- Add `aria-label` to every icon-only button:
  - `.btn-close` on the bulk-render modal → `aria-label="Close"`
  - `.navbar-toggler` → already has it ✓
  - Theme toggle button (added in Phase 4) → `aria-label="Toggle colour scheme"`
- Add `aria-live="polite"` to the callsign auto-complete confirmation `<p class="form-text text-success">` on `Qsos/add.php`. Screen readers then announce "Auto-filled from MCMC" as the value lands.
- Add `aria-live="polite"` to the bulk-render progress `<p>` so progress is announced.

#### Acceptance — Phase 1

- 0 `<img>` tags without `loading="lazy"` except the documented hero images.
- Every form input has either a meaningful placeholder OR a `.form-text` note (or both); required fields visibly marked.
- Skip-to-content link appears on first Tab press from anywhere.
- Flash messages announced by VoiceOver / NVDA.
- Tests green, smoke green.

---

### Phase 2 — Component extraction

Pull the duplicated markup into Cake `templates/element/ui/` partials. Every element is a single-file, single-purpose view fragment receiving its data via `$this->element('ui/X', [...])`.

#### Elements to create

| File | Purpose | Replaces inline in |
|---|---|---|
| `element/ui/callsign.php` | `<span class="callsign">{call}</span>` | Qsos/index, Qsos/view, Dashboard, Public/share, Cards/view, Admin/Cards, Admin/Users, Admin/Audit, Admin/Templates/pending, Qsos/import |
| `element/ui/badge_share_status.php` | shared / private / revoked badge based on `$card` | Cards/index, Cards/view, Admin/Cards/index |
| `element/ui/badge_qso_type.php` | `[NET]` badge with title tooltip when `$qso->qso_type === 'net'` | Qsos/index, Qsos/view |
| `element/ui/badge_transport.php` | transport badge when `Transport::isInternet($qso->transport)` | Qsos/index, Qsos/view |
| `element/ui/card_thumb.php` | `<img>` with `thumbPathFor` fallback + `loading="lazy"` + correct alt text | Cards/index, Dashboard, Admin/Cards |
| `element/ui/empty_state.php` | `<div class="alert alert-info">{message} [<a>{cta}</a>]</div>` | Cards/index, Templates/index, Uploads/index, Qsos/index, Admin/Templates/pending, Admin/Uploads/index, Dashboard (×2) |
| `element/ui/action_bar.php` | primary + secondary + cancel button row with consistent `gap-2 mt-4 flex-wrap` | every form's final row |
| `element/ui/page_header.php` | `<h1>{title}</h1><p>{lede}</p>` so the H1+lede styling fires uniformly | every top-level template |
| `element/ui/dl_item.php` | one `<dt class="col-sm-3">{term}</dt><dd class="col-sm-9">{value or em-dash}</dd>` pair | Qsos/view, Cards/view, Public/share, Admin/Users/edit |

#### Element interface examples

```php
// element/ui/callsign.php
<span class="callsign"><?= h($call ?? '—') ?></span>

// element/ui/badge_share_status.php
<?php if ($card->share_revoked_at): ?>
  <span class="badge bg-secondary">Share revoked</span>
<?php elseif ($card->share_slug): ?>
  <span class="badge bg-success">Shared</span>
<?php else: ?>
  <span class="badge bg-light">Private</span>
<?php endif; ?>

// element/ui/empty_state.php
<div class="alert alert-info">
  <?= h($message) ?>
  <?php if (!empty($cta_url)): ?>
    <a href="<?= h($cta_url) ?>"><?= h($cta_label) ?> &rarr;</a>
  <?php endif; ?>
</div>

// Calling convention:
<?= $this->element('ui/callsign', ['call' => $qso->call_worked]) ?>
<?= $this->element('ui/empty_state', [
    'message'  => "You haven't generated any cards yet.",
    'cta_url'  => '/qsos',
    'cta_label' => 'Render one from a QSO',
]) ?>
```

#### Why Cake elements (not cells, not view helpers)

- Elements are dumb view fragments — no controller logic. Right tool for "render this snippet with these vars."
- Cells are for self-contained widgets with their own data-fetching (e.g. "show user's notification count" — fetches from DB). Overkill here.
- View helpers are for transformations on data (`$this->Text->truncate(...)`); not for HTML composition.

#### Acceptance — Phase 2

- 9 new files under `templates/element/ui/`.
- ~30 of the 41 templates updated to use elements where the pattern fits.
- No behaviour changes (PHPUnit + 23-route smoke remain green).
- Each element's full HTML is in exactly one file; future tweaks land once.

---

### Phase 3 — Responsive + deeper a11y

#### Manual viewport sweep

For each viewport (320, 640, 768, 1024, 1440 px), visit:

Public routes:
- `/`, `/login`, `/register`, `/password/forgot`, `/qsl/{slug}` (share)

Authenticated:
- `/dashboard`, `/qsos`, `/qsos/new`, `/qsos/import`, `/qsos/{id}`, `/qsos/{id}/render`
- `/templates`, `/templates/new`, `/templates/{id}/edit`
- `/cards`, `/cards/{id}`
- `/uploads`, `/uploads/{id}/edit`
- `/profile`

Admin:
- `/admin`, `/admin/settings`, `/admin/cleanup`, `/admin/users`, `/admin/cards`, `/admin/uploads`, `/admin/audit`, `/admin/callsign-directory`, `/admin/templates/pending`, `/admin/upgrade`

For each: note layout breakage in a checklist, fix in the same commit.

Expected problem areas (predict + plan):
- Qsos filter row at 640px (already responsive but worth re-verifying).
- Bulk-render modal at 320px (current `margin: 3px` already in place).
- Designer side panels at 768px — three columns squeezing each other.
- Long share URLs in Cards/view → `word-break: break-all` already applied.

#### Focus management

- **Bulk-render modal (Qsos/index):** when opened, focus moves into the template select; ESC closes (currently only the X button does); Tab cycles within the modal (focus trap); on close, focus returns to the trigger button.
  - Implementation: small Alpine helper `focusTrap` that listens for keydown.tab and bounces back to the first/last focusable when exiting either end.

- **Admin dropdown:** currently opens on hover and click. Confirm keyboard nav works: Enter / Space opens, arrow keys to move between items, Esc to close.

- **Template designer:** the canvas has its own keyboard handling (arrow keys to nudge selected field, Delete to remove). Verify Tab moves through the side-panel inputs without diving into the canvas.

#### Keyboard sweep

For each major flow, complete the task using **only** the keyboard:
- Sign in
- Add a QSO
- Render an eQSL
- Save the rendered card
- View the share link

Note any focus traps, missing skip links, unfocusable interactive elements.

#### Contrast audit

For every token combination used in `theme.css`, compute the contrast ratio:

| Foreground | Background | Required ratio | Action |
|---|---|---|---|
| `--fg` `#09090b` | `--bg` `#fafafa` | 4.5:1 normal | likely passes (~17:1) |
| `--fg-muted` `#52525b` | `--surface` `#ffffff` | 4.5:1 normal | likely passes (~7.7:1) |
| `--fg-subtle` `#71717a` | `--surface` `#ffffff` | 4.5:1 normal | borderline — check |
| `--accent-strong` `#047857` | `--surface` `#ffffff` | 4.5:1 normal | likely passes |
| `--accent` `#059669` | `--surface` `#ffffff` | 4.5:1 normal | borderline — check |
| white text on `--fg-strong` `#18181b` (primary button) | — | 4.5:1 | likely passes |
| `.btn-primary:focus-visible` ring | — | 3:1 vs surrounding | likely passes |

Document any failures and adjust either the token value or its usage.

#### Acceptance — Phase 3

- Every page rendered cleanly at 320, 640, 768, 1024, 1440 px (manually verified).
- Bulk-render modal traps Tab focus, closes on ESC, restores focus on close.
- Keyboard-only smoke through five major flows completes successfully.
- Every documented token combo meets WCAG AA.
- One new file at most (the focus trap helper), zero behaviour changes elsewhere.

---

### Phase 4 — Dark mode

The real feature in this spec.

#### Token expansion

Add dark counterparts to every visual token. Tokens kept in light mode get their dark values exposed via a `[data-theme="eqsl-dark"]` block in `theme.css`.

| Light token | Light value | Dark value | Notes |
|---|---|---|---|
| `--bg` | `#fafafa` | `#0a0a0a` | true zinc-950 base |
| `--surface` | `#ffffff` | `#18181b` | zinc-900 cards |
| `--surface-2` | `#f4f4f5` | `#27272a` | zinc-800 |
| `--surface-3` | `#e9e9eb` | `#3f3f46` | zinc-700 |
| `--border` | `#e4e4e7` | `#27272a` | borders disappear too much on pure dark; use a step lighter than surface |
| `--border-strong` | `#d4d4d8` | `#3f3f46` | |
| `--fg` | `#09090b` | `#fafafa` | reverse, plus a hair softer |
| `--fg-strong` | `#18181b` | `#ffffff` | for primary buttons (becomes white on dark) |
| `--fg-muted` | `#52525b` | `#a1a1aa` | zinc-400 |
| `--fg-subtle` | `#71717a` | `#71717a` | zinc-500 reads fine on both |
| `--accent` | `#059669` | `#10b981` | brighter so emerald shows up |
| `--accent-strong` | `#047857` | `#34d399` | hover state, even brighter |
| `--accent-soft` | `#ecfdf5` | `#064e3b` | inverted depth |
| `--accent-ring` | `rgba(5,150,105,0.32)` | `rgba(16,185,129,0.4)` | focus ring punchier on dark |
| `--info`, `--warning`, `--danger` | as-is | desaturate / brighten as needed | one-off audit |
| `--info-soft`, `--warning-soft`, `--danger-soft` | as-is | dark equivalents | invert |

Shadows on dark need different alpha (lighter, more subtle). Define a parallel `--sh-1-dark`, `--sh-2-dark`, etc., or override the existing tokens in the dark block.

#### DaisyUI theme registration

In `templates/layout/default.php`, expand the daisyui config to include both themes:

```js
daisyui: {
  themes: [
    {
      eqsl: {
        'color-scheme':         'light',
        primary:                '#18181b',
        'primary-content':      '#ffffff',
        accent:                 '#059669',
        // …existing tokens
      },
    },
    {
      'eqsl-dark': {
        'color-scheme':         'dark',
        primary:                '#fafafa',
        'primary-content':      '#18181b',
        accent:                 '#10b981',
        'base-100':             '#18181b',
        'base-200':             '#27272a',
        'base-300':             '#3f3f46',
        'base-content':         '#fafafa',
        // …
      },
    },
  ],
  darkTheme: 'eqsl-dark',
},
```

#### CSS dark-mode override

Append to `theme.css`:

```css
[data-theme="eqsl-dark"] {
  --bg:            #0a0a0a;
  --surface:       #18181b;
  --surface-2:     #27272a;
  --surface-3:     #3f3f46;
  --border:        #27272a;
  --border-strong: #3f3f46;
  --fg:            #fafafa;
  --fg-strong:     #ffffff;
  --fg-muted:      #a1a1aa;
  --fg-subtle:     #71717a;
  --accent:        #10b981;
  --accent-strong: #34d399;
  --accent-soft:   #064e3b;
  --accent-ring:   rgba(16, 185, 129, 0.4);
  --info-soft:     #082f49;
  --warning-soft:  #422006;
  --danger-soft:   #450a0a;
  --success-soft:  #064e3b;
  --sh-1: 0 1px 2px rgba(0, 0, 0, 0.4);
  --sh-2: 0 1px 3px rgba(0, 0, 0, 0.45), 0 1px 2px rgba(0, 0, 0, 0.3);
  --sh-3: 0 4px 8px rgba(0, 0, 0, 0.5), 0 2px 4px rgba(0, 0, 0, 0.3);
  --sh-4: 0 12px 28px rgba(0, 0, 0, 0.6), 0 4px 10px rgba(0, 0, 0, 0.4);
}
```

Because every component already pulls from `var(--…)`, this override is enough — no need to rewrite component rules.

#### Toggle UI

Sun/moon icon button at the right end of the navbar, just before the Sign-out item:

```html
<li class="nav-item">
  <button type="button"
          id="themeToggle"
          class="btn btn-link"
          aria-label="Toggle colour scheme"
          title="Toggle colour scheme">
    <!-- inline SVG: shows sun in dark mode, moon in light mode -->
  </button>
</li>
```

Three-state cycle on click: `light → dark → system → light`. Current state read from `<html data-theme>`. Icon swaps via `<svg>` content + a `data-mode` attribute.

#### Persistence + pre-paint script

Inline in `<head>` (must run before any CSS paints):

```html
<script>
  (function () {
    var pref = localStorage.getItem('eqsl-theme') || 'system';
    var resolved = pref === 'system'
      ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'eqsl-dark' : 'eqsl')
      : (pref === 'dark' ? 'eqsl-dark' : 'eqsl');
    document.documentElement.setAttribute('data-theme', resolved);
    document.documentElement.setAttribute('data-theme-pref', pref);
  })();
</script>
```

Storage key: `eqsl-theme` with three valid values: `'light'`, `'dark'`, `'system'`.

Why `<html>` not `<body>`: applies before the body element exists, so the very first paint is the right colour. Setting on `<body>` causes a flash of light theme on dark-mode users.

#### Visual QA matrix

For each of the 23 routes × 2 themes = 46 page renders, verify:

- Page background visible (not pure black void).
- Card borders visible (the `--border` step).
- All text passes 4.5:1 contrast against its background.
- Card preview shadow still has depth on dark (lighten the shadow or invert).
- Badges readable in both modes.
- Forms / inputs match the page surface (no jarring contrast).
- Alerts (info / success / warning / danger) still convey their severity.
- Code blocks readable.

#### Acceptance — Phase 4

- Toggle button cycles three states, persists across page loads.
- Default for new users follows `prefers-color-scheme`.
- No FOUC on initial paint (verified by reloading 5× on slow throttling).
- All 46 page renders pass contrast + visibility checks.
- One new inline `<script>` in `<head>`, one expanded daisyui config, one CSS override block in `theme.css`, one new toggle element in the navbar. Nothing else changes.

---

### Phase 5 — Production CSS build

Replace the Tailwind Play CDN with a pre-compiled bundle. Runs on the developer's laptop only — the shared host still has no Node.

#### Why bother

- Tailwind Play CDN ships an ~80KB compiler that runs in the browser. Wastes bandwidth, slows first paint, shows brief FOUC.
- The Tailwind project explicitly recommends against Play CDN in production.
- Compiling locally produces a ~15–25KB minified CSS bundle (PurgeCSS removes unused classes).

#### New files

- `tailwind.config.js` — content paths point at `templates/**/*.php` and `webroot/js/*.js`. theme.extend mirrors what's currently inline in `default.php`.
- `src/css/tailwind-source.css` — entry point:
  ```css
  @tailwind base;
  @tailwind components;
  @tailwind utilities;
  @import './theme.css';   /* our brand layer */
  ```
  (Need to confirm if @import works with the Tailwind CLI; if not, concatenate via a build script.)
- `package.json` script: `"build:css": "tailwindcss -i src/css/tailwind-source.css -o webroot/css/dist.css --minify"`
- Add `daisyui` (matching the CDN version 4.12.14) to `devDependencies`.

#### Tailwind config (sketch)

```js
module.exports = {
  content: [
    './templates/**/*.php',
    './webroot/js/**/*.js',
    './webroot/css/theme.css',
  ],
  safelist: [
    // Alpine-driven classes that don't appear in static markup
    'show', 'is-active', 'btn-active', 'hidden',
    { pattern: /^(alert|btn|badge|card|nav-link)-(primary|secondary|success|info|warning|danger|light|dark)$/ },
  ],
  theme: {
    extend: {
      colors: {
        ink:   '#09090b',
        paper: '#fafafa',
      },
      fontFamily: {
        sans: ['"Inter Variable"', 'Inter', 'system-ui', 'sans-serif'],
        mono: ['"Geist Mono Variable"', 'ui-monospace', 'monospace'],
      },
    },
  },
  plugins: [require('daisyui')],
  daisyui: {
    themes: [
      { eqsl: { /* …same as CDN config… */ } },
      { 'eqsl-dark': { /* …same as Phase 4 config… */ } },
    ],
    darkTheme: 'eqsl-dark',
  },
};
```

#### Layout changes

Remove from `templates/layout/default.php`:

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css">
<script src="https://cdn.tailwindcss.com/3.4.3"></script>
<script>tailwind.config = { /* …big inline block… */ };</script>
```

Replace with:

```html
<link rel="stylesheet" href="<?= $this->Url->build('/css/dist.css') ?>?v=<?= filemtime(WWW_ROOT . 'css/dist.css') ?>">
```

#### Build flow

- **Developer:** `npm install` once, then `npm run build:css` before any commit that changes templates or CSS. Result `webroot/css/dist.css` is committed to git.
- **CI option (optional, deferred):** add a `build` step to `.github/workflows/deploy.yml` that runs `npm install && npm run build:css` before the FTP push. Saves the developer one manual step but adds Node to CI. Recommend keeping it local for now.
- **Watch mode for dev:** `tailwindcss -i … -o … --watch` rebuilds on file change.

#### CSP cleanup

Update `src/Middleware/SecurityHeadersMiddleware.php`:

- Remove `https://cdn.tailwindcss.com` from `script-src`.
- Remove `https://cdn.tailwindcss.com` from `style-src`.
- Keep `https://cdn.jsdelivr.net` (still needed for fonts + Alpine).

#### Acceptance — Phase 5

- No `cdn.tailwindcss.com` script tag in the served HTML.
- `webroot/css/dist.css` exists, is < 50KB minified.
- Every page renders identically to the Play CDN version.
- CSP no longer whitelists `cdn.tailwindcss.com`.
- README documents the `npm run build:css` step.

---

## Cross-phase concerns

### Testing

- PHPUnit suite (329 tests, 1062 assertions) stays green throughout. Zero behaviour changes expected from any phase.
- After each phase: smoke-test all 23 routes return 200 logged in + 200 / 302 logged out as appropriate.
- For Phase 4 specifically: screenshot every page in both themes for the PR description / commit message.
- For Phase 5 specifically: render every page in production-build mode locally before pushing.

### Commit cadence

- Phase 1 → 1 commit per sub-section (1.1 / 1.2 / 1.3), so 3 small commits.
- Phase 2 → 1 commit (all elements added at once, all templates updated in the same change so reviewers can verify nothing's left half-converted).
- Phase 3 → 1 commit for the responsive sweep, 1 for the focus-trap helper, 1 for any contrast fixes. Up to 3 commits.
- Phase 4 → 1 commit for tokens + override, 1 for toggle UI + persistence, 1 for any per-component fixes uncovered during visual QA. Up to 3 commits.
- Phase 5 → 1 commit.

Total estimated: 10–13 commits.

### Risks

- **Phase 2** touches 41 templates mechanically. Risk of typos breaking pages. Mitigate with smoke after each batch of ~10 files.
- **Phase 3** is partly subjective (what counts as "broken" at 320px). Document each issue + fix in commit messages so reviewers can verify.
- **Phase 4** is the real iteration risk. Dark mode will look wrong on first try for at least 2–3 components. Budget extra time + visual QA.
- **Phase 5** has Tailwind purge-trap risk: classes that only appear at runtime (added by Alpine `:class="..."`) won't be in the static markup and may get purged. Mitigate with explicit safelist patterns (already drafted above).

---

## Decisions captured (so reviewers don't re-litigate)

1. **No React, no shadcn-the-library.** Stack stays CakePHP-server-rendered.
2. **Three-state theme toggle** (light → dark → system) rather than two-state. Lets users explicitly pin a choice without losing the "follow OS" option.
3. **localStorage over cookie** for theme. No server roundtrip needed; persists across browsers if synced (e.g. Chrome Sync); zero impact on caching.
4. **`data-theme` on `<html>`** not `<body>` — applies before body exists so first paint is correct.
5. **Inline pre-paint script** in `<head>`. CSP already allows `'unsafe-inline'` for scripts (because of CakePHP's `Form->postLink` inline onclick). No new CSP relaxation required.
6. **Cake elements over view cells** for the component layer. Elements are dumb view fragments; cells are mini-controllers. The repeated patterns here are dumb.
7. **Build runs locally**, not in CI. The deploy workflow stays simple; the developer runs `npm run build:css` before committing. CI option deferred until we have a clear need.
8. **PurgeCSS via Tailwind's content scan**, with a small safelist for the Alpine-driven dynamic classes documented above.

---

## Open questions for the reviewer

None at write-time. If anything reads ambiguously, flag in review.
