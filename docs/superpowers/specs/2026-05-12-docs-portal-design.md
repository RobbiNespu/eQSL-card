# eQSL Card — Documentation Portal (Design Spec)

**Date:** 2026-05-12
**Status:** Draft — pending review
**Scope:** A built-in `/help` portal — public, two-pane (sidebar + content), authored as static PHP templates, with diagram + screenshot support.

---

## Goals

1. **Self-serve onboarding.** A new visitor lands on `/help`, reads the "Welcome" + "Quick start", and is ready to generate their first eQSL card without external support.
2. **Visual explanations.** Workflows (logging → render → share, install + first-user, the QSO data flow) are shown with flowcharts and screenshots, not just paragraphs.
3. **Audience parity.** End users (operators) and admins both find what they need without separate sub-portals — admin-flavoured topics live in their own sidebar category.
4. **No new external dependencies that violate the no-compile / shared-hosting constraint.** Same FTP-only deploy story.
5. **Consistent with the rest of the app.** Reuses the `ui/page_header`, `ui/empty_state`, dark-mode tokens, focus ring, etc. that the UI audit just shipped.

## Non-goals

- Markdown authoring (the brainstorm chose static PHP templates instead).
- Database-backed CMS with WYSIWYG editor.
- Full-text search across docs (v2 or later — `<kbd>Ctrl+F</kbd>` is fine for v1).
- Versioning of doc pages (evergreen content; release-tagged docs are v2).
- i18n / multilingual content (v1 is English-only).
- External docs site (mkdocs / Docusaurus / Astro). Lives in-app.

---

## Decisions captured from brainstorm

1. **Authoring:** Each docs page is a CakePHP template at `templates/Help/<slug>.php`. Can use existing `ui/*` elements. No markdown parser.
2. **Access:** Public; no auth middleware on the docs controller. Routes are SEO-indexable.
3. **Layout:** Two-pane. Persistent sidebar (collapsible on mobile) lists every page grouped by category. Main area renders the current page.
4. **Entry:** New "Help" link in the navbar (between Templates and the theme toggle). Footer also gets a link.
5. **Route style:** `/help` (index), `/help/<category>/<page-slug>` for individual articles. Pretty URLs.

## Architecture

```
Browser GET /help/logging/add-qso
    │
    ▼
HelpController::view($category, $slug)
    │  validates ($category, $slug) is a known doc in App\Service\HelpCatalog
    │
    ▼
templates/Help/view.php
    │  renders the two-pane layout — sidebar (driven by HelpCatalog) + content
    │
    ▼
templates/Help/logging/add-qso.php
    │  the actual article. Plain PHP/HTML using ui/* elements.
```

### App\Service\HelpCatalog

A single PHP file declaring the whole IA as a nested array. Drives sidebar rendering, breadcrumbs, "previous / next" links, and the controller's allow-list of valid `(category, slug)` pairs.

```php
final class HelpCatalog {
    public const TREE = [
        'getting-started' => [
            'label' => 'Getting started',
            'pages' => [
                'welcome'         => 'Welcome to eQSL Card',
                'create-account'  => 'Create an account',
                'first-card'      => 'Your first eQSL card (5-min quick start)',
            ],
        ],
        'logging' => [
            'label' => 'Logging QSOs',
            'pages' => [
                'add-qso'         => 'Log a contact',
                'import'          => 'Import an ADIF / CSV log',
                'net-checkins'    => 'Logging net check-ins',
                'autocomplete'    => 'Callsign auto-complete',
            ],
        ],
        'cards' => [
            'label' => 'Cards & sharing',
            'pages' => [
                'render'          => 'Generate an eQSL card',
                'bulk-render'     => 'Bulk-render many cards',
                'share'           => 'Share a card publicly',
                'download'        => 'Download as image or PDF',
            ],
        ],
        'templates' => [
            'label' => 'Templates',
            'pages' => [
                'overview'        => 'How templates work',
                'designer'        => 'Design your own template',
                'submit-public'   => 'Submit a template to the gallery',
            ],
        ],
        'admin' => [
            'label' => 'Admin guide',
            'pages' => [
                'install'         => 'First-time install + setup',
                'settings'        => 'Site settings (background, SMTP, retention)',
                'users'           => 'User management',
                'cleanup'         => 'Storage cleanup',
                'callsign-dir'    => 'Callsign directory CSV upload',
                'audit'           => 'Audit log',
                'migrations'      => 'Running migrations after a deploy',
            ],
        ],
        'reference' => [
            'label' => 'Reference',
            'pages' => [
                'glossary'        => 'Glossary',
                'troubleshooting' => 'Troubleshooting',
                'about'           => 'About + credits',
            ],
        ],
    ];

    public static function exists(string $category, string $slug): bool { /* allow-list lookup */ }
    public static function pageLabel(string $category, string $slug): string { /* … */ }
    public static function neighbours(string $category, string $slug): array { /* prev/next */ }
}
```

### Routes

```php
$builder->scope('/help', function ($routes) {
    $routes->connect('/',                          ['controller' => 'Help', 'action' => 'index']);
    $routes->connect('/{category}/{slug}', /*…*/   ['controller' => 'Help', 'action' => 'view'])
        ->setPatterns(['category' => '[a-z-]+', 'slug' => '[a-z0-9-]+']);
});
```

The controller validates `(category, slug)` against `HelpCatalog::exists()` and throws 404 otherwise — prevents directory-traversal via crafted slugs.

### Template structure

```
templates/Help/
├── index.php                       # /help — landing with the table of contents
├── view.php                        # wrapper for individual pages — renders the
│                                   # sidebar via HelpCatalog::TREE + slot for content
├── getting-started/
│   ├── welcome.php
│   ├── create-account.php
│   └── first-card.php
├── logging/
│   ├── add-qso.php
│   ├── import.php
│   ├── net-checkins.php
│   └── autocomplete.php
├── cards/
│   ├── render.php
│   ├── bulk-render.php
│   ├── share.php
│   └── download.php
├── templates/
│   ├── overview.php
│   ├── designer.php
│   └── submit-public.php
├── admin/
│   ├── install.php
│   ├── settings.php
│   ├── users.php
│   ├── cleanup.php
│   ├── callsign-dir.php
│   ├── audit.php
│   └── migrations.php
└── reference/
    ├── glossary.php
    ├── troubleshooting.php
    └── about.php
```

24 article pages + 1 index + 1 wrapper template. Each article is a small file (50–200 lines of PHP/HTML).

---

## v1 content scope

24 pages is a lot to author up front. For v1, I propose shipping a **minimum viable set** of 10 pages and stubbing the others with a "coming soon" placeholder that still appears in the sidebar — so the IA is correct from day one and stubs fill in over time.

### v1 — write in full

1. `getting-started/welcome` — what eQSL Card is, who it's for, screenshot of a generated card.
2. `getting-started/create-account` — registration walkthrough with the new form screenshot.
3. `getting-started/first-card` — quick-start tutorial from sign-up → render → download. Includes a Mermaid flowchart of the user flow.
4. `logging/add-qso` — form walkthrough, what each field means, screenshot of the QSO form.
5. `logging/import` — ADIF / CSV upload flow, supported headers, screenshot of the import preview screen.
6. `cards/render` — pick template + background + generate. Screenshot of the render form.
7. `cards/share` — share toggle, password protection, revoke. Sequence diagram of the public-share request flow.
8. `templates/overview` — system vs public vs personal, browsing the gallery, cloning.
9. `admin/install` — install wizard + first-user setup. Step-by-step with screenshots of each install stage.
10. `reference/glossary` — DX, QSO, NCS, QTH, RST, eQSL, ADIF, etc. Useful for newcomers who don't speak amateur radio yet.

### v1 — stub with "coming soon"

The remaining 14 pages exist as stub templates (visible in the sidebar, but the page renders a simple "This guide hasn't been written yet — see [Welcome] for the basics or contact the admin"). This way the IA is final from day one and stubs upgrade to full content over time without changing routes.

This split lets us ship the docs portal in one commit-set without writing 24 articles before launch.

---

## Visual design

Per the brainstorm, the portal reuses the existing design system. Concretely:

- **Page header:** `<?= $this->element('ui/page_header', ['title' => …, 'lede' => …]) ?>`.
- **Sidebar:** dedicated `.help-sidebar` block in `theme.css`. Sticky on desktop (`position: sticky; top: var(--s-7)`), full-width and `<details>`-collapsible on mobile.
- **Content area:** constrained to `max-width: 70ch` for readability of long-form prose.
- **Headings inside articles:** use the existing h1–h6 styling. H1 is the article title; h2 introduces major sections.
- **Inline code, kbd, pre:** existing `theme.css` rules already cover these — `font-family: Geist Mono`, mono code, etc.
- **Tables:** existing `.table` style with the rounded border + striped rows.
- **Callouts:** introduce a new helper element `ui/callout.php` for "Note:" / "Tip:" / "Warning:" boxes inside articles. Renders an `alert-info` / `alert-warning` / etc. with an emoji prefix.
- **Screenshots:** inline `<img>` with `loading="lazy"` + `class="img-fluid rounded border"`. Captions in a small `<figcaption>` below.
- **Dark mode:** everything inherits the existing token system; screenshots may need a swap (see below).

### Sidebar IA on desktop

```
┌─────────────────────────┬──────────────────────────────────────┐
│  HELP                   │                                      │
│                         │  <H1> Add a QSO                      │
│  Getting started        │  <P> The QSO form is the heart of …  │
│    Welcome              │                                      │
│  > Create an account    │  [screenshot]                        │
│    Your first eQSL      │                                      │
│                         │  <H2> Required fields                │
│  Logging QSOs           │  …                                   │
│  > Add a QSO  *active*  │                                      │
│    Import ADIF / CSV    │  ← Previous (Welcome)                │
│    Net check-ins        │  Next (Import ADIF) →                │
│    Callsign auto-comp.  │                                      │
│  …                      │                                      │
└─────────────────────────┴──────────────────────────────────────┘
```

### Sidebar on mobile

The sidebar becomes a `<details>` at the top of the page with the current category + page name as the summary. Tap to expand the full TOC; tap a link to navigate.

```
┌────────────────────────────────────────────┐
│  ▸ Help · Logging QSOs · Add a QSO         │
└────────────────────────────────────────────┘
   (the page content below)
```

---

## Diagram rendering

Many docs pages benefit from a flowchart or sequence diagram. Three options were considered:

### Chosen: Mermaid via CDN, lazy-loaded only on pages that use it

- Author writes a `<pre class="mermaid">` block with Mermaid syntax inside any docs article.
- The article opts in by setting `$this->set('useMermaid', true)` (or by including a `<?php $this->start('script'); ?>` block that loads the CDN).
- `templates/Help/view.php` only includes the Mermaid CDN `<script>` when `useMermaid` is true on the page.
- CSP already allows `https://cdn.jsdelivr.net` — no new policy.
- Mermaid renders on the client; HTML output is portable, no server work.

### Alternative considered: inline SVG

Hand-authored `<svg>` blocks. Higher authoring cost, but no JS dependency and renders the same in print. Worth using for one-off complex diagrams that Mermaid can't express, but not as the default.

### Alternative considered: PNG / WebP screenshots

Author draws a diagram in Excalidraw / Figma / Mermaid Live Editor and exports a PNG. Heavier on storage; diagrams can't be re-themed for dark mode. Useful for visual mockups that aren't really diagrams.

### Recommendation: mix

- Mermaid for flowcharts + sequence diagrams that are simple enough to express in Mermaid syntax.
- Inline SVG for the one-off architectural overview (if any).
- PNG / WebP screenshots ONLY for screenshots of the actual UI — never for hand-drawn diagrams that Mermaid could do.

---

## Screenshots strategy

### Storage

- All docs screenshots live under `webroot/files/help/<category>/<slug>/`.
- Filenames are descriptive: `qso-form-empty.webp`, `qso-form-filled.webp`, etc.
- WebP at quality 82 to match the rest of the app.
- Each screenshot has an explicit `alt` attribute describing the UI shown.

### Light / dark variants

For screens that look obviously different in dark mode (most of them), capture BOTH variants:
- `qso-form-empty.webp` (light)
- `qso-form-empty.dark.webp` (dark)

Render via a small helper element:

```php
<?= $this->element('ui/screenshot', [
    'src' => '/files/help/logging/add-qso/qso-form-empty.webp',
    'alt' => 'Empty QSO log form with required fields marked',
    'caption' => 'The QSO form on first load.',
]) ?>
```

The element emits both `<img>` variants with a `picture` + media-query swap based on `prefers-color-scheme`, OR uses `[data-theme="eqsl-dark"]` CSS to swap the `<img src>` via `content:` on a `::before` pseudo. Choice between the two lands during implementation.

### Authoring workflow

1. Run the app, log in, navigate to the screen.
2. Use a screenshot tool (Flameshot / macOS Cmd+Shift+4 / Windows Snipping Tool) to capture just the relevant area.
3. Optimise via `cwebp -q 82 <file>.png -o <file>.webp` locally (no in-app pipeline).
4. Drop into `webroot/files/help/<category>/<slug>/`, commit.

No server-side image processing for help screenshots — they're authored once and rarely change.

---

## Navigation integration

### Navbar

The "Help" link gets added between "Templates" and the theme toggle:

```php
<li class="nav-item"><a class="nav-link" href="/templates">Templates</a></li>
<li class="nav-item"><a class="nav-link" href="/help">Help</a></li>
<li class="nav-item"><!-- theme toggle --></li>
```

For logged-out visitors, "Help" appears between "Sign in" and "Create account":

```php
<li class="nav-item"><a class="nav-link" href="/login">Sign in</a></li>
<li class="nav-item"><a class="nav-link" href="/help">Help</a></li>
<li class="nav-item"><a class="nav-link btn btn-primary text-white" href="/register">Create account</a></li>
```

### Footer

The footer gets a "Help" link added next to the existing attribution. Same target.

### Contextual deep links

In-app pages link directly to relevant help articles where appropriate. Examples:
- The QSO add form has a small "How does this work? →" link to `/help/logging/add-qso` in its lede.
- The template designer has "Designer guide →" linking to `/help/templates/designer`.
- The admin install wizard's final step links to `/help/admin/install` for "what next".

Sprinkle judiciously — don't pollute every form with help links.

---

## SEO + meta

Each docs page sets its own `<title>` and `<meta name="description">` via Cake's view block helpers:

```php
<?php
$this->assign('title', 'Add a QSO — eQSL Card Help');
$this->start('meta');
?>
<meta name="description" content="Step-by-step guide to logging a new QSO in eQSL Card — required fields, date/time UTC, frequency, mode, and the callsign auto-complete.">
<?php $this->end(); ?>
```

Result: each article has a unique title + description for search engines, social-share unfurls, and bookmark display.

`/help` index page gets a sitemap-style listing so crawlers find every article. Optional XML sitemap at `/sitemap.xml` is a v2 concern.

---

## Implementation outline (high level — actual task list lives in the plan)

1. **Routes:** add `/help` + `/help/<category>/<slug>` to `config/routes.php`.
2. **HelpController:** two actions (`index`, `view`) with allow-list validation via `HelpCatalog`.
3. **App\Service\HelpCatalog:** the IA constant + helper methods (`exists`, `pageLabel`, `neighbours`).
4. **Templates/Help/view.php:** two-pane layout with sidebar element.
5. **element/ui/help_sidebar.php:** sidebar rendered from `HelpCatalog::TREE` with active-page highlighting.
6. **element/ui/callout.php:** Note / Tip / Warning boxes.
7. **element/ui/screenshot.php:** light / dark variant `<img>` wrapper.
8. **theme.css additions:** `.help-sidebar` block, `.help-content` max-width, `.callout` variants.
9. **Mermaid integration:** opt-in script load in `Help/view.php` when `$useMermaid` is true.
10. **Navbar + footer:** add the "Help" entry.
11. **10 v1 articles + 14 stubs:** the actual content.
12. **Screenshots:** capture the 10–15 screenshots needed for v1.
13. **PHPUnit coverage:** `HelpControllerTest` exercising the 404 path + a smoke-render of each v1 article.

Estimated effort: comparable to the UI audit — maybe 12-15 tasks across 2-3 phases.

---

## Risks

1. **Authoring fatigue.** 10 articles is real writing work. The stub-and-fill strategy mitigates this by letting the portal ship as soon as the infrastructure + 10 priority pages are done.
2. **Screenshot drift.** UI changes will make screenshots stale. Mitigation: a single `webroot/files/help/` lives under git, so a `git log -p webroot/files/help/` review during release flagging is enough.
3. **Dark-mode screenshot doubling.** 30 screenshots becomes 60 with light + dark variants. Easy to start in just light and add dark later as a "nice to have" — the dark variant is optional in the `ui/screenshot` element.
4. **Sidebar discoverability on mobile.** A collapsed `<details>` is less discoverable than a full sidebar. The mobile "previous / next" links inside each article are the safety net so users can navigate even without expanding the TOC.

---

## Open questions for the reviewer

1. **Page allow-list strictness.** The plan validates `(category, slug)` against `HelpCatalog::TREE`. Should the controller silently 404 on unknown slugs, or `throw new NotFoundException` to log the attempt? (Recommend: 404 silently for v1; log only if it becomes a problem.)
2. **In-app contextual deep links — opt-in or out?** The spec says "sprinkle judiciously". Do you want a single "Help →" link on every form's lede paragraph, or only on the most complex forms (QSO add, template designer, admin settings)? (Recommend: only the complex forms for v1.)
3. **Footer entry.** Add a "Help" link to the footer's existing line? (Recommend: yes — discoverable from any page even if the navbar's hidden.)

If anything else is unclear or should change, flag in review.
