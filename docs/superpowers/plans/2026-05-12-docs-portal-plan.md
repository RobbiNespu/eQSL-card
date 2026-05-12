# eQSL Card — Documentation Portal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a public, two-pane documentation portal at `/help` driven by a single PHP allow-list (`App\Service\HelpCatalog`), with reusable callout / screenshot / sidebar elements, opt-in Mermaid diagrams, and 10 fully-written v1 articles plus 14 stubs that lock in the IA from day one.

**Architecture:** CakePHP server-rendered pages. Routes `/help` and `/help/{category}/{slug}` map to `HelpController`. The controller validates `(category, slug)` against `HelpCatalog::TREE` (404 otherwise) and renders `templates/Help/{category}/{slug}.php`. Article templates begin with `$this->extend('/Help/view')` to inherit the shared two-pane chrome (sidebar + content area). Diagrams use Mermaid via jsdelivr CDN, loaded only on pages that opt in via `$this->set('useMermaid', true)`. Screenshots live in `webroot/files/help/{category}/{slug}/` and render through `element/ui/screenshot.php` with optional light/dark variants.

**Tech Stack:** CakePHP 5, PHP 8.1, Tailwind+DaisyUI (compiled to `webroot/css/dist.css` per the prior UI audit), Alpine.js, optional Mermaid via CDN.

**Commit author:** All commits MUST be authored as `Robbi Nespu <robbinespu@gmail.com>` with NO `Co-Authored-By` trailer. Use the pattern:
```bash
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "..."
```

**Read first:** `docs/superpowers/specs/2026-05-12-docs-portal-design.md` (the approved spec).

---

## File structure map

### Files to create

Backend (Backend Developer):
- `src/Service/HelpCatalog.php` — the IA constant + helpers
- `src/Controller/HelpController.php` — index + view actions
- `tests/TestCase/Service/HelpCatalogTest.php`
- `tests/TestCase/Controller/HelpControllerTest.php`

Frontend (Frontend Developer):
- `templates/Help/index.php` — landing page (`/help`)
- `templates/Help/view.php` — shared two-pane wrapper (extended by articles)
- `templates/element/ui/help_sidebar.php` — sidebar nav element
- `templates/element/ui/callout.php` — note / tip / warning boxes
- `templates/element/ui/screenshot.php` — image with light/dark variants
- `tests/TestCase/View/HelpElementsTest.php` — assertions for sidebar / callout / screenshot
- `templates/Help/{category}/{slug}.php` — 24 article files (10 fully written, 14 stubs)
- `webroot/files/help/.gitkeep` — placeholder dir for screenshots

Documentation:
- 10 fully-written articles (content written by Documentation Specialist)

### Files to modify

- `config/routes.php` — add `/help` + `/help/{category}/{slug}` routes (Backend)
- `templates/layout/default.php` — add "Help" link to navbar (logged-in + logged-out branches) and footer (Frontend)
- `webroot/css/theme.css` — `.help-shell`, `.help-sidebar`, `.help-content`, `.callout-*` rules (Frontend)
- `README.md` — link to `/help` (Documentation)
- `templates/Qsos/add.php`, `templates/Templates/edit.php`, `templates/Install/index.php` — contextual deep links (Frontend)

---

## Pre-flight verification

Before starting Task 1.1, confirm the working state:

```bash
git status
# Expected: clean on branch m1-foundation

docker compose ps | grep -E "php|db|nginx"
# Expected: all three Up. If not: docker compose up -d and wait 5 seconds.

curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8080/
# Expected: 200

docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
# Expected: OK (349 tests, 1103 assertions)
```

If any of these fail, fix before starting.

---

# PHASE 1 — Backend foundation

Two tasks: the IA catalog, then the controller + routes.

---

## Task 1.1: HelpCatalog service (TDD)

**Role:** Backend Developer

**Files:**
- Create: `src/Service/HelpCatalog.php`
- Create: `tests/TestCase/Service/HelpCatalogTest.php`

### Steps

- [ ] **Step 1: Write the failing test**

Create `tests/TestCase/Service/HelpCatalogTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\HelpCatalog;
use Cake\TestSuite\TestCase;

final class HelpCatalogTest extends TestCase
{
    public function testExistsReturnsTrueForKnownPair(): void
    {
        $this->assertTrue(HelpCatalog::exists('getting-started', 'welcome'));
    }

    public function testExistsReturnsFalseForUnknownCategory(): void
    {
        $this->assertFalse(HelpCatalog::exists('not-a-category', 'welcome'));
    }

    public function testExistsReturnsFalseForUnknownSlugInRealCategory(): void
    {
        $this->assertFalse(HelpCatalog::exists('getting-started', 'not-a-page'));
    }

    public function testPageLabelReturnsExpectedString(): void
    {
        $this->assertSame('Welcome to eQSL Card', HelpCatalog::pageLabel('getting-started', 'welcome'));
    }

    public function testCategoryLabelReturnsExpectedString(): void
    {
        $this->assertSame('Getting started', HelpCatalog::categoryLabel('getting-started'));
    }

    public function testNeighboursReturnsPrevAndNext(): void
    {
        $n = HelpCatalog::neighbours('getting-started', 'create-account');
        $this->assertIsArray($n);
        $this->assertArrayHasKey('prev', $n);
        $this->assertArrayHasKey('next', $n);
        $this->assertSame(['category' => 'getting-started', 'slug' => 'welcome'], $n['prev']);
        $this->assertSame(['category' => 'getting-started', 'slug' => 'first-card'], $n['next']);
    }

    public function testNeighboursFirstPageHasNullPrev(): void
    {
        $n = HelpCatalog::neighbours('getting-started', 'welcome');
        $this->assertNull($n['prev']);
        $this->assertNotNull($n['next']);
    }

    public function testNeighboursLastPageHasNullNext(): void
    {
        $n = HelpCatalog::neighbours('reference', 'about');
        $this->assertNotNull($n['prev']);
        $this->assertNull($n['next']);
    }

    public function testNeighboursCrossesCategoryBoundary(): void
    {
        // Last page of one category links to first page of the next.
        $n = HelpCatalog::neighbours('getting-started', 'first-card');
        $this->assertSame(['category' => 'logging', 'slug' => 'add-qso'], $n['next']);
    }

    public function testAllPagesYieldsEveryPair(): void
    {
        $pairs = iterator_to_array(HelpCatalog::allPages());
        $this->assertGreaterThanOrEqual(24, count($pairs));
        // Each entry is [category, slug, label].
        foreach ($pairs as $entry) {
            $this->assertCount(3, $entry);
        }
    }
}
```

- [ ] **Step 2: Run the test, confirm failures**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/Service/HelpCatalogTest.php --no-coverage 2>&1 | tail -10
```

Expected: 10 errors / failures (class doesn't exist yet).

- [ ] **Step 3: Create `src/Service/HelpCatalog.php`**

```php
<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Single source of truth for the docs portal's information architecture.
 *
 * Drives:
 *   - HelpController's route validation (only allow-listed pairs render).
 *   - The sidebar element (renders the tree).
 *   - Per-article previous/next links.
 *
 * Pages are added by editing TREE here — no DB, no admin UI.
 */
final class HelpCatalog
{
    /**
     * Nested: category-slug → ['label' => str, 'pages' => [slug => title]].
     * Order matters — used for prev/next.
     */
    public const TREE = [
        'getting-started' => [
            'label' => 'Getting started',
            'pages' => [
                'welcome'        => 'Welcome to eQSL Card',
                'create-account' => 'Create an account',
                'first-card'     => 'Your first eQSL card',
            ],
        ],
        'logging' => [
            'label' => 'Logging QSOs',
            'pages' => [
                'add-qso'      => 'Log a contact',
                'import'       => 'Import an ADIF / CSV log',
                'net-checkins' => 'Logging net check-ins',
                'autocomplete' => 'Callsign auto-complete',
            ],
        ],
        'cards' => [
            'label' => 'Cards & sharing',
            'pages' => [
                'render'      => 'Generate an eQSL card',
                'bulk-render' => 'Bulk-render many cards',
                'share'       => 'Share a card publicly',
                'download'    => 'Download as image or PDF',
            ],
        ],
        'templates' => [
            'label' => 'Templates',
            'pages' => [
                'overview'      => 'How templates work',
                'designer'      => 'Design your own template',
                'submit-public' => 'Submit a template to the gallery',
            ],
        ],
        'admin' => [
            'label' => 'Admin guide',
            'pages' => [
                'install'      => 'First-time install + setup',
                'settings'     => 'Site settings',
                'users'        => 'User management',
                'cleanup'      => 'Storage cleanup',
                'callsign-dir' => 'Callsign directory CSV upload',
                'audit'        => 'Audit log',
                'migrations'   => 'Running migrations',
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

    public static function exists(string $category, string $slug): bool
    {
        return isset(self::TREE[$category]['pages'][$slug]);
    }

    public static function pageLabel(string $category, string $slug): string
    {
        return self::TREE[$category]['pages'][$slug] ?? '';
    }

    public static function categoryLabel(string $category): string
    {
        return self::TREE[$category]['label'] ?? '';
    }

    /**
     * @return array{prev: ?array, next: ?array}
     */
    public static function neighbours(string $category, string $slug): array
    {
        $flat = [];
        foreach (self::TREE as $cat => $data) {
            foreach (array_keys($data['pages']) as $s) {
                $flat[] = ['category' => $cat, 'slug' => $s];
            }
        }
        $idx = null;
        foreach ($flat as $i => $entry) {
            if ($entry['category'] === $category && $entry['slug'] === $slug) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            return ['prev' => null, 'next' => null];
        }
        return [
            'prev' => $flat[$idx - 1] ?? null,
            'next' => $flat[$idx + 1] ?? null,
        ];
    }

    /**
     * @return \Generator<array{0:string,1:string,2:string}>
     */
    public static function allPages(): \Generator
    {
        foreach (self::TREE as $category => $data) {
            foreach ($data['pages'] as $slug => $label) {
                yield [$category, $slug, $label];
            }
        }
    }
}
```

- [ ] **Step 4: Run the test, confirm pass**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/Service/HelpCatalogTest.php --no-coverage 2>&1 | tail -5
```

Expected: `OK (10 tests, ...)`.

- [ ] **Step 5: Full suite stays green**

```bash
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: `OK (359 tests, ...)` — 349 baseline + 10 new HelpCatalog tests.

- [ ] **Step 6: Commit**

```bash
git add src/Service/HelpCatalog.php tests/TestCase/Service/HelpCatalogTest.php
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
feat(help): HelpCatalog service — IA for the docs portal

A single-file allow-list of categories + pages with labels. Drives:
  - HelpController's 404 gate (only TREE pairs render).
  - Sidebar rendering (single source of truth).
  - Per-article prev/next links.

24 pages across 6 categories: getting-started, logging, cards,
templates, admin, reference. Order matters — used for prev/next.

10 tests cover existence checks, label lookups, neighbour
traversal (first/last, cross-category), and the allPages
generator.
EOF
)"
```

---

## Task 1.2: HelpController + routes (TDD)

**Role:** Backend Developer

**Files:**
- Create: `src/Controller/HelpController.php`
- Create: `tests/TestCase/Controller/HelpControllerTest.php`
- Modify: `config/routes.php`

### Steps

- [ ] **Step 1: Write the failing controller test**

Create `tests/TestCase/Controller/HelpControllerTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class HelpControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testIndexReturns200WhenLoggedOut(): void
    {
        $this->get('/help');
        $this->assertResponseOk();
        $this->assertResponseContains('Help');
    }

    public function testKnownArticleReturns200(): void
    {
        // Until article stubs land, view returns the rendered template;
        // by Task 1.2 only the controller exists. We assert 404 for
        // unknown pairs and rely on Task 4.1 (stubs) to make known
        // pairs return 200. For this test pass, route to a guaranteed-
        // OK known pair AFTER Task 4.1 — for now mark as 200 expected
        // and the test passes once stubs ship in Task 4.
        //
        // For Task 1.2 we test only the 404 gate; this test stays
        // skipped until Task 4.
        $this->markTestSkipped('Awaits Task 4 article stubs');
    }

    public function testUnknownCategoryReturns404(): void
    {
        $this->get('/help/not-a-category/welcome');
        $this->assertResponseCode(404);
    }

    public function testUnknownSlugReturns404(): void
    {
        $this->get('/help/getting-started/not-a-page');
        $this->assertResponseCode(404);
    }

    public function testRouteRejectsPathTraversalAttempt(): void
    {
        // The route regex (a-z0-9 dash only) means /help/../etc/passwd
        // can't even match — Cake returns 404 from the router itself.
        $this->get('/help/..%2f..%2fetc/passwd');
        $this->assertResponseCode(404);
    }
}
```

- [ ] **Step 2: Run the test, confirm failures**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/Controller/HelpControllerTest.php --no-coverage 2>&1 | tail -10
```

Expected: errors — routes don't exist.

- [ ] **Step 3: Add `/help` routes to `config/routes.php`**

Find the block:

```php
        $builder->connect('/email/verify/{token}', ['controller' => 'Auth', 'action' => 'verify'])
```

Insert immediately after it (still inside the same `$builder->scope('/', …)` closure):

```php
        // Public docs portal — no auth required.
        $builder->connect('/help', ['controller' => 'Help', 'action' => 'index']);
        $builder->connect('/help/{category}/{slug}', ['controller' => 'Help', 'action' => 'view'])
            ->setPatterns(['category' => '[a-z][a-z0-9-]*', 'slug' => '[a-z][a-z0-9-]*'])
            ->setPass(['category', 'slug']);
```

- [ ] **Step 4: Create `src/Controller/HelpController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\HelpCatalog;
use Cake\Http\Exception\NotFoundException;

/**
 * Public docs portal.
 *
 *   GET /help                      → index landing page.
 *   GET /help/{category}/{slug}    → individual article (validated against
 *                                    HelpCatalog::TREE; 404 otherwise).
 *
 * No auth — both routes serve logged-out visitors so search engines
 * can index and operators can share article links externally.
 */
final class HelpController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        // Mark both actions as public so the auth middleware skips them.
        $this->Authentication->allowUnauthenticated(['index', 'view']);
    }

    public function index(): void
    {
        $this->set('tree', HelpCatalog::TREE);
        $this->set('title', 'Help');
    }

    public function view(string $category, string $slug): void
    {
        if (!HelpCatalog::exists($category, $slug)) {
            throw new NotFoundException('Documentation page not found.');
        }
        $this->set(compact('category', 'slug'));
        $this->set('title', HelpCatalog::pageLabel($category, $slug));
        $this->render("Help/{$category}/{$slug}");
    }
}
```

- [ ] **Step 5: Run the test, confirm pass**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/Controller/HelpControllerTest.php --no-coverage 2>&1 | tail -10
```

Expected: 1 skipped (the known-article test, awaits Task 4), 3 passes (404 gates).

The `testIndexReturns200WhenLoggedOut` test will FAIL because `templates/Help/index.php` doesn't exist yet — that's Task 2.2. **Mark this test skipped too**, with a TODO to un-skip once Task 2.2 lands. Update the test file to:

```php
    public function testIndexReturns200WhenLoggedOut(): void
    {
        $this->markTestSkipped('Awaits Task 2.2 (Help/index.php template)');
    }
```

Run again — should pass with 2 skipped + 3 passing.

- [ ] **Step 6: Full suite stays green**

```bash
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: `OK (...)` with 5 new tests (3 passing + 2 skipped), full count `364`.

- [ ] **Step 7: Commit**

```bash
git add src/Controller/HelpController.php tests/TestCase/Controller/HelpControllerTest.php config/routes.php
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
feat(help): HelpController + public /help routes

  - GET /help                   → index landing (Task 2.2 will add the template)
  - GET /help/{category}/{slug} → article view, validated against
                                  HelpCatalog::TREE, 404 otherwise.

Both actions allowUnauthenticated — the docs portal is public so
search engines can index it and operators can share article links
externally without forcing recipients to sign up.

Route regex restricts {category} and {slug} to lower-case alpha +
digits + dash starting with a letter, so directory-traversal
attempts (../etc/passwd) can't even reach the controller — Cake's
router returns 404 from the regex match. Defense in depth: the
controller also re-validates via HelpCatalog::exists().

Tests: 3 passing 404-gate cases + 2 skipped (await Task 2.2 + 4.1
templates).
EOF
)"
```

---

# PHASE 2 — Layout shell

Two tasks: the sidebar element + theme.css styles, then the wrapper template + landing page + navbar/footer wiring.

---

## Task 2.1: Help sidebar element + theme.css styles (TDD)

**Role:** Frontend Developer

**Files:**
- Create: `templates/element/ui/help_sidebar.php`
- Create: `tests/TestCase/View/HelpElementsTest.php`
- Modify: `webroot/css/theme.css`

### Steps

- [ ] **Step 1: Write the failing test**

Create `tests/TestCase/View/HelpElementsTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\View;

use Cake\TestSuite\TestCase;
use Cake\View\View;

final class HelpElementsTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = new View();
    }

    public function testHelpSidebarRendersAllCategoryLabels(): void
    {
        $out = $this->view->element('ui/help_sidebar', [
            'activeCategory' => null,
            'activeSlug' => null,
        ]);
        $this->assertStringContainsString('Getting started', $out);
        $this->assertStringContainsString('Logging QSOs', $out);
        $this->assertStringContainsString('Cards &amp; sharing', $out);
        $this->assertStringContainsString('Admin guide', $out);
    }

    public function testHelpSidebarMarksActivePage(): void
    {
        $out = $this->view->element('ui/help_sidebar', [
            'activeCategory' => 'getting-started',
            'activeSlug' => 'welcome',
        ]);
        // Active link gets aria-current="page" + a CSS class hook.
        $this->assertMatchesRegularExpression(
            '/<a[^>]+aria-current="page"[^>]*>Welcome to eQSL Card<\/a>/',
            $out
        );
    }

    public function testHelpSidebarLinksUseHelpUrls(): void
    {
        $out = $this->view->element('ui/help_sidebar', [
            'activeCategory' => null,
            'activeSlug' => null,
        ]);
        $this->assertStringContainsString('href="/help/getting-started/welcome"', $out);
        $this->assertStringContainsString('href="/help/admin/install"', $out);
    }
}
```

- [ ] **Step 2: Run the test, confirm failures**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/View/HelpElementsTest.php --no-coverage 2>&1 | tail -10
```

Expected: 3 errors (element missing).

- [ ] **Step 3: Create `templates/element/ui/help_sidebar.php`**

```php
<?php
/**
 * Docs portal sidebar — renders the full category tree from
 * App\Service\HelpCatalog. The active link gets aria-current="page".
 *
 * @var \App\View\AppView $this
 * @var string|null $activeCategory
 * @var string|null $activeSlug
 */
use App\Service\HelpCatalog;
?>
<nav class="help-sidebar" aria-label="Documentation navigation">
  <details class="help-sidebar__mobile-toggle">
    <summary>
      <span class="help-sidebar__crumb"><?= h(HelpCatalog::categoryLabel($activeCategory ?? '') ?: 'Help') ?></span>
      <?php if ($activeSlug): ?>
        <span class="help-sidebar__crumb-sep">·</span>
        <span class="help-sidebar__crumb"><?= h(HelpCatalog::pageLabel($activeCategory, $activeSlug)) ?></span>
      <?php endif; ?>
    </summary>
    <ul class="help-sidebar__list">
      <?php foreach (HelpCatalog::TREE as $cat => $data): ?>
        <li class="help-sidebar__category">
          <span class="help-sidebar__category-label"><?= h($data['label']) ?></span>
          <ul>
            <?php foreach ($data['pages'] as $slug => $label): ?>
              <?php $isActive = $cat === $activeCategory && $slug === $activeSlug; ?>
              <li>
                <a class="help-sidebar__link<?= $isActive ? ' is-active' : '' ?>"
                   href="/help/<?= h($cat) ?>/<?= h($slug) ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                  <?= h($label) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </li>
      <?php endforeach; ?>
    </ul>
  </details>
</nav>
```

- [ ] **Step 4: Run the test, confirm pass**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/View/HelpElementsTest.php --no-coverage 2>&1 | tail -5
```

Expected: `OK (3 tests, ...)`.

- [ ] **Step 5: Append sidebar + content styles to `webroot/css/theme.css`**

Append at the end of the file (after the existing dark-mode override block):

```css

/* =========================================================================
 *  Docs portal (/help) — two-pane layout with persistent sidebar
 * ========================================================================= */
.help-shell {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: var(--s-6);
  align-items: start;
}
.help-sidebar {
  position: sticky;
  top: var(--s-5);
  max-height: calc(100vh - var(--s-7));
  overflow-y: auto;
  font-size: var(--t-sm);
}
.help-sidebar__list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.help-sidebar__list ul {
  list-style: none;
  padding-left: var(--s-3);
  margin: var(--s-1) 0 var(--s-3);
}
.help-sidebar__category-label {
  display: block;
  font-size: var(--t-xs);
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--fg-muted);
  margin: var(--s-3) 0 var(--s-1);
}
.help-sidebar__link {
  display: block;
  padding: 0.4rem var(--s-3);
  border-radius: var(--r);
  color: var(--fg-muted);
  text-decoration: none;
  line-height: 1.4;
}
.help-sidebar__link:hover {
  background: var(--surface-2);
  color: var(--fg);
  text-decoration: none;
}
.help-sidebar__link.is-active,
.help-sidebar__link[aria-current="page"] {
  background: var(--accent-soft);
  color: var(--accent-strong);
  font-weight: 500;
}
.help-sidebar__mobile-toggle > summary {
  display: none;
}
.help-sidebar__crumb-sep { margin: 0 var(--s-1); color: var(--fg-subtle); }

.help-content {
  max-width: 70ch;
  font-size: var(--t-base);
  line-height: 1.65;
}
.help-content h2 { margin-top: var(--s-7); }
.help-content h3 { margin-top: var(--s-5); }
.help-content img { max-width: 100%; height: auto; }
.help-content figure { margin: var(--s-5) 0; }
.help-content figcaption {
  font-size: var(--t-sm);
  color: var(--fg-muted);
  margin-top: var(--s-2);
}
.help-content pre.mermaid {
  background: var(--surface-2);
  padding: var(--s-4);
  border-radius: var(--r-md);
  text-align: center;
}

.help-prev-next {
  display: flex;
  justify-content: space-between;
  gap: var(--s-3);
  margin-top: var(--s-8);
  padding-top: var(--s-5);
  border-top: 1px solid var(--border);
}
.help-prev-next a {
  display: inline-block;
  padding: var(--s-3) var(--s-4);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  color: var(--fg);
  text-decoration: none;
  flex: 1 1 0;
  background: var(--surface);
}
.help-prev-next a:hover { border-color: var(--accent); background: var(--surface-2); text-decoration: none; }
.help-prev-next .help-prev-next__label {
  display: block;
  font-size: var(--t-xs);
  color: var(--fg-muted);
  margin-bottom: 2px;
}
.help-prev-next__next { text-align: right; }

/* Mobile: stack the sidebar above the content and collapse it via <details>. */
@media (max-width: 991.98px) {
  .help-shell {
    grid-template-columns: 1fr;
  }
  .help-sidebar {
    position: static;
    max-height: none;
    border: 1px solid var(--border);
    border-radius: var(--r-md);
    padding: var(--s-3);
    background: var(--surface);
    margin-bottom: var(--s-5);
  }
  .help-sidebar__mobile-toggle > summary {
    display: list-item;
    cursor: pointer;
    font-weight: 500;
    padding: var(--s-2) 0;
  }
  .help-sidebar__list {
    margin-top: var(--s-3);
  }
}
@media (min-width: 992px) {
  .help-sidebar__mobile-toggle[open] > .help-sidebar__list,
  .help-sidebar__mobile-toggle > .help-sidebar__list {
    display: block; /* always shown on desktop regardless of <details> state */
  }
}
```

- [ ] **Step 6: Rebuild dist.css**

```bash
npm run build:css
```

Expected: `dist.css` regenerates without error. Confirm size hasn't ballooned absurdly:

```bash
ls -lh webroot/css/dist.css
```

- [ ] **Step 7: Full smoke + tests**

```bash
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: `OK (...)`, 3 more passing tests than the prior task baseline.

- [ ] **Step 8: Commit**

```bash
git add templates/element/ui/help_sidebar.php tests/TestCase/View/HelpElementsTest.php webroot/css/theme.css webroot/css/dist.css
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
feat(help): sidebar element + .help-shell layout styles

  - element/ui/help_sidebar.php renders the full HelpCatalog::TREE
    with the active page marked via .is-active class +
    aria-current="page". On mobile the whole tree collapses into a
    <details> with the current crumb as the summary.
  - theme.css gains a .help-shell grid (260px + 1fr) with sticky
    sidebar on desktop, stacked on mobile. .help-content caps at
    70ch for prose readability. .help-prev-next pagination block
    styled to match.
  - dist.css rebuilt.

Three view tests cover the sidebar's category labels, active-page
marking, and link URLs.
EOF
)"
```

---

## Task 2.2: Help/view.php wrapper + Help/index.php landing + navbar/footer entries

**Role:** Frontend Developer

**Files:**
- Create: `templates/Help/view.php`
- Create: `templates/Help/index.php`
- Create: `webroot/files/help/.gitkeep`
- Modify: `templates/layout/default.php`
- Modify: `tests/TestCase/Controller/HelpControllerTest.php` (un-skip `testIndexReturns200WhenLoggedOut`)

### Steps

- [ ] **Step 1: Create `templates/Help/view.php`** (the shared two-pane wrapper)

```php
<?php
/**
 * Docs portal page wrapper. Article templates extend this via
 * <?php $this->extend('/Help/view'); ?>.
 *
 * @var \App\View\AppView $this
 * @var string $category
 * @var string $slug
 * @var bool $useMermaid (optional; opt-in to Mermaid CDN load)
 */
$useMermaid = $useMermaid ?? false;
$neighbours = \App\Service\HelpCatalog::neighbours($category, $slug);
?>
<div class="help-shell">
  <?= $this->element('ui/help_sidebar', [
      'activeCategory' => $category,
      'activeSlug' => $slug,
  ]) ?>

  <article class="help-content">
    <?= $this->fetch('content') ?>

    <nav class="help-prev-next" aria-label="Previous and next">
      <?php if (!empty($neighbours['prev'])): ?>
        <a href="/help/<?= h($neighbours['prev']['category']) ?>/<?= h($neighbours['prev']['slug']) ?>">
          <span class="help-prev-next__label">← Previous</span>
          <?= h(\App\Service\HelpCatalog::pageLabel($neighbours['prev']['category'], $neighbours['prev']['slug'])) ?>
        </a>
      <?php else: ?>
        <span></span>
      <?php endif; ?>

      <?php if (!empty($neighbours['next'])): ?>
        <a class="help-prev-next__next"
           href="/help/<?= h($neighbours['next']['category']) ?>/<?= h($neighbours['next']['slug']) ?>">
          <span class="help-prev-next__label">Next →</span>
          <?= h(\App\Service\HelpCatalog::pageLabel($neighbours['next']['category'], $neighbours['next']['slug'])) ?>
        </a>
      <?php else: ?>
        <span></span>
      <?php endif; ?>
    </nav>
  </article>
</div>

<?php if ($useMermaid): ?>
  <?php $this->start('script'); ?>
  <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
  <script>
    mermaid.initialize({
      startOnLoad: true,
      theme: document.documentElement.getAttribute('data-theme') === 'eqsl-dark' ? 'dark' : 'default'
    });
  </script>
  <?php $this->end(); ?>
<?php endif; ?>
```

- [ ] **Step 2: Create `templates/Help/index.php`** (the landing page)

```php
<?php
/**
 * /help — landing page. Shows the full table of contents as a card grid
 * so visitors can browse categories at a glance, then click into the
 * sidebar from any article.
 *
 * @var \App\View\AppView $this
 * @var array $tree (from HelpController::index)
 */
?>
<?= $this->element('ui/page_header', [
    'title' => 'Help',
    'lede'  => 'Guides for using eQSL Card — from your first contact to running a busy logbook.',
]) ?>

<div class="row g-3">
  <?php foreach ($tree as $category => $data): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card h-100 card-body">
        <h2 class="h5 mb-2"><?= h($data['label']) ?></h2>
        <ul class="list-unstyled mb-0" style="padding: 0; list-style: none;">
          <?php foreach ($data['pages'] as $slug => $label): ?>
            <li style="padding: 2px 0;">
              <a href="/help/<?= h($category) ?>/<?= h($slug) ?>"><?= h($label) ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endforeach; ?>
</div>
```

- [ ] **Step 3: Add Help link to navbar in `templates/layout/default.php`**

Find the logged-in nav block:

```php
        <li class="nav-item"><a class="nav-link" href="/templates">Templates</a></li>
```

Insert immediately after it:

```php
        <li class="nav-item"><a class="nav-link" href="/help">Help</a></li>
```

Find the logged-out nav block:

```php
        <li class="nav-item"><a class="nav-link" href="/login">Sign in</a></li>
        <li class="nav-item"><a class="nav-link btn btn-primary text-white" href="/register">Create account</a></li>
```

Insert a Help link between them:

```php
        <li class="nav-item"><a class="nav-link" href="/login">Sign in</a></li>
        <li class="nav-item"><a class="nav-link" href="/help">Help</a></li>
        <li class="nav-item"><a class="nav-link btn btn-primary text-white" href="/register">Create account</a></li>
```

- [ ] **Step 4: Add Help link to the footer in `templates/layout/default.php`**

Find:

```php
      Open-source eQSL card workbench for amateur radio operators ·
      Built by <a href="https://robbi.my" rel="noopener">Robbi Nespu</a> ·
      9W2NSP · <span class="footer-mono"><?= date('Y-m-d') ?> UTC</span>
```

Add a Help link in front of "Built by":

```php
      Open-source eQSL card workbench for amateur radio operators ·
      <a href="/help">Help</a> ·
      Built by <a href="https://robbi.my" rel="noopener">Robbi Nespu</a> ·
      9W2NSP · <span class="footer-mono"><?= date('Y-m-d') ?> UTC</span>
```

- [ ] **Step 5: Add a `.gitkeep` for the screenshot directory**

```bash
mkdir -p webroot/files/help
touch webroot/files/help/.gitkeep
```

- [ ] **Step 6: Un-skip the index test in `tests/TestCase/Controller/HelpControllerTest.php`**

Find:

```php
    public function testIndexReturns200WhenLoggedOut(): void
    {
        $this->markTestSkipped('Awaits Task 2.2 (Help/index.php template)');
    }
```

Replace with:

```php
    public function testIndexReturns200WhenLoggedOut(): void
    {
        $this->get('/help');
        $this->assertResponseOk();
        $this->assertResponseContains('Getting started');
        $this->assertResponseContains('Welcome to eQSL Card');
    }
```

- [ ] **Step 7: Smoke test**

```bash
echo "=== /help (logged out) ==="
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8080/help
# Expected: 200

echo "=== /help logged-out content sanity ==="
curl -s http://127.0.0.1:8080/help | grep -c "Getting started"
# Expected: at least 1

echo "=== Help link in navbar of /dashboard (logged-in users see it) ==="
# Need to login for this:
rm -f /tmp/eqsl-cookies.txt
LOGIN_HTML=$(curl -s -c /tmp/eqsl-cookies.txt http://127.0.0.1:8080/login)
TOKEN=$(echo "$LOGIN_HTML" | grep -oP 'name="_csrfToken"[^>]*value="\K[^"]+' | head -1)
curl -s -b /tmp/eqsl-cookies.txt -c /tmp/eqsl-cookies.txt -L -X POST http://127.0.0.1:8080/login \
  --data-urlencode "_csrfToken=$TOKEN" --data-urlencode "email=contact@robbi.my" --data-urlencode "password=Password2345" -o /dev/null

curl -s -b /tmp/eqsl-cookies.txt http://127.0.0.1:8080/dashboard | grep -c 'href="/help"'
# Expected: 2 (navbar + footer)

docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: PHPUnit `OK (...)` with one MORE passing test than 2.1's baseline (the `testIndexReturns200WhenLoggedOut` un-skip).

- [ ] **Step 8: Commit**

```bash
git add templates/Help/view.php templates/Help/index.php templates/layout/default.php tests/TestCase/Controller/HelpControllerTest.php webroot/files/help/.gitkeep
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
feat(help): two-pane wrapper + landing page + nav entries

  - templates/Help/view.php: the shared wrapper articles extend.
    Renders the sidebar element + the article body via
    $this->fetch('content') + a previous/next pagination block
    driven by HelpCatalog::neighbours(). Conditionally lazy-loads
    Mermaid via jsdelivr CDN when an article opts in by setting
    $useMermaid = true.
  - templates/Help/index.php: the /help landing page. Renders the
    full TOC as a 3-up card grid for at-a-glance browsing.
  - layout/default.php: Help link added to navbar (logged-in branch
    between Templates and the theme toggle; logged-out branch
    between Sign in and Create account) and to the footer.
  - webroot/files/help/.gitkeep: placeholder dir for article
    screenshots; subdirectories per category/slug get created
    when content lands.
  - HelpControllerTest::testIndexReturns200WhenLoggedOut un-skipped
    now that the template exists.

Articles still don't render (404 — they're stub-and-write in Tasks
4 and 5). Logged-out smoke confirms /help itself returns 200 with
all six category labels in the served HTML.
EOF
)"
```

---

# PHASE 3 — Authoring helpers

Three small reusable pieces: callout boxes, the light/dark screenshot wrapper, and the Mermaid switch (already wired in Task 2.2 — this phase just adds the elements).

---

## Task 3.1: ui/callout element + theme.css

**Role:** Frontend Developer

**Files:**
- Create: `templates/element/ui/callout.php`
- Modify: `webroot/css/theme.css`
- Modify: `tests/TestCase/View/HelpElementsTest.php` (add callout tests)

### Steps

- [ ] **Step 1: Add failing tests for the callout element**

Open `tests/TestCase/View/HelpElementsTest.php` and append these tests inside the class:

```php
    public function testCalloutDefaultIsNote(): void
    {
        $out = $this->view->element('ui/callout', ['body' => 'Heads up.']);
        $this->assertStringContainsString('callout callout-note', $out);
        $this->assertStringContainsString('Heads up.', $out);
    }

    public function testCalloutVariantTip(): void
    {
        $out = $this->view->element('ui/callout', ['body' => 'Try this.', 'variant' => 'tip']);
        $this->assertStringContainsString('callout-tip', $out);
        $this->assertStringContainsString('Tip', $out); // emoji prefix label
    }

    public function testCalloutVariantWarning(): void
    {
        $out = $this->view->element('ui/callout', ['body' => 'Careful.', 'variant' => 'warning']);
        $this->assertStringContainsString('callout-warning', $out);
        $this->assertStringContainsString('Warning', $out);
    }

    public function testCalloutEscapesBody(): void
    {
        $out = $this->view->element('ui/callout', ['body' => '<script>x</script>']);
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }
```

- [ ] **Step 2: Run, confirm 4 failures**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/View/HelpElementsTest.php --no-coverage 2>&1 | tail -10
```

- [ ] **Step 3: Create `templates/element/ui/callout.php`**

```php
<?php
/**
 * Inline callout box for help articles — Note / Tip / Warning.
 *
 * @var \App\View\AppView $this
 * @var string $body
 * @var string $variant 'note' (default) | 'tip' | 'warning'
 */
$variant = $variant ?? 'note';
if (!in_array($variant, ['note', 'tip', 'warning'], true)) {
    $variant = 'note';
}
$labels = ['note' => 'Note', 'tip' => 'Tip', 'warning' => 'Warning'];
?>
<aside class="callout callout-<?= h($variant) ?>" role="note">
  <strong class="callout__label"><?= h($labels[$variant]) ?></strong>
  <p><?= h($body ?? '') ?></p>
</aside>
```

- [ ] **Step 4: Add callout styles to `webroot/css/theme.css`**

Append (after the help portal styles from Task 2.1):

```css

/* Help article callouts (Note / Tip / Warning) */
.callout {
  margin: var(--s-5) 0;
  padding: var(--s-4) var(--s-5);
  border: 1px solid var(--border);
  border-left-width: 3px;
  border-radius: var(--r-md);
  background: var(--surface);
}
.callout > p { margin: 0; }
.callout__label {
  display: inline-block;
  font-size: var(--t-xs);
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-bottom: var(--s-1);
}
.callout-note    { border-left-color: var(--info);    background: var(--info-soft); }
.callout-note    .callout__label { color: #075985; }
.callout-tip     { border-left-color: var(--success); background: var(--success-soft); }
.callout-tip     .callout__label { color: var(--accent-strong); }
.callout-warning { border-left-color: var(--warning); background: var(--warning-soft); }
.callout-warning .callout__label { color: #b45309; }
```

- [ ] **Step 5: Run, confirm pass + rebuild dist.css**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/View/HelpElementsTest.php --no-coverage 2>&1 | tail -5
npm run build:css
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: all helpElements tests pass, full suite green.

- [ ] **Step 6: Commit**

```bash
git add templates/element/ui/callout.php tests/TestCase/View/HelpElementsTest.php webroot/css/theme.css webroot/css/dist.css
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
feat(help): callout element (Note / Tip / Warning)

Inline alert-style boxes for help articles. Three variants:
  - note    (default; info-blue tint)
  - tip     (success-emerald tint)
  - warning (warning-amber tint)

Body string is h()-escaped to prevent script injection. The label
prefix (Note / Tip / Warning) is constant per variant. Four
HelpElementsTest cases cover defaulting, variants, and escaping.
EOF
)"
```

---

## Task 3.2: ui/screenshot element with light/dark variants

**Role:** Frontend Developer

**Files:**
- Create: `templates/element/ui/screenshot.php`
- Modify: `webroot/css/theme.css`
- Modify: `tests/TestCase/View/HelpElementsTest.php` (add screenshot tests)

### Steps

- [ ] **Step 1: Add failing tests**

Append to `tests/TestCase/View/HelpElementsTest.php`:

```php
    public function testScreenshotRendersImgWithAltAndCaption(): void
    {
        $out = $this->view->element('ui/screenshot', [
            'src' => '/files/help/cards/render/template-picker.webp',
            'alt' => 'The template picker',
            'caption' => 'Pick a template from the system gallery.',
        ]);
        $this->assertStringContainsString('src="/files/help/cards/render/template-picker.webp"', $out);
        $this->assertStringContainsString('alt="The template picker"', $out);
        $this->assertStringContainsString('Pick a template from the system gallery.', $out);
        $this->assertStringContainsString('loading="lazy"', $out);
    }

    public function testScreenshotRendersDarkVariantWhenProvided(): void
    {
        $out = $this->view->element('ui/screenshot', [
            'src' => '/files/help/cards/render/template-picker.webp',
            'darkSrc' => '/files/help/cards/render/template-picker.dark.webp',
            'alt' => 'The template picker',
        ]);
        $this->assertStringContainsString('template-picker.webp', $out);
        $this->assertStringContainsString('template-picker.dark.webp', $out);
        // Two <img> tags — one for each theme.
        $this->assertSame(2, substr_count($out, '<img'));
    }

    public function testScreenshotOmitsCaptionWhenNotGiven(): void
    {
        $out = $this->view->element('ui/screenshot', [
            'src' => '/files/help/foo.webp',
            'alt' => 'foo',
        ]);
        $this->assertStringNotContainsString('<figcaption', $out);
    }
```

- [ ] **Step 2: Run, confirm 3 failures**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/View/HelpElementsTest.php --no-coverage 2>&1 | tail -10
```

- [ ] **Step 3: Create `templates/element/ui/screenshot.php`**

```php
<?php
/**
 * Screenshot wrapper with optional light/dark variants.
 *
 * Light image always renders. If $darkSrc is provided, a second <img>
 * renders alongside and CSS selectors hide whichever doesn't match
 * the current [data-theme]. Both images are lazy-loaded.
 *
 * @var \App\View\AppView $this
 * @var string $src         path under /files/help/, light variant
 * @var string $alt         required alt text
 * @var string|null $darkSrc optional dark variant path
 * @var string|null $caption optional caption shown below the image
 */
?>
<figure class="screenshot<?= !empty($darkSrc) ? ' has-dark' : '' ?>">
  <img class="screenshot__img screenshot__img--light"
       src="<?= h($src) ?>"
       alt="<?= h($alt) ?>"
       loading="lazy">
  <?php if (!empty($darkSrc)): ?>
    <img class="screenshot__img screenshot__img--dark"
         src="<?= h($darkSrc) ?>"
         alt="<?= h($alt) ?>"
         loading="lazy">
  <?php endif; ?>
  <?php if (!empty($caption)): ?>
    <figcaption><?= h($caption) ?></figcaption>
  <?php endif; ?>
</figure>
```

- [ ] **Step 4: Add screenshot styles to `webroot/css/theme.css`**

Append:

```css

/* Help article screenshots — optional light/dark variants */
.screenshot { margin: var(--s-5) 0; }
.screenshot__img {
  max-width: 100%;
  height: auto;
  border: 1px solid var(--border-strong);
  border-radius: var(--r-md);
  display: block;
}
.screenshot.has-dark .screenshot__img--dark { display: none; }
[data-theme="eqsl-dark"] .screenshot.has-dark .screenshot__img--light { display: none; }
[data-theme="eqsl-dark"] .screenshot.has-dark .screenshot__img--dark { display: block; }
```

- [ ] **Step 5: Run, confirm pass + rebuild dist.css**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/View/HelpElementsTest.php --no-coverage 2>&1 | tail -5
npm run build:css
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: 10 helpElements tests (3 sidebar + 4 callout + 3 screenshot), full suite green.

- [ ] **Step 6: Commit**

```bash
git add templates/element/ui/screenshot.php tests/TestCase/View/HelpElementsTest.php webroot/css/theme.css webroot/css/dist.css
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
feat(help): screenshot element with light/dark variants

Renders a <figure> with one or two <img> tags + optional caption.
When a darkSrc is supplied, both images are emitted with CSS rules
that hide whichever doesn't match the current [data-theme]. Lazy
loads via loading="lazy" on both. Alt text is required and shared
between the two variants (same UI, different colour scheme).

Three tests: basic render, dark-variant rendering, caption omission.
EOF
)"
```

---

# PHASE 4 — Article scaffolding

One task: generate stub files for all 24 articles. After this commit, every `/help/{category}/{slug}` URL in the catalog returns 200 with a "Coming soon" placeholder. The IA is final from day one.

---

## Task 4.1: Stub all 24 article templates + smoke-test every route

**Role:** Frontend Developer

**Files:** 24 new template files under `templates/Help/{category}/{slug}.php`

### Steps

- [ ] **Step 1: Define the stub article template**

Every stub uses the same shape — extend the wrapper, render a `page_header`, render a "Coming soon" note. Body of every stub file:

```php
<?php $this->extend('/Help/view'); ?>
<?= $this->element('ui/page_header', [
    'title' => $this->fetch('title') ?: 'Documentation',
    'lede'  => 'This guide is on the way.',
]) ?>
<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => "We haven't written this article yet. In the meantime, the Welcome guide covers the basics. Have a specific question? Reach the operator on the homepage.",
]) ?>
```

The `$this->fetch('title')` would normally come from a `$this->assign('title', ...)` inside the article — but since the controller already calls `$this->set('title', HelpCatalog::pageLabel(...))` and a stub doesn't override it, we'll use `<?= h($title) ?>` directly instead. Update the stub:

```php
<?php $this->extend('/Help/view'); ?>
<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'This guide is on the way.',
]) ?>
<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => "We haven't written this article yet. In the meantime, the Welcome guide covers the basics. Have a specific question? Reach the operator on the homepage.",
]) ?>
```

- [ ] **Step 2: Create the directory tree + 24 stub files**

```bash
mkdir -p templates/Help/getting-started \
         templates/Help/logging \
         templates/Help/cards \
         templates/Help/templates \
         templates/Help/admin \
         templates/Help/reference

# 24 slugs, one per category
slugs_getting_started="welcome create-account first-card"
slugs_logging="add-qso import net-checkins autocomplete"
slugs_cards="render bulk-render share download"
slugs_templates="overview designer submit-public"
slugs_admin="install settings users cleanup callsign-dir audit migrations"
slugs_reference="glossary troubleshooting about"

stub='<?php $this->extend('"'"'/Help/view'"'"'); ?>
<?= $this->element('"'"'ui/page_header'"'"', [
    '"'"'title'"'"' => $title,
    '"'"'lede'"'"'  => '"'"'This guide is on the way.'"'"',
]) ?>
<?= $this->element('"'"'ui/callout'"'"', [
    '"'"'variant'"'"' => '"'"'note'"'"',
    '"'"'body'"'"' => "We haven'"'"'t written this article yet. In the meantime, the Welcome guide covers the basics. Have a specific question? Reach the operator on the homepage.",
]) ?>
'

for s in $slugs_getting_started; do echo "$stub" > "templates/Help/getting-started/$s.php"; done
for s in $slugs_logging;         do echo "$stub" > "templates/Help/logging/$s.php"; done
for s in $slugs_cards;           do echo "$stub" > "templates/Help/cards/$s.php"; done
for s in $slugs_templates;       do echo "$stub" > "templates/Help/templates/$s.php"; done
for s in $slugs_admin;           do echo "$stub" > "templates/Help/admin/$s.php"; done
for s in $slugs_reference;       do echo "$stub" > "templates/Help/reference/$s.php"; done

echo "files created: $(find templates/Help -name '*.php' ! -name 'index.php' ! -name 'view.php' | wc -l)"
```

Expected: `files created: 24`.

- [ ] **Step 3: Smoke-test every catalog route returns 200**

```bash
echo "=== smoke all 24 articles ==="
for cat_slug in \
  getting-started/welcome getting-started/create-account getting-started/first-card \
  logging/add-qso logging/import logging/net-checkins logging/autocomplete \
  cards/render cards/bulk-render cards/share cards/download \
  templates/overview templates/designer templates/submit-public \
  admin/install admin/settings admin/users admin/cleanup admin/callsign-dir admin/audit admin/migrations \
  reference/glossary reference/troubleshooting reference/about; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8080/help/$cat_slug")
  echo "$code /help/$cat_slug"
done
```

Expected: every line is `200 /help/...`.

- [ ] **Step 4: Confirm 404 for unknown still works**

```bash
echo "=== 404 cases ==="
echo "$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/help/unknown/page) /help/unknown/page"
echo "$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/help/getting-started/not-a-page) /help/getting-started/not-a-page"
```

Expected: both `404`.

- [ ] **Step 5: Full PHPUnit**

```bash
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: full suite green.

- [ ] **Step 6: Commit**

```bash
git add templates/Help/
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
feat(help): stub all 24 articles — sidebar IA locked in

Every (category, slug) pair listed in HelpCatalog::TREE now has a
PHP template that extends Help/view, renders the page header from
the catalog's title, and displays a "coming soon" Note callout.

Effect: the sidebar's navigation works from day one. Every link
resolves to a valid 200 page with the correct chrome, sidebar
highlighting, and prev/next links. Content lands in later phases
by replacing stub bodies with prose + screenshots + diagrams.

Smoke-tested all 24 article URLs return 200 and the 404 gate still
fires on unknown pairs.
EOF
)"
```

---

# PHASE 5 — v1 article content

Six tasks — one per category. Each replaces stub bodies with the actual prose, screenshots, and diagrams.

For every article task, the Documentation Specialist agent writes the prose; the Frontend Developer captures the actual screenshot files (or marks them as "user TODO" with a placeholder path) and updates the PHP template. PHPUnit doesn't lock article body content, so structural tests stay green throughout.

**Standard scaffold for a finished article:**

```php
<?php $this->extend('/Help/view'); ?>
<?php // $this->set('useMermaid', true);  // uncomment if this article has a Mermaid block ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => '<one-sentence summary of what this guide covers>',
]) ?>

<p>Opening paragraph…</p>

<h2>First major section</h2>
<p>…</p>

<?= $this->element('ui/screenshot', [
    'src'     => '/files/help/{category}/{slug}/<filename>.webp',
    'alt'     => '<descriptive alt>',
    'caption' => '<optional caption>',
]) ?>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body'    => '<helpful aside>',
]) ?>

<!-- More sections, screenshots, callouts as needed -->
```

For each article task below, the dispatched Documentation Specialist agent gets:
1. The article's slug + title
2. The section outline (h2 headings + bullet-point content for each)
3. The list of screenshots the article needs (filenames + what each should show)
4. Word target (~300–600 words per article)
5. Any Mermaid diagram to include

---

## Task 5.1: Getting Started articles (3 articles)

**Role:** Documentation Specialist (writes prose) + Frontend Developer (commits and verifies)

**Files:**
- Modify: `templates/Help/getting-started/welcome.php`
- Modify: `templates/Help/getting-started/create-account.php`
- Modify: `templates/Help/getting-started/first-card.php`

### Steps

- [ ] **Step 1: Write `welcome.php`** (~250 words)

Sections:
- **What is eQSL Card** — 2-3 sentences explaining electronic QSL cards for amateur radio.
- **Who it's for** — Operators who want to confirm contacts digitally, send personalised eQSLs to QSO partners, or generate net check-in confirmations.
- **What you can do** — bullet list: log QSOs, design templates, generate cards, share publicly, bulk-render.

Include one screenshot of a generated eQSL card sample at `/files/help/getting-started/welcome/sample-card.webp`.

End the article with the standard prev/next nav (rendered automatically by the wrapper).

- [ ] **Step 2: Write `create-account.php`** (~200 words)

Sections:
- **What you'll need** — email, callsign, password.
- **The registration form** — walk through each field with what it's for; reference the form's required markers (asterisks) and helper text.
- **After registering** — email verification step (if enabled), redirect to dashboard.

Two screenshots:
- `/files/help/getting-started/create-account/register-form.webp` — empty form
- `/files/help/getting-started/create-account/register-filled.webp` — filled out

- [ ] **Step 3: Write `first-card.php`** (~500 words; Mermaid diagram included)

Uncomment `$this->set('useMermaid', true);` at the top.

Sections:
- **The 5-minute path** — quick summary of what you'll do.
- **The flow** — Mermaid flowchart:

```html
<pre class="mermaid">
flowchart LR
  A[Sign up] --> B[Add a QSO]
  B --> C[Generate eQSL]
  C --> D[Download or share]
</pre>
```

- **Step 1: Add a QSO** — link to /help/logging/add-qso, brief description, screenshot.
- **Step 2: Generate the card** — link to /help/cards/render, screenshot of the render form.
- **Step 3: Share or download** — link to /help/cards/share, two outcomes.

Three screenshots:
- `/files/help/getting-started/first-card/qso-form.webp`
- `/files/help/getting-started/first-card/render-form.webp`
- `/files/help/getting-started/first-card/generated-card.webp`

- [ ] **Step 4: Smoke**

```bash
for u in welcome create-account first-card; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8080/help/getting-started/$u")
  echo "$code /help/getting-started/$u"
done
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: all `200`, suite green.

**Important — screenshots:** the article templates reference `.webp` files that don't exist yet. The pages still render (the `<img>` produces a broken image icon next to the alt text). Capture the screenshots manually and drop into `webroot/files/help/getting-started/{slug}/<file>.webp` before launch.

- [ ] **Step 5: Commit**

```bash
git add templates/Help/getting-started/
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
docs(help): Getting started — welcome / create account / first card

Three articles cover the new-user journey: what eQSL Card is and
who it's for; the registration form walkthrough with required +
optional fields explained; and the 5-minute quick start from
sign-up to generated card with a Mermaid flowchart of the flow.

Screenshot file paths declared in the templates; the actual .webp
files are TODO for capture before launch.
EOF
)"
```

---

## Task 5.2: Logging articles — add-qso + import (2 articles)

**Role:** Documentation Specialist + Frontend Developer

**Files:**
- Modify: `templates/Help/logging/add-qso.php`
- Modify: `templates/Help/logging/import.php`

### Steps

- [ ] **Step 1: Write `add-qso.php`** (~500 words)

Sections:
- **Two QSO types** — Contact vs Net check-in. Reference the toggle at the top of the form. Cross-link to `/help/logging/net-checkins` (stub for now).
- **Required fields** — Their callsign, Date/Time UTC. Why UTC matters.
- **Optional fields** — frequency, band, mode, RST, operator name, QTH, grid square, notes.
- **Callsign auto-complete** — quick mention; link to `/help/logging/autocomplete`.
- **Transport** — RF vs internet-mediated (Echolink, Zello, etc.). Brief explanation.

Two screenshots:
- `/files/help/logging/add-qso/form-empty.webp`
- `/files/help/logging/add-qso/form-net-mode.webp` (the form with Net check-in selected, showing the additional NCS / Net title fields)

Include a `tip` callout about always entering UTC.

- [ ] **Step 2: Write `import.php`** (~400 words)

Sections:
- **Supported formats** — ADIF (.adi/.adif) and CSV (.csv).
- **What gets imported** — fields recognised: callsign, datetime UTC, band, mode, frequency, RST sent/received, notes.
- **Duplicate handling** — duplicates by (callsign, datetime UTC) are skipped silently.
- **The two-step flow** — upload → preview → confirm.

Two screenshots:
- `/files/help/logging/import/upload-form.webp`
- `/files/help/logging/import/preview-screen.webp`

- [ ] **Step 3: Smoke + commit**

```bash
for u in add-qso import; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8080/help/logging/$u")
  echo "$code /help/logging/$u"
done

docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3

git add templates/Help/logging/add-qso.php templates/Help/logging/import.php
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
docs(help): Logging — Add a QSO + Import ADIF / CSV

Two articles cover the two ways operators get QSOs into their log:
the manual form (with the contact / net toggle, required vs
optional fields, callsign auto-complete pointer, and the transport
selector) and the bulk import flow (formats, dedupe rules, the
upload → preview → confirm two-step UX).

Screenshot files TODO before launch.
EOF
)"
```

---

## Task 5.3: Cards articles — render + share (2 articles)

**Role:** Documentation Specialist + Frontend Developer

**Files:**
- Modify: `templates/Help/cards/render.php`
- Modify: `templates/Help/cards/share.php`

### Steps

- [ ] **Step 1: Write `render.php`** (~400 words)

Sections:
- **Where rendering starts** — from a QSO row in the logbook, click "Render".
- **Pick a template** — system / public / your own. Cross-link to `/help/templates/overview`.
- **Pick a background** — site default / a previous upload / upload a new one. Mention attribution metadata.
- **Generate** — what happens (server-side render via GD/FPDF, save to library).

Three screenshots:
- `/files/help/cards/render/template-picker.webp`
- `/files/help/cards/render/background-picker.webp`
- `/files/help/cards/render/generated.webp`

- [ ] **Step 2: Write `share.php`** (~450 words; Mermaid sequence diagram)

Uncomment `$this->set('useMermaid', true);` at the top.

Sections:
- **Public share links** — generate a link that anyone can view without signing in.
- **Optional password protection** — Argon2id-hashed; bcrypt at-rest.
- **Revoking a share** — the link returns 410 Gone afterwards.

Mermaid sequence diagram of the share flow:

```html
<pre class="mermaid">
sequenceDiagram
  participant U as Operator
  participant App as eQSL Card
  participant V as Recipient
  U->>App: Click "Share"
  App->>U: Public link + (optional) password
  U->>V: Sends link
  V->>App: GET /qsl/{slug}
  alt password set
    App->>V: Password prompt
    V->>App: Submits password
    App->>V: 200 + card
  else no password
    App->>V: 200 + card
  end
</pre>
```

Two screenshots:
- `/files/help/cards/share/share-toggle.webp`
- `/files/help/cards/share/public-view.webp`

- [ ] **Step 3: Smoke + commit**

```bash
for u in render share; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8080/help/cards/$u")
  echo "$code /help/cards/$u"
done

docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3

git add templates/Help/cards/render.php templates/Help/cards/share.php
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
docs(help): Cards — Generate an eQSL + Share publicly

Render article walks the picker / background / generate flow.
Share article covers public links, optional password protection,
revocation (410 Gone after revoke), plus a Mermaid sequence diagram
of the recipient's request flow.
EOF
)"
```

---

## Task 5.4: Templates overview (1 article)

**Role:** Documentation Specialist + Frontend Developer

**Files:**
- Modify: `templates/Help/templates/overview.php`

### Steps

- [ ] **Step 1: Write `overview.php`** (~400 words)

Sections:
- **Three kinds** — System (admin-curated, always available), Public (community-contributed, admin-moderated), Personal (yours, private).
- **Where to find them** — `/templates`, with three tabs: My templates, Public, System.
- **Using a template** — picked at render time; the operator's QSO data is composited onto the template at server render time.
- **Cloning a public template** — fork-and-edit pattern; cross-link to `/help/templates/designer` (stub for now).
- **Submitting to the gallery** — cross-link to `/help/templates/submit-public` (stub).

Two screenshots:
- `/files/help/templates/overview/templates-page.webp` (the tabbed templates listing)
- `/files/help/templates/overview/template-card.webp` (close-up of one template card with its action buttons)

- [ ] **Step 2: Smoke + commit**

```bash
code=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/help/templates/overview)
echo "$code /help/templates/overview"
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
git add templates/Help/templates/overview.php
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
docs(help): Templates overview

Explains the three template types (system / public / personal),
where they live in the UI, how they're used at render time, the
clone-public pattern, and a pointer to the designer guide.
EOF
)"
```

---

## Task 5.5: Admin install guide (1 article)

**Role:** Documentation Specialist + Frontend Developer

**Files:**
- Modify: `templates/Help/admin/install.php`

### Steps

- [ ] **Step 1: Write `install.php`** (~700 words; longer because install is multi-stage)

Sections (one section per install wizard stage):
1. **Before you begin** — PHP 8.1+, MariaDB 10.6+, GD extension, write access to `webroot/files/` and `tmp/`. Reference the README for `composer install --no-dev`.
2. **Stage 1: System check** — what each green check means; common failures (missing `intl`, `pdo_mysql`, file perms).
3. **Stage 2: Database** — DSN form, what the installer writes to `config/app_local.php`.
4. **Stage 3: First admin account** — sets the only initial admin; subsequent admins are promoted by an existing admin.
5. **Stage 4: Done** — first sign-in, where to go next (settings page).

Five screenshots:
- `/files/help/admin/install/stage-1-syscheck.webp`
- `/files/help/admin/install/stage-2-db.webp`
- `/files/help/admin/install/stage-3-admin.webp`
- `/files/help/admin/install/stage-4-done.webp`
- `/files/help/admin/install/syscheck-failure.webp` (what a failed system check looks like)

Include a `warning` callout: "The first-admin step happens exactly once — secure the credentials immediately."

- [ ] **Step 2: Smoke + commit**

```bash
code=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/help/admin/install)
echo "$code /help/admin/install"
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
git add templates/Help/admin/install.php
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
docs(help): Admin install + first-user setup

Walks the four-stage install wizard with a screenshot per stage,
plus one of a failed system check. Includes a warning callout
about securing the first-admin credentials.
EOF
)"
```

---

## Task 5.6: Reference glossary (1 article)

**Role:** Documentation Specialist

**Files:**
- Modify: `templates/Help/reference/glossary.php`

### Steps

- [ ] **Step 1: Write `glossary.php`** (~400 words, alphabetical)

Terms to cover (with one-sentence definitions, amateur-radio newcomer friendly):

- ADIF (Amateur Data Interchange Format)
- Band
- Callsign
- DX
- eQSL
- Grid square / Maidenhead locator
- Mode (CW, SSB, FM, FT8, etc.)
- NCS (Net Control Station)
- Net
- QSL card
- QSO
- QTH
- RST report
- Transport (RF vs internet-mediated)
- UTC

Render as a `<dl>` with terms in `<dt>` and definitions in `<dd>`, using the existing `dl-stack` class so the visual rhythm matches QSO/Card detail views.

- [ ] **Step 2: Smoke + commit**

```bash
code=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/help/reference/glossary)
echo "$code /help/reference/glossary"
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
git add templates/Help/reference/glossary.php
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
docs(help): Reference glossary

Newcomer-friendly definitions of the 15 amateur-radio + eQSL Card
terms that show up across the rest of the docs. Rendered as a
<dl class="row dl-stack"> so the visual rhythm matches detail
views elsewhere in the app.
EOF
)"
```

---

# PHASE 6 — Integration + polish

One final task: per-article SEO meta, contextual deep links from in-app pages, README update, final full-suite verification.

---

## Task 6.1: SEO meta, deep links, README, final smoke

**Role:** Frontend Developer (deep links + SEO) + Documentation Specialist (README) + QA Engineer (final smoke)

**Files:**
- Modify: each of the 10 v1 articles (add per-article meta description via `$this->start('meta')` block)
- Modify: `templates/Qsos/add.php` (link to /help/logging/add-qso)
- Modify: `templates/Templates/edit.php` (link to /help/templates/designer)
- Modify: `README.md` (link to /help)

### Steps

- [ ] **Step 1: Per-article SEO meta — add to each v1 article**

For each of the 10 v1 articles, add a meta block at the top (immediately after `extend('/Help/view')`):

```php
<?php
$this->assign('title', '<Article title> — eQSL Card Help');
$this->start('meta');
?>
<meta name="description" content="<one-sentence description, ~140 chars>">
<?php $this->end(); ?>
```

The 10 articles:
- `getting-started/welcome` → "What eQSL Card is, who it's for, and what you can do with it."
- `getting-started/create-account` → "Sign up walkthrough — email, callsign, password, what each field is for."
- `getting-started/first-card` → "5-minute quick start: from sign-up to generated, downloadable eQSL card."
- `logging/add-qso` → "Step-by-step guide to logging a contact or net check-in in eQSL Card."
- `logging/import` → "Bulk-import an ADIF or CSV export from your existing logging program."
- `cards/render` → "Pick a template + background and generate a personalised eQSL card from any logged QSO."
- `cards/share` → "Create a public share link for an eQSL card, optionally protected with a password."
- `templates/overview` → "How system, public, and personal templates work in eQSL Card."
- `admin/install` → "First-time installation + first-admin setup walkthrough for the eQSL Card site."
- `reference/glossary` → "Definitions of common amateur-radio and eQSL terms used across the eQSL Card site."

- [ ] **Step 2: Add a contextual deep link in `templates/Qsos/add.php`**

Find the page header. Insert a small "Help" link in the lede area or directly under the H1:

```php
<p class="form-text">
  <a href="/help/logging/add-qso">📖 How does this form work? →</a>
</p>
```

Place it ABOVE the QSO type toggle so users see it before they start filling in.

- [ ] **Step 3: Add a contextual deep link in `templates/Templates/edit.php`**

Find the H1 / page header at the top of the designer. Insert a "Designer guide" link nearby:

```php
<p class="form-text">
  <a href="/help/templates/designer">📖 Designer guide →</a>
</p>
```

Note that `/help/templates/designer` is still a stub at this point — that's fine, it returns 200 with a "coming soon" callout. The link points to where the content will land.

- [ ] **Step 4: Add the Help link to README.md**

Find an appropriate spot (likely the existing top-of-file feature list or a "Documentation" subsection). If no Documentation subsection exists, add one:

```markdown
## Documentation

In-app help portal lives at `/help` once the site is running. Covers
getting started, logging QSOs, generating cards, sharing, template
design, admin setup, and a glossary of amateur-radio terms.
```

- [ ] **Step 5: Full final smoke**

```bash
# Logged out
echo "=== logged-out routes ==="
for u in / /login /register /help /help/getting-started/welcome /help/cards/render /help/admin/install; do
  echo "$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080$u) $u"
done

# Logged in
rm -f /tmp/eqsl-cookies.txt
LOGIN_HTML=$(curl -s -c /tmp/eqsl-cookies.txt http://127.0.0.1:8080/login)
TOKEN=$(echo "$LOGIN_HTML" | grep -oP 'name="_csrfToken"[^>]*value="\K[^"]+' | head -1)
curl -s -b /tmp/eqsl-cookies.txt -c /tmp/eqsl-cookies.txt -L -X POST http://127.0.0.1:8080/login \
  --data-urlencode "_csrfToken=$TOKEN" --data-urlencode "email=contact@robbi.my" --data-urlencode "password=Password2345" -o /dev/null

echo "=== logged-in routes ==="
for u in /dashboard /qsos/new /templates/new /help; do
  code=$(curl -s -b /tmp/eqsl-cookies.txt -o /dev/null -w "%{http_code}" "http://127.0.0.1:8080$u")
  err=$(curl -s -b /tmp/eqsl-cookies.txt "http://127.0.0.1:8080$u" | grep -cE 'Notice:|Warning:|Fatal:|Undefined')
  echo "$code $u — $err errors"
done

echo "=== contextual deep links ==="
curl -s -b /tmp/eqsl-cookies.txt http://127.0.0.1:8080/qsos/new | grep -c '/help/logging/add-qso'
curl -s -b /tmp/eqsl-cookies.txt http://127.0.0.1:8080/templates/new | grep -c '/help/templates/designer'

# Per-article meta description present
echo "=== SEO meta description on first-card article ==="
curl -s http://127.0.0.1:8080/help/getting-started/first-card | grep -c 'meta name="description"'

# Full suite
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: all routes 200, both deep links present (1 each), meta description present (1), suite green.

- [ ] **Step 6: Commit**

```bash
git add templates/Help/ templates/Qsos/add.php templates/Templates/edit.php README.md
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
docs(help): SEO meta + contextual deep links + README

  - Per-article <meta name="description"> on all 10 v1 articles
    via $this->start('meta') blocks so each page has a unique
    summary for search engines + social-share unfurls. Page <title>
    also gets the "— eQSL Card Help" suffix for distinctiveness.
  - templates/Qsos/add.php gets a small "How does this form work?"
    link to /help/logging/add-qso above the QSO-type toggle.
  - templates/Templates/edit.php gets a "Designer guide" link near
    the H1 to /help/templates/designer (which is still a stub but
    returns 200 with a coming-soon callout — the link points to
    where the content will land).
  - README.md gains a Documentation subsection pointing at /help.

Final smoke confirms all logged-out + logged-in routes return 200
with zero PHP errors, deep links resolve, and per-article meta
descriptions are present in the served HTML. Phase 6 ships the
docs portal v1.
EOF
)"
```

---

## Self-review

After all six phases complete, run:

```bash
git log --oneline | head -20
```

Expected: ~13 commits, each scoped to one task. Files modified should be confined to `src/Service/`, `src/Controller/`, `config/routes.php`, `templates/Help/`, `templates/element/ui/`, `templates/layout/default.php`, `templates/Qsos/add.php`, `templates/Templates/edit.php`, `tests/TestCase/`, `webroot/css/theme.css`, `webroot/css/dist.css`, `webroot/files/help/.gitkeep`, `README.md`. Nothing else.

Final acceptance:

```bash
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: full suite green; the docs portal contributed ~20 new tests (10 HelpCatalog + 5 HelpController + 10 HelpElements) on top of the prior baseline.

**Spec coverage check:**

| Spec requirement | Implementing task |
|---|---|
| `App\Service\HelpCatalog` allow-list | Task 1.1 |
| `HelpController::index + view` with 404 gate | Task 1.2 |
| `/help` + `/help/{category}/{slug}` routes | Task 1.2 |
| Two-pane layout (sidebar + content) | Task 2.1 + 2.2 |
| Sidebar driven by `HelpCatalog::TREE` | Task 2.1 |
| Active-page highlight + aria-current | Task 2.1 |
| Mobile sidebar collapses to `<details>` | Task 2.1 (CSS) |
| Help index landing page | Task 2.2 |
| Navbar + footer entry | Task 2.2 |
| `ui/callout` element (note / tip / warning) | Task 3.1 |
| `ui/screenshot` element with light/dark variants | Task 3.2 |
| Mermaid opt-in via `$useMermaid` | Task 2.2 (wrapper has the conditional) |
| 24 stubbed articles | Task 4.1 |
| 10 fully-written v1 articles | Tasks 5.1–5.6 |
| Per-article SEO meta | Task 6.1 |
| Contextual deep links from in-app forms | Task 6.1 |
| README link | Task 6.1 |

No spec gaps.

---

## Notes for the implementer

- **Screenshots are user-driven.** Each article task lists the screenshot filenames. The .webp files don't ship in this plan's commits — the user captures them manually from their running app and drops them into `webroot/files/help/{category}/{slug}/` before launch. Articles render fine without them (broken image icon + alt text), so the portal is shippable even with screenshot debt.
- **The PHPUnit deprecation count is pre-existing** (CakePHP migrations plugin). It's unchanged by this work — don't try to fix it as part of the docs portal.
- **Stub-and-write strategy:** Phase 4 ships every article as a stub so the IA is final from day one. Phases 5.1–5.6 backfill content into 10 of those 24. The remaining 14 stubs stay as stubs — they're real pages with valid URLs that say "coming soon", so the sidebar feels finished and stubs upgrade later without changing routes or breaking links.
- **All commits authored solo as `Robbi Nespu <robbinespu@gmail.com>`** — every commit block in this plan enforces this via `-c user.name=... -c user.email=...`. Do not let any subagent introduce a `Co-Authored-By` trailer.
- **Mermaid script tag is opt-in per article.** Don't load it on pages that don't have a `<pre class="mermaid">` block — it's a wasted ~80KB. The wrapper handles this via the `$useMermaid` view var which articles set explicitly.
