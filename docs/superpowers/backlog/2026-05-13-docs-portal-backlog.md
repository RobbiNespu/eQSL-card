# Docs portal — post-launch backlog

**Date filed:** 2026-05-13
**Related plan:** `docs/superpowers/plans/2026-05-12-docs-portal-plan.md`
**Related spec:** `docs/superpowers/specs/2026-05-12-docs-portal-design.md`

The docs portal v1 shipped with 11 fully-written articles (10 from the plan + the
"About + credits" page written separately) and 13 stub articles. All 24 routes
resolve to a 200 page with the right chrome; stubs render a "coming soon" Note
callout. This file captures the remaining work so nothing falls through the
cracks.

---

## 1. Stub articles still to write

These 13 articles ship in the sidebar with a "coming soon" Note. Filling them
in is incremental — each is its own self-contained file under
`templates/Help/<category>/<slug>.php` that extends `/Help/view`. Pattern from
the existing v1 articles applies:

```php
<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', '<Article title> — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="<one-sentence description>">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => '<lede paragraph>',
]) ?>

<!-- sections, screenshots, callouts -->
```

### Logging (2 stubs left)

| Slug | Estimated length | What to cover |
|---|---|---|
| `logging/net-checkins` | ~400 words | The Net check-in QSO type in depth. NCS workflow, the three net fields (NCS callsign, net title, organisation), example cards, why the NCS issues the card (not the participant). Cross-link from add-qso's "Two QSO types" section. |
| `logging/autocomplete` | ~400 words | Callsign auto-complete walk-through. The 700ms debounce, the cache, the per-provider on/off toggle. Provider list: local CSV directory, RadioID, QRZ (stub), MCMC (Malaysia), MARTS (stub), RAPI (stub). How to import a CSV via `/admin/callsign-directory`. |

### Cards (2 stubs left)

| Slug | Estimated length | What to cover |
|---|---|---|
| `cards/bulk-render` | ~500 words | Select multiple QSOs from the logbook, open the bulk-render modal, pick template + background, watch progress. Skip rules (QSOs that already have a card). Throttling. |
| `cards/download` | ~250 words | The three download formats (PNG image, PDF wrapper, PNG via share link). Print considerations. Filename format. |

### Templates (2 stubs left)

| Slug | Estimated length | What to cover |
|---|---|---|
| `templates/designer` | ~700 words | The visual designer in depth. Adding fields, dragging to position, the placeholder syntax (`{callsign}`, `{operator_callsign}`, `{qso_datetime_utc:Y-m-d}`, etc.), font selection, outline + shadow styling. Save flow, what gets stored. |
| `templates/submit-public` | ~300 words | The public submission lifecycle. Tick "Make public" in the designer's save dialog, what the admin sees, approval criteria, what rejected reasons look like, how to re-submit after a rejection. |

### Admin (6 stubs left)

| Slug | Estimated length | What to cover |
|---|---|---|
| `admin/settings` | ~600 words | Each Settings card — default background, SMTP, eQSL credit footer template (with `{year}` / `{generated_at}` placeholders), Security (rate-limit bypass), Callsign auto-complete enable + providers, Storage retention. |
| `admin/users` | ~300 words | List view, role badges (admin / user), search, edit (role change with self-demotion lock), soft-delete. Promoting an existing user to admin. |
| `admin/cleanup` | ~400 words | The four cleanup actions: purge guest cards older than N days, prune orphaned uploads, expire old user cards (retention-driven), filesystem maintenance (cache / logs / sessions / callsign cache). Warnings on each. |
| `admin/callsign-dir` | ~400 words | The admin-imported CSV directory. Supported header aliases (the long list from the help text on the page). Per-import "source label" for traceability. Clearing the directory. |
| `admin/audit` | ~250 words | The append-only audit log. Event types, filtering by event / actor, what each log entry captures (actor user or guest visit, target type + id, metadata JSON). |
| `admin/migrations` | ~250 words | The `/admin/upgrade` page. When to use it (after FTP-deploying a new release), what `status` shows, what "Apply pending migrations" does, the post-apply cache flush. |

### Reference (1 stub left)

| Slug | Estimated length | What to cover |
|---|---|---|
| `reference/troubleshooting` | ~600 words | Common issues + fixes: "I can't sign in" (verify email link, password reset, rate-limit bypass for local IPs), "image upload too large" (`max_upload_mb` setting), "the card looks wrong" (template + background fit, FPDF transcoding), "I'm locked out as admin" (SQL recovery procedure documented on `/admin/settings`), "callsign auto-complete returns nothing" (provider settings, cache). |

### Already written

- `getting-started/welcome` ✓
- `getting-started/create-account` ✓
- `getting-started/first-card` ✓ (Mermaid flowchart)
- `logging/add-qso` ✓
- `logging/import` ✓
- `cards/render` ✓
- `cards/share` ✓ (Mermaid sequence diagram)
- `templates/overview` ✓
- `admin/install` ✓
- `reference/glossary` ✓
- `reference/about` ✓

---

## 2. Screenshot captures still to take

All v1 articles reference `.webp` files under `webroot/files/help/<category>/<slug>/`.
The directories don't exist yet (only `webroot/files/help/.gitkeep` is committed).
Articles render the broken-image icon next to the alt text until the files land.

### Capture process

1. Run the app locally (`docker compose up -d`).
2. Navigate to the screen the article references.
3. Capture just the relevant area (Flameshot / macOS Cmd+Shift+4 / Windows Snip).
4. Convert + optimise to WebP at quality 82:
   ```bash
   cwebp -q 82 input.png -o output.webp
   ```
5. Drop into `webroot/files/help/<category>/<slug>/<filename>.webp`.
6. Commit.

### List of screenshots referenced in v1 articles

(Filename suggested; alt text in each article describes what should be visible.)

| Path | Article | What to show |
|---|---|---|
| `webroot/files/help/getting-started/welcome/sample-card.webp` | welcome | A generated eQSL card with operator + contact callsigns, QSO details on a background |
| `webroot/files/help/getting-started/create-account/register-form.webp` | create-account | Empty registration form |
| `webroot/files/help/getting-started/first-card/qso-form.webp` | first-card | QSO form filled in with sample data |
| `webroot/files/help/getting-started/first-card/render-form.webp` | first-card | Render form with template + background pickers |
| `webroot/files/help/getting-started/first-card/generated-card.webp` | first-card | Card view page with Download/Share buttons |
| `webroot/files/help/logging/add-qso/form-empty.webp` | add-qso | Empty QSO form, Contact mode selected |
| `webroot/files/help/logging/import/upload-form.webp` | import | Import upload form |
| `webroot/files/help/logging/import/preview-screen.webp` | import | Preview after parse with valid/duplicate/invalid counts |
| `webroot/files/help/cards/render/template-picker.webp` | cards/render | Template radio cards |
| `webroot/files/help/cards/render/background-picker.webp` | cards/render | Background picker with previous uploads |
| `webroot/files/help/cards/render/generated.webp` | cards/render | Generated card view page |
| `webroot/files/help/cards/share/share-toggle.webp` | cards/share | Sharing block with password field |
| `webroot/files/help/cards/share/public-view.webp` | cards/share | Recipient's view of the shared card |
| `webroot/files/help/templates/overview/templates-page.webp` | templates/overview | Templates listing with 3 tabs |
| `webroot/files/help/templates/overview/template-card.webp` | templates/overview | One template card with action buttons |
| `webroot/files/help/admin/install/stage-1-syscheck.webp` | admin/install | System check, all green |
| `webroot/files/help/admin/install/stage-2-db.webp` | admin/install | Database config form |
| `webroot/files/help/admin/install/stage-3-admin.webp` | admin/install | First-admin form |
| `webroot/files/help/admin/install/stage-4-done.webp` | admin/install | Setup complete screen |
| `webroot/files/help/admin/install/syscheck-failure.webp` | admin/install | System check with red crosses |

Total: 20 screenshots. None are above-the-fold critical (all are inline within
article bodies), so the portal is shippable without them.

### Optional dark-mode variants

The `ui/screenshot` element supports an optional `darkSrc` second image that
swaps in when `[data-theme="eqsl-dark"]` is active. Adding dark variants is a
"nice to have, after launch" task — none of the articles currently pass
`darkSrc`. If you want full dark-mode parity, capture each screenshot twice
(once per theme) and update the article's `screenshot` element call:

```php
<?= $this->element('ui/screenshot', [
    'src'     => '/files/help/cards/render/template-picker.webp',
    'darkSrc' => '/files/help/cards/render/template-picker.dark.webp',
    'alt'     => '...',
    'caption' => '...',
]) ?>
```

---

## 3. Other follow-ups

### a) `webroot/css/app.css` could be retired

`app.css` has only 3 rules left:

```css
.card-preview { max-width: 100%; height: auto; box-shadow: 0 4px 16px rgba(0,0,0,.1); border-radius: .5rem; }
.field-error { color: #b91c1c; font-size: .875rem; }
[x-cloak] { display: none !important; }
```

All three are already present (and better-styled, using design tokens) in
`theme.css`. App.css is essentially dead code kept as a separate `<link>` for
historical reasons. A small cleanup commit could:

1. Delete `webroot/css/app.css`.
2. Drop the `<link>` tag from `templates/layout/default.php`.
3. Confirm nothing visually changes (the `theme.css` versions take over via
   identical class selectors).

Cache buster on `app.css` was added on 2026-05-13 (this commit) for
correctness, but retiring the file is the cleaner long-term move.

### b) `templates/layout/error.php` cache buster

The error layout still uses Cake's `HtmlHelper::css(['normalize.min', ...])`
without a cache-buster timestamp. These are CakePHP scaffold leftovers
(`normalize.min.css`, `milligram.min.css`, `fonts.css`, `cake.css`) only ever
shown on 4xx / 5xx error pages, so the impact is negligible. If you replace
the error template with the app's normal theme anyway (which would be a nicer
UX), the question goes away. Otherwise, the helper supports a `?fullBase` /
`?timestamp` option in CakePHP 5; flip it on if you ever care.

### c) Dark mode visual QA pass on the docs portal

The Phase 4 visual QA from the UI audit covered the 23 app routes but not the
new `/help` routes. Once screenshots land (light variants at minimum), do a
full dark-mode sweep across every article and check:

- Article text contrast against `--bg`
- Sidebar active-page background (`--accent-soft`) reads OK on dark
- Mermaid diagram colours (the `theme: 'dark'` switch is wired in
  `Help/view.php`'s opt-in script; verify it actually fires)
- Callout border-left colour visible in dark mode
- Screenshot border (`--border-strong`) frames images correctly

### d) Per-article PHPUnit smoke

`HelpControllerTest` covers index + a known article (`getting-started/welcome`)
+ 404 cases. A data-provider test that asserts every entry in
`HelpCatalog::TREE` returns 200 would catch typos in future articles. Cheap
addition; not blocking.

### e) Search

The docs portal has no built-in search — Ctrl+F handles the within-page case,
and the sidebar covers cross-article navigation. If/when v2 ships, options
include a simple PHP search over the article files or a small Lunr.js
client-side index. Deferred for now.

### f) Sitemap.xml

For SEO, each article has its own meta description + title. A
sitemap.xml at `/sitemap.xml` listing all 24 help URLs would help search
engines discover them faster. The `HelpCatalog::allPages()` generator
provides the data; a small controller action would suffice.

### g) Translated articles (i18n)

eQSL Card is currently English-only. Translating articles to Malay (the
maintainer's first language) would be a natural first step. CakePHP's i18n
plumbing is in place but the articles aren't currently wrapped in `__()`
calls — translation pass would need a one-time conversion of the prose
into Cake-translatable strings, or a `Help/<locale>/<category>/<slug>.php`
parallel directory.

---

## Tracking

When a stub article gets written, move its row from "Stub articles still to
write" to "Already written" above. When a screenshot lands, tick it off in
section 2. New follow-ups go in section 3.

When this file gets long enough to be unwieldy, split per category into
`docs/superpowers/backlog/2026-XX-XX-<category>.md`.
