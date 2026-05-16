# eQSL UI/UX Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Audit and polish the entire eQSL UI in five phases — lazy-loading + form polish + a11y basics, component extraction, responsive + deeper a11y, dark mode, and a production CSS build — without changing any underlying behaviour.

**Architecture:** Server-rendered PHP templates layered with Tailwind utility CSS, DaisyUI components, and Alpine.js for interactivity. Reusable view fragments live in `templates/element/ui/`. Theme state lives in `<html data-theme>`, persisted in localStorage, set before paint by an inline `<head>` script to avoid FOUC. Phase 5 replaces the Tailwind Play CDN with a locally-compiled CSS bundle.

**Tech Stack:** CakePHP 5, PHP 8.1, Tailwind 3 (Play CDN → npm CLI), DaisyUI 4, Alpine.js 3, Argon2id auth, PHPUnit 10.

**Commit author:** All commits MUST be authored as `Robbi Nespu <robbinespu@gmail.com>` with NO `Co-Authored-By` trailer. Use the pattern shown in commit blocks: `git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "..."`.

**Read first:** `docs/superpowers/specs/2026-05-12-ui-audit-design.md` (the approved spec).

---

## File structure map

### Files to create

- `templates/element/ui/callsign.php` — mono callsign render
- `templates/element/ui/badge_share_status.php` — shared/private/revoked badge
- `templates/element/ui/badge_qso_type.php` — `[NET]` badge
- `templates/element/ui/badge_transport.php` — internet-mediated transport badge
- `templates/element/ui/card_thumb.php` — card image with thumbnail fallback
- `templates/element/ui/empty_state.php` — `alert-info` with optional CTA
- `templates/element/ui/action_bar.php` — primary + secondary + cancel row
- `templates/element/ui/page_header.php` — `<h1>` + lede paragraph
- `templates/element/ui/dl_item.php` — one `<dt>/<dd>` pair with em-dash fallback
- `tests/TestCase/View/UiElementsTest.php` — assertions that each element renders the right HTML
- `webroot/js/focus-trap.js` — small reusable focus-trap helper (Phase 3)
- `tailwind.config.js` — Tailwind + DaisyUI config (Phase 5)
- `src/css/tailwind-source.css` — Tailwind CLI entry point (Phase 5)
- `webroot/css/dist.css` — built CSS bundle (Phase 5; committed to git)

### Files to modify

Phase 1:
- `templates/Admin/Settings/index.php`, `templates/Profile/index.php`, `templates/Public/index.php`, `templates/Qsos/render.php`, `templates/Uploads/edit.php` (add `loading="lazy"`)
- 14 form templates (placeholders + helper text + `req` markers)
- `templates/element/flash/*.php` (×5; add `role="alert"`)
- `templates/layout/default.php` (skip-to-content link)
- `templates/Qsos/add.php` (aria-live on autofill confirmation)
- `templates/Qsos/index.php` (aria-live on bulk-render progress; aria-label on close button)
- `webroot/css/theme.css` (skip-link visual rule)

Phase 2:
- ~30 templates updated to use the new `element/ui/*` partials

Phase 3:
- `templates/Qsos/index.php` (wire focus-trap helper to bulk-render modal)
- `templates/layout/default.php` (script tag for focus-trap)
- Any templates discovered broken in the viewport sweep

Phase 4:
- `templates/layout/default.php` (dual DaisyUI theme config, pre-paint script, toggle button)
- `webroot/css/theme.css` (`[data-theme="eqsl-dark"]` override block)
- `webroot/js/app.js` (toggle click handler)

Phase 5:
- `templates/layout/default.php` (replace CDN links with `dist.css`)
- `src/Middleware/SecurityHeadersMiddleware.php` (drop `cdn.tailwindcss.com` from CSP)
- `package.json` (add daisyui dep, `build:css` script)
- `README.md` (document the build step)

---

## Pre-flight verification

Before starting Task 1, confirm the working state:

```bash
git status
# Should be clean on branch m1-foundation

docker compose ps
# Should show all services up (php, db, mailhog, nginx)
# If not: docker compose up -d  and wait 5 seconds

curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8080/
# Should print 200

docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
# Should print: OK (329 tests, 1062 assertions)
```

If any of these fail, fix before starting any task.

---

# PHASE 1 — Quick wins

Three independent sub-phases. Each is its own commit.

---

## Task 1.1: Add `loading="lazy"` to remaining images

**Why:** Improves Largest Contentful Paint by deferring off-screen images. 10 of 18 `<img>` tags are missing the attribute. The page-hero card preview images stay eager (above-the-fold).

**Files:**
- Modify: `templates/Admin/Settings/index.php`
- Modify: `templates/Profile/index.php`
- Modify: `templates/Public/index.php`
- Modify: `templates/Qsos/render.php`
- Modify: `templates/Uploads/edit.php`

### Steps

- [ ] **Step 1: Find every `<img>` missing the attribute**

```bash
grep -rn "<img" templates/ | grep -v "loading=" | grep -v "/element/flash"
```

Expected output: 10 matches across the 5 files above (plus the hero images in Cards/view, Public/share, Public/preview which intentionally stay eager).

- [ ] **Step 2: Add `loading="lazy"` to `templates/Admin/Settings/index.php` — two preview images**

Use Edit tool on `templates/Admin/Settings/index.php`. Find the first match:

```php
      <img src="/files/templates/_default-bg.jpg?v=<?= h(filemtime(WWW_ROOT . 'files/templates/_default-bg.jpg')) ?>"
           class="img-fluid rounded" style="max-width: 240px" alt="default bg">
```

Replace with:

```php
      <img src="/files/templates/_default-bg.jpg?v=<?= h(filemtime(WWW_ROOT . 'files/templates/_default-bg.jpg')) ?>"
           class="img-fluid rounded" style="max-width: 240px" alt="default bg" loading="lazy">
```

Then find the bundled-fallback preview:

```php
      <img src="/files/templates/_demo-bg.jpg" class="img-fluid rounded" style="max-width: 240px" alt="bundled bg">
```

Replace with:

```php
      <img src="/files/templates/_demo-bg.jpg" class="img-fluid rounded" style="max-width: 240px" alt="bundled bg" loading="lazy">
```

- [ ] **Step 3: Add `loading="lazy"` to `templates/Profile/index.php` avatar**

Find:

```php
      <img src="/<?= h($user->avatar_path) ?>" alt="avatar" class="img-fluid rounded mb-3" style="max-width: 200px">
```

Replace with:

```php
      <img src="/<?= h($user->avatar_path) ?>" alt="avatar" class="img-fluid rounded mb-3" style="max-width: 200px" loading="lazy">
```

- [ ] **Step 4: Add `loading="lazy"` to `templates/Public/index.php` template thumbnails**

Find:

```php
          <?php if ($t->thumbnail_path): ?>
            <img src="/<?= h($t->thumbnail_path) ?>" alt="<?= h($t->name) ?>"
                 class="img-fluid rounded mb-2" loading="lazy">
          <?php endif; ?>
```

This one is already lazy. Leave it. Re-grep step 1 output — confirm no remaining match in `Public/index.php`.

- [ ] **Step 5: Add `loading="lazy"` to `templates/Qsos/render.php` existing-uploads thumbnails**

Find:

```php
        <img src="/<?= h($u->storage_path) ?>" alt="" class="img-fluid rounded" loading="lazy"
             style="aspect-ratio: 3/2; object-fit: cover;">
```

Already lazy. Re-grep — confirm no remaining match in `Qsos/render.php`.

- [ ] **Step 6: Add `loading="lazy"` to `templates/Uploads/edit.php` current-upload preview**

Find:

```php
    <img src="/<?= h($upload->storage_path) ?>" alt="" class="img-fluid rounded">
```

Replace with:

```php
    <img src="/<?= h($upload->storage_path) ?>" alt="" class="img-fluid rounded" loading="lazy">
```

- [ ] **Step 7: Verify zero remaining non-lazy images outside the hero allowlist**

```bash
grep -rn "<img" templates/ | grep -v 'loading=' | grep -vE "(Cards/view|Public/share|Public/preview)\.php"
```

Expected output: no matches.

- [ ] **Step 8: Smoke test all routes**

```bash
rm -f /tmp/eqsl-cookies.txt
LOGIN_HTML=$(curl -s -c /tmp/eqsl-cookies.txt http://127.0.0.1:8080/login)
TOKEN=$(echo "$LOGIN_HTML" | grep -oP 'name="_csrfToken"[^>]*value="\K[^"]+' | head -1)
curl -s -b /tmp/eqsl-cookies.txt -c /tmp/eqsl-cookies.txt -L -X POST http://127.0.0.1:8080/login \
  --data-urlencode "_csrfToken=$TOKEN" --data-urlencode "email=contact@robbi.my" --data-urlencode "password=Password2345" -o /dev/null
for u in / /dashboard /qsos/new /templates /cards /uploads /profile /admin/settings; do
  echo "$(curl -s -b /tmp/eqsl-cookies.txt -o /dev/null -w "%{http_code}" http://127.0.0.1:8080$u) $u"
done
```

Expected: every line prints `200 /…`.

- [ ] **Step 9: Commit**

```bash
git add templates/Admin/Settings/index.php templates/Profile/index.php templates/Uploads/edit.php
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
perf(ui): lazy-load remaining background previews and avatars

Adds loading="lazy" to every <img> below the fold. The page-hero card
previews on Cards/view, Public/share, and Public/preview stay eager
because they're above-the-fold content. Saves bytes + reduces LCP
without any visual change.
EOF
)"
```

---

## Task 1.2: Form polish sweep — placeholders, helper notes, required markers

**Why:** Form inputs are currently inconsistent — some have placeholders, some have `.form-text` helpers, some have neither. Every form should give the user enough context to fill it in correctly without guessing.

**Files:** 14 form templates.

### Steps

- [ ] **Step 1: Audit current form inputs**

```bash
grep -rn 'type="email"\|type="password"\|type="text"\|type="number"\|type="date"\|type="datetime-local"\|<textarea\|<select' templates/Auth templates/Qsos templates/Profile templates/Uploads templates/Public templates/Admin templates/Cards 2>/dev/null | wc -l
```

Records the baseline count (informational).

- [ ] **Step 2: Polish `templates/Auth/login.php`**

The login form already has labels and required markers. Audit only — confirm:
- Email has `autocomplete="username"`. ✓
- Password has `autocomplete="current-password"`. ✓
- Neither has a placeholder (their labels are sufficient). ✓

No changes needed. Verify by reading file and noting "no changes".

- [ ] **Step 3: Polish `templates/Auth/register.php`**

Add helper text to email and password fields. Find:

```php
    <div class="field">
      <label class="form-label" for="email">Email</label>
      <?= $this->Form->control('email', [
          'type'  => 'email',
          'class' => 'form-control',
          'label' => false,
          'id'    => 'email',
          'autocomplete' => 'email',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
```

Replace with:

```php
    <div class="field">
      <label class="form-label" for="email">Email <span class="req">*</span></label>
      <?= $this->Form->control('email', [
          'type'  => 'email',
          'class' => 'form-control',
          'label' => false,
          'id'    => 'email',
          'autocomplete' => 'email',
          'required' => true,
          'placeholder' => 'you@example.com',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
      <p class="form-text">Used for sign-in and password resets. We never share it.</p>
    </div>
```

Find the name field:

```php
    <div class="field">
      <label class="form-label" for="name">Name</label>
      <?= $this->Form->control('name', [
```

Replace with required marker:

```php
    <div class="field">
      <label class="form-label" for="name">Name <span class="req">*</span></label>
      <?= $this->Form->control('name', [
          'class' => 'form-control',
          'label' => false,
          'id'    => 'name',
          'required' => true,
          'placeholder' => 'Your name',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
```

Find the callsign field:

```php
    <div class="field">
      <label class="form-label" for="callsign">Callsign</label>
      <?= $this->Form->control('callsign', [
```

Replace with:

```php
    <div class="field">
      <label class="form-label" for="callsign">Callsign <span class="req">*</span></label>
      <?= $this->Form->control('callsign', [
          'class' => 'form-control',
          'label' => false,
          'id'    => 'callsign',
          'autocapitalize' => 'characters',
          'autocomplete' => 'off',
          'spellcheck' => 'false',
          'required' => true,
          'placeholder' => 'e.g. 9W2NSP',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
      <p class="form-text">Your amateur radio callsign. Used on the eQSL cards you generate.</p>
    </div>
```

- [ ] **Step 4: Polish `templates/Qsos/add.php`**

The Their callsign field has no placeholder. Find:

```php
        <label class="form-label" for="call-worked" x-text="isNet() ? 'Participant callsign' : 'Their callsign'">Their callsign</label>
        <input type="text" id="call-worked" name="call_worked" class="form-control"
               x-model="callsign"
               @input.debounce.300ms="onCallsignInput()"
               @blur="onCallsignInput()"
               autocomplete="off" autocapitalize="characters" spellcheck="false"
               required>
```

Add placeholder and required marker on label. Replace with:

```php
        <label class="form-label" for="call-worked">
          <span x-text="isNet() ? 'Participant callsign' : 'Their callsign'">Their callsign</span>
          <span class="req">*</span>
        </label>
        <input type="text" id="call-worked" name="call_worked" class="form-control"
               x-model="callsign"
               @input.debounce.300ms="onCallsignInput()"
               @blur="onCallsignInput()"
               autocomplete="off" autocapitalize="characters" spellcheck="false"
               placeholder="e.g. W1AW"
               required>
```

Find the Date/Time UTC field:

```php
      <?= $this->Form->control('qso_datetime_utc', [
          'type'  => 'datetime-local',
          'label' => 'Date / Time UTC',
          'class' => 'form-control',
          'required' => true,
      ]) ?>
```

Replace with:

```php
      <div class="field">
        <label class="form-label" for="qso-datetime-utc">Date / Time UTC <span class="req">*</span></label>
        <?= $this->Form->control('qso_datetime_utc', [
            'type'  => 'datetime-local',
            'label' => false,
            'id'    => 'qso-datetime-utc',
            'class' => 'form-control',
            'required' => true,
            'templates' => ['inputContainer' => '{{content}}'],
        ]) ?>
        <p class="form-text">UTC — not your local time. Use a UTC clock to be sure.</p>
      </div>
```

Find the Frequency field:

```php
      <?= $this->Form->control('frequency_mhz', [
          'label' => 'Frequency (MHz)',
          'class' => 'form-control',
      ]) ?>
```

Replace with:

```php
      <div class="field">
        <label class="form-label" for="frequency-mhz">Frequency (MHz)</label>
        <?= $this->Form->control('frequency_mhz', [
            'label' => false,
            'id'    => 'frequency-mhz',
            'class' => 'form-control',
            'placeholder' => 'e.g. 14.07415',
            'templates' => ['inputContainer' => '{{content}}'],
        ]) ?>
        <p class="form-text">Megahertz. Up to 4 decimal places.</p>
      </div>
```

Find the Grid square field:

```php
      <div class="field">
        <label class="form-label" for="grid-square">Grid square</label>
        <input type="text" id="grid-square" name="grid_square" class="form-control"
               x-model="gridSquare" placeholder="e.g. OJ02wx" autocomplete="off">
      </div>
```

Add helper. Replace with:

```php
      <div class="field">
        <label class="form-label" for="grid-square">Grid square</label>
        <input type="text" id="grid-square" name="grid_square" class="form-control"
               x-model="gridSquare" placeholder="e.g. OJ02wx" autocomplete="off">
        <p class="form-text">Maidenhead locator — 4 or 6 characters.</p>
      </div>
```

- [ ] **Step 5: Polish `templates/Qsos/render.php`**

The Template radio cards and Background radio cards already have descriptive labels. Audit only — no markup changes needed unless you spot a missing helper.

Confirm helper for the upload-new image disclosure stays as-is.

- [ ] **Step 6: Polish `templates/Profile/index.php`**

The QTH and Grid square fields already have placeholders + helper text from the prior pass. Audit only.

Confirm the avatar upload `<input type="file">` has accept and a helper. Currently:

```php
        <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp" class="form-control">
```

Add helper. Find the line and replace the following block:

```php
      <div class="field">
        <label class="form-label" for="avatar">Upload an avatar</label>
        <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp" class="form-control">
      </div>
```

With:

```php
      <div class="field">
        <label class="form-label" for="avatar">Upload an avatar</label>
        <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp" class="form-control">
        <p class="form-text">JPEG, PNG, or WebP. Square images look best.</p>
      </div>
```

- [ ] **Step 7: Polish `templates/Uploads/edit.php`**

Add helper to author + license fields.

Find:

```php
      <div class="field">
        <label class="form-label" for="author_name">Author / photographer</label>
        <input type="text" id="author_name" name="author_name"
               value="<?= h($upload->author_name ?? '') ?>"
               class="form-control" placeholder="Leave blank if unknown">
      </div>
```

Replace with:

```php
      <div class="field">
        <label class="form-label" for="author_name">Author / photographer</label>
        <input type="text" id="author_name" name="author_name"
               value="<?= h($upload->author_name ?? '') ?>"
               class="form-control" placeholder="Leave blank if unknown">
        <p class="form-text">Shown as the credit line on every future card that uses this image.</p>
      </div>
```

- [ ] **Step 8: Polish `templates/Auth/forgot.php` and `templates/Auth/reset.php`**

Both already polished in earlier session work. Audit:
- forgot.php: email has autocomplete + required ✓. No placeholder needed.
- reset.php: password has helper note + autocomplete + required ✓.

No changes.

- [ ] **Step 9: Polish `templates/Admin/Cleanup/index.php`**

The days filter has a label but no placeholder/helper. Find:

```php
      <input type="number" id="days" name="days" value="<?= h($days) ?>" min="1" class="form-control">
```

Replace with:

```php
      <input type="number" id="days" name="days" value="<?= h($days) ?>" min="1" class="form-control" placeholder="30">
```

Add helper after the input wrapper:

```php
    <div class="field">
      <label class="form-label" for="days">Older than (days)</label>
      <input type="number" id="days" name="days" value="<?= h($days) ?>" min="1" class="form-control" placeholder="30">
      <p class="form-text">Items older than this many days are eligible for cleanup.</p>
    </div>
```

- [ ] **Step 10: Polish `templates/Admin/CallsignDirectory/index.php`**

The CSV upload form should have a helper about expected format. The headers description below it covers this — no change needed.

The search box should have a placeholder. Find:

```php
      <input type="search" name="q" value="<?= h($search) ?>" placeholder="Callsign substring" class="form-control">
```

Already has placeholder ✓.

- [ ] **Step 11: Polish `templates/Public/index.php`**

Already standardised in earlier session. Audit only — confirm callsign fields have `autocomplete="off"` (yes), required markers (yes), placeholders are missing on most because the labels self-explain. Add a placeholder for "Their name" and "QTH":

Find:

```php
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="operator_name">Their name</label>
      <?= $this->Form->control('operator_name', [
          'class' => 'form-control', 'label' => false, 'id' => 'operator_name',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
```

Replace with:

```php
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="operator_name">Their name</label>
      <?= $this->Form->control('operator_name', [
          'class' => 'form-control', 'label' => false, 'id' => 'operator_name',
          'placeholder' => 'Optional',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
```

- [ ] **Step 12: Polish `templates/Public/unlock.php`**

Already minimal — single password input with autocomplete + required + label. No changes.

- [ ] **Step 13: Polish `templates/Cards/view.php` share password input**

Find:

```php
        <div class="field">
          <label class="form-label" for="share_password">Optional password</label>
          <input type="password" id="share_password" name="password" class="form-control"
                 autocomplete="new-password" placeholder="Leave blank for unprotected">
        </div>
```

Add helper. Replace with:

```php
        <div class="field">
          <label class="form-label" for="share_password">Optional password</label>
          <input type="password" id="share_password" name="password" class="form-control"
                 autocomplete="new-password" placeholder="Leave blank for unprotected">
          <p class="form-text">Recipients will need this password to view the shared card.</p>
        </div>
```

- [ ] **Step 14: Smoke test every form-bearing page**

```bash
for u in /login /register /password/forgot /qsos/new /qsos/import /uploads/1/edit /profile /admin/settings /admin/cleanup /admin/callsign-directory; do
  echo "$(curl -s -b /tmp/eqsl-cookies.txt -o /dev/null -w "%{http_code}" http://127.0.0.1:8080$u) $u"
done
```

Expected: every line is `200 /…` (or `200` after auth redirect resolves).

- [ ] **Step 15: Run PHPUnit**

```bash
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: `OK (329 tests, 1062 assertions)`.

- [ ] **Step 16: Commit**

```bash
git add templates/Auth/register.php templates/Qsos/add.php templates/Profile/index.php templates/Uploads/edit.php templates/Admin/Cleanup/index.php templates/Public/index.php templates/Cards/view.php
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
refactor(forms): unify placeholders, helper text, required markers

Every form input on every page now has either a placeholder that adds
context, a .form-text note explaining a non-obvious constraint, or
both. Required fields visibly marked with a .req asterisk on the
label. Optional fields say "Optional" in the placeholder where the
label doesn't already imply it.

No behaviour change — same field names, same validation rules. Just
makes the forms self-explanatory for newcomers.
EOF
)"
```

---

## Task 1.3: A11y basics — role="alert", skip-link, aria-live, aria-label

**Why:** Currently 8 ARIA attributes total across 41 templates. Adding the minimum set so screen-reader users get parity: announced flashes, a skip-to-content link, live regions for dynamic feedback, labels on icon-only buttons.

**Files:**
- Modify: `templates/element/flash/default.php`, `success.php`, `error.php`, `warning.php`, `info.php`
- Modify: `templates/layout/default.php`
- Modify: `templates/Qsos/add.php`
- Modify: `templates/Qsos/index.php`
- Modify: `webroot/css/theme.css`
- Create: `tests/TestCase/View/FlashElementAccessibilityTest.php`

### Steps

- [ ] **Step 1: Write the failing test for flash elements**

Create `tests/TestCase/View/FlashElementAccessibilityTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\View;

use Cake\TestSuite\TestCase;
use Cake\View\View;

final class FlashElementAccessibilityTest extends TestCase
{
    /**
     * Every flash element must wrap its message in role="alert" so
     * assistive tech announces it. We render each element with a stub
     * message and assert role="alert" appears in the output.
     */
    public function flashElementProvider(): array
    {
        return [
            ['flash/default', 'alert'],
            ['flash/success', 'alert alert-success'],
            ['flash/error',   'alert alert-danger'],
            ['flash/warning', 'alert alert-warning'],
            ['flash/info',    'alert alert-info'],
        ];
    }

    /**
     * @dataProvider flashElementProvider
     */
    public function testFlashElementHasRoleAlert(string $element, string $expectedClass): void
    {
        $view = new View();
        $out = $view->element($element, [
            'params' => [],
            'message' => 'Hello world',
        ]);
        $this->assertStringContainsString('role="alert"', $out);
        $this->assertStringContainsString($expectedClass, $out);
        $this->assertStringContainsString('Hello world', $out);
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/View/FlashElementAccessibilityTest.php --no-coverage 2>&1 | tail -10
```

Expected: 5 failures. Each asserts `role="alert"` is in the output, none of the flash elements currently emit it.

- [ ] **Step 3: Add role="alert" to `templates/element/flash/default.php`**

Replace the entire file with:

```php
<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
$extra = $params['class'] ?? '';
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="alert <?= h($extra) ?>" role="alert"><?= $message ?></div>
```

- [ ] **Step 4: Add role="alert" to `templates/element/flash/success.php`**

Replace the file with:

```php
<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="alert alert-success" role="alert"><?= $message ?></div>
```

- [ ] **Step 5: Add role="alert" to `templates/element/flash/error.php`**

Replace the file with:

```php
<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="alert alert-danger" role="alert"><?= $message ?></div>
```

- [ ] **Step 6: Add role="alert" to `templates/element/flash/warning.php`**

Replace the file with:

```php
<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="alert alert-warning" role="alert"><?= $message ?></div>
```

- [ ] **Step 7: Add role="alert" to `templates/element/flash/info.php`**

Replace the file with:

```php
<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="alert alert-info" role="alert"><?= $message ?></div>
```

- [ ] **Step 8: Run the flash test — should now pass**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/View/FlashElementAccessibilityTest.php --no-coverage 2>&1 | tail -10
```

Expected: `OK (5 tests, 15 assertions)`.

- [ ] **Step 9: Add skip-to-content link to `templates/layout/default.php`**

Find this line:

```php
<body data-theme="eqsl">
<nav class="navbar navbar-expand-lg">
```

Replace with:

```php
<body data-theme="eqsl">
<a href="#main-content" class="skip-link">Skip to main content</a>
<nav class="navbar navbar-expand-lg">
```

Then find:

```php
<main class="container">
```

Replace with:

```php
<main class="container" id="main-content" tabindex="-1">
```

- [ ] **Step 10: Add the skip-link CSS to `webroot/css/theme.css`**

Find this section (around line 100, inside the base section):

```css
small, .small { font-size: var(--t-sm); }
```

Add right after it:

```css
/* Skip link — invisible until keyboard-focused, then jumps to main content.
   First focusable element on the page so Tab from the top brings it up. */
.skip-link {
  position: absolute;
  top: -100px;
  left: var(--s-3);
  background: var(--fg-strong);
  color: #fff;
  padding: var(--s-2) var(--s-3);
  border-radius: var(--r-md);
  text-decoration: none;
  font-weight: 500;
  z-index: 9999;
  transition: top 120ms ease;
}
.skip-link:focus {
  top: var(--s-3);
  color: #fff;
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
```

- [ ] **Step 11: Add aria-live to the callsign autofill confirmation in `templates/Qsos/add.php`**

Find:

```php
        <p class="form-text text-success" x-show="lookupSource" x-cloak>
          Auto-filled from <strong x-text="lookupSource"></strong>.
          <button type="button" class="btn-link" style="padding: 0; min-height: 0;" @click="clearLookup()">Clear</button>
        </p>
```

Replace with:

```php
        <p class="form-text text-success" x-show="lookupSource" x-cloak
           role="status" aria-live="polite">
          Auto-filled from <strong x-text="lookupSource"></strong>.
          <button type="button" class="btn-link" style="padding: 0; min-height: 0;" @click="clearLookup()">Clear</button>
        </p>
```

- [ ] **Step 12: Add aria-live and aria-label to bulk-render modal in `templates/Qsos/index.php`**

Find:

```php
          <button type="button" class="btn-close" @click="closeModal()"></button>
```

Replace with:

```php
          <button type="button" class="btn-close" @click="closeModal()" aria-label="Close"></button>
```

Find:

```php
          <template x-if="started">
            <div>
              <p>Rendering <span x-text="done"></span> of <span x-text="total"></span>...</p>
              <div class="progress">
                <div class="progress-bar"
                     :style="`width: ${total ? (done * 100 / total) : 0}%`"
                     x-text="total ? Math.round(done * 100 / total) + '%' : '0%'"></div>
              </div>
```

Replace the outer `<div>` with one carrying aria-live:

```php
          <template x-if="started">
            <div role="status" aria-live="polite">
              <p>Rendering <span x-text="done"></span> of <span x-text="total"></span>...</p>
              <div class="progress">
                <div class="progress-bar"
                     :style="`width: ${total ? (done * 100 / total) : 0}%`"
                     x-text="total ? Math.round(done * 100 / total) + '%' : '0%'"></div>
              </div>
```

- [ ] **Step 13: Re-run smoke test logged-in**

```bash
rm -f /tmp/eqsl-cookies.txt
LOGIN_HTML=$(curl -s -c /tmp/eqsl-cookies.txt http://127.0.0.1:8080/login)
TOKEN=$(echo "$LOGIN_HTML" | grep -oP 'name="_csrfToken"[^>]*value="\K[^"]+' | head -1)
curl -s -b /tmp/eqsl-cookies.txt -c /tmp/eqsl-cookies.txt -L -X POST http://127.0.0.1:8080/login \
  --data-urlencode "_csrfToken=$TOKEN" --data-urlencode "email=contact@robbi.my" --data-urlencode "password=Password2345" -o /dev/null
echo "skip-link present on dashboard:"
curl -s -b /tmp/eqsl-cookies.txt http://127.0.0.1:8080/dashboard | grep -c "skip-link"
echo "aria-live on qsos/new:"
curl -s -b /tmp/eqsl-cookies.txt http://127.0.0.1:8080/qsos/new | grep -c "aria-live"
echo "PHPUnit:"
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: 1, 1, `OK (334 tests, 1077 assertions)` (329 + 5 new flash tests).

- [ ] **Step 14: Commit**

```bash
git add tests/TestCase/View/FlashElementAccessibilityTest.php \
  templates/element/flash/*.php \
  templates/layout/default.php \
  templates/Qsos/add.php templates/Qsos/index.php \
  webroot/css/theme.css
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
a11y(ui): role=alert on flashes, skip-link, aria-live regions

  - Flash messages (all 5 elements) now wrap in role="alert" so
    assistive tech announces them. Covered by a new
    FlashElementAccessibilityTest data-provider — 5 tests, 15
    assertions, runs as part of the regular suite.
  - "Skip to main content" link is the first focusable element on
    every page. CSS hides it offscreen until focus lands; Tab from
    the very top brings it into view above the navbar.
  - <main> gets id="main-content" + tabindex="-1" so the skip link
    can land focus there.
  - Callsign auto-complete confirmation on QSOs/add and bulk-render
    progress block on QSOs/index both get role="status" +
    aria-live="polite". Screen readers announce "Auto-filled from
    MCMC" / "Rendering 5 of 12..." as the values change.
  - Bulk-render modal close button gets aria-label="Close".
EOF
)"
```

---

# PHASE 2 — Component extraction

Pull repeated markup into Cake `element/ui/` partials. Mechanical refactor; no behaviour change.

---

## Task 2.1: Create the element files + the assertion test

**Why:** Establish the partials and assert each renders the expected HTML before touching call sites.

**Files:**
- Create: `templates/element/ui/callsign.php`
- Create: `templates/element/ui/badge_share_status.php`
- Create: `templates/element/ui/badge_qso_type.php`
- Create: `templates/element/ui/badge_transport.php`
- Create: `templates/element/ui/card_thumb.php`
- Create: `templates/element/ui/empty_state.php`
- Create: `templates/element/ui/action_bar.php`
- Create: `templates/element/ui/page_header.php`
- Create: `templates/element/ui/dl_item.php`
- Create: `tests/TestCase/View/UiElementsTest.php`

### Steps

- [ ] **Step 1: Write the failing assertion test for all 9 elements**

Create `tests/TestCase/View/UiElementsTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\View;

use Cake\TestSuite\TestCase;
use Cake\View\View;

final class UiElementsTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = new View();
    }

    public function testCallsignRendersMonoSpan(): void
    {
        $out = $this->view->element('ui/callsign', ['call' => '9W2NSP']);
        $this->assertStringContainsString('class="callsign"', $out);
        $this->assertStringContainsString('9W2NSP', $out);
    }

    public function testCallsignEmpty(): void
    {
        $out = $this->view->element('ui/callsign', ['call' => '']);
        $this->assertStringContainsString('—', $out);
    }

    public function testBadgeShareStatusShared(): void
    {
        $card = (object)['share_slug' => 'abc', 'share_revoked_at' => null];
        $out = $this->view->element('ui/badge_share_status', ['card' => $card]);
        $this->assertStringContainsString('bg-success', $out);
        $this->assertStringContainsString('Shared', $out);
    }

    public function testBadgeShareStatusRevoked(): void
    {
        $card = (object)['share_slug' => 'abc', 'share_revoked_at' => new \DateTime()];
        $out = $this->view->element('ui/badge_share_status', ['card' => $card]);
        $this->assertStringContainsString('bg-secondary', $out);
        $this->assertStringContainsString('revoked', $out);
    }

    public function testBadgeShareStatusPrivate(): void
    {
        $card = (object)['share_slug' => null, 'share_revoked_at' => null];
        $out = $this->view->element('ui/badge_share_status', ['card' => $card]);
        $this->assertStringContainsString('Private', $out);
    }

    public function testBadgeQsoTypeNet(): void
    {
        $qso = (object)['qso_type' => 'net', 'net_title' => 'Daily Net'];
        $out = $this->view->element('ui/badge_qso_type', ['qso' => $qso]);
        $this->assertStringContainsString('NET', $out);
        $this->assertStringContainsString('Daily Net', $out);
    }

    public function testBadgeQsoTypeContact(): void
    {
        $qso = (object)['qso_type' => 'contact'];
        $out = $this->view->element('ui/badge_qso_type', ['qso' => $qso]);
        $this->assertSame('', trim($out));
    }

    public function testBadgeTransportInternet(): void
    {
        $qso = (object)['transport' => 'echolink', 'transport_meta' => 'node 12345'];
        $out = $this->view->element('ui/badge_transport', ['qso' => $qso]);
        $this->assertStringContainsString('ECHOLINK', $out);
    }

    public function testBadgeTransportRf(): void
    {
        $qso = (object)['transport' => 'rf', 'transport_meta' => null];
        $out = $this->view->element('ui/badge_transport', ['qso' => $qso]);
        $this->assertSame('', trim($out));
    }

    public function testEmptyStateWithCta(): void
    {
        $out = $this->view->element('ui/empty_state', [
            'message' => 'Nothing here yet.',
            'cta_url' => '/x',
            'cta_label' => 'Add one',
        ]);
        $this->assertStringContainsString('Nothing here yet.', $out);
        $this->assertStringContainsString('href="/x"', $out);
        $this->assertStringContainsString('Add one', $out);
        $this->assertStringContainsString('alert-info', $out);
    }

    public function testEmptyStateMessageOnly(): void
    {
        $out = $this->view->element('ui/empty_state', [
            'message' => 'Empty.',
        ]);
        $this->assertStringContainsString('Empty.', $out);
        $this->assertStringNotContainsString('<a ', $out);
    }

    public function testPageHeaderWithLede(): void
    {
        $out = $this->view->element('ui/page_header', [
            'title' => 'Logbook',
            'lede'  => 'Your QSOs.',
        ]);
        $this->assertStringContainsString('<h1>Logbook</h1>', $out);
        $this->assertStringContainsString('Your QSOs.', $out);
    }

    public function testPageHeaderWithoutLede(): void
    {
        $out = $this->view->element('ui/page_header', ['title' => 'Hello']);
        $this->assertStringContainsString('<h1>Hello</h1>', $out);
        $this->assertStringNotContainsString('<p>', $out);
    }

    public function testDlItemWithValue(): void
    {
        $out = $this->view->element('ui/dl_item', [
            'term'  => 'Callsign',
            'value' => '9W2NSP',
        ]);
        $this->assertStringContainsString('<dt class="col-sm-3">Callsign</dt>', $out);
        $this->assertStringContainsString('<dd class="col-sm-9">9W2NSP</dd>', $out);
    }

    public function testDlItemEmptyValueShowsEmDash(): void
    {
        $out = $this->view->element('ui/dl_item', [
            'term'  => 'Callsign',
            'value' => '',
        ]);
        $this->assertStringContainsString('—', $out);
    }
}
```

- [ ] **Step 2: Run the test and confirm it fails**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/View/UiElementsTest.php --no-coverage 2>&1 | tail -5
```

Expected: All 15 tests fail with "Element X.php was not found" errors.

- [ ] **Step 3: Create `templates/element/ui/callsign.php`**

```php
<?php
/**
 * Renders a callsign in the mono typeface used everywhere callsigns appear.
 *
 * @var \App\View\AppView $this
 * @var string|null $call
 */
?>
<span class="callsign"><?= h($call ?: '—') ?></span>
```

- [ ] **Step 4: Create `templates/element/ui/badge_share_status.php`**

```php
<?php
/**
 * Card share-status badge — shared / private / share-revoked.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Card|object $card
 */
?>
<?php if (!empty($card->share_revoked_at)): ?>
  <span class="badge bg-secondary">Share revoked</span>
<?php elseif (!empty($card->share_slug)): ?>
  <span class="badge bg-success">Shared</span>
<?php else: ?>
  <span class="badge bg-light">Private</span>
<?php endif; ?>
```

- [ ] **Step 5: Create `templates/element/ui/badge_qso_type.php`**

```php
<?php
/**
 * NET badge — renders when the QSO is a net check-in, otherwise nothing.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Qso|object $qso
 */
?>
<?php if (($qso->qso_type ?? 'contact') === 'net'): ?>
  <?php $title = !empty($qso->net_title) ? 'Net check-in: ' . $qso->net_title : 'Net check-in'; ?>
  <span class="badge bg-info" title="<?= h($title) ?>">NET</span>
<?php endif; ?>
```

- [ ] **Step 6: Create `templates/element/ui/badge_transport.php`**

```php
<?php
/**
 * Transport badge — renders for non-RF (internet-mediated) QSOs only.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Qso|object $qso
 */
$transport = $qso->transport ?? null;
?>
<?php if ($transport && \App\Service\Transport::isInternet($transport)): ?>
  <?php
  $label = \App\Service\Transport::label($transport);
  $meta = !empty($qso->transport_meta) ? ' · ' . $qso->transport_meta : '';
  ?>
  <span class="badge bg-secondary" title="<?= h($label . $meta) ?>">
    <?= h(strtoupper((string)$transport)) ?>
  </span>
<?php endif; ?>
```

- [ ] **Step 7: Create `templates/element/ui/card_thumb.php`**

```php
<?php
/**
 * Card preview thumbnail with the thumb path fallback to the full image.
 * Always lazy-loaded.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Card|object $card
 * @var string $alt
 */
$thumbPath = \App\Service\CardRenderer::thumbPathFor($card->png_path);
$previewSrc = is_file(WWW_ROOT . $thumbPath) ? $thumbPath : $card->png_path;
?>
<img src="/<?= h($previewSrc) ?>"
     class="card-img-top"
     alt="<?= h($alt ?? 'eQSL card preview') ?>"
     loading="lazy">
```

- [ ] **Step 8: Create `templates/element/ui/empty_state.php`**

```php
<?php
/**
 * Empty-state banner — info alert with an optional call-to-action link.
 *
 * @var \App\View\AppView $this
 * @var string $message
 * @var string|null $cta_url    optional
 * @var string|null $cta_label  optional (only used when $cta_url set)
 */
?>
<div class="alert alert-info" role="status">
  <?= h($message) ?>
  <?php if (!empty($cta_url)): ?>
    <a href="<?= h($cta_url) ?>"><?= h($cta_label ?? 'Go') ?> &rarr;</a>
  <?php endif; ?>
</div>
```

- [ ] **Step 9: Create `templates/element/ui/action_bar.php`**

```php
<?php
/**
 * Form action button row — primary submit, optional secondary, optional cancel.
 * Renders inside an existing <form>; just emits the buttons.
 *
 * @var \App\View\AppView $this
 * @var string|null $primary_label   default "Save"
 * @var string|null $secondary_label optional
 * @var string|null $secondary_url   optional
 * @var string|null $cancel_label    default "Cancel"
 * @var string|null $cancel_url      optional; if set, renders the cancel link
 */
$primaryLabel   = $primary_label   ?? 'Save';
$cancelLabel    = $cancel_label    ?? 'Cancel';
$secondaryLabel = $secondary_label ?? null;
$secondaryUrl   = $secondary_url   ?? null;
$cancelUrl      = $cancel_url      ?? null;
?>
<div class="d-flex gap-2 mt-4 flex-wrap">
  <button class="btn btn-primary"><?= h($primaryLabel) ?></button>
  <?php if ($secondaryLabel && $secondaryUrl): ?>
    <a class="btn btn-outline-primary" href="<?= h($secondaryUrl) ?>"><?= h($secondaryLabel) ?></a>
  <?php endif; ?>
  <?php if ($cancelUrl): ?>
    <a class="btn btn-secondary" href="<?= h($cancelUrl) ?>"><?= h($cancelLabel) ?></a>
  <?php endif; ?>
</div>
```

- [ ] **Step 10: Create `templates/element/ui/page_header.php`**

```php
<?php
/**
 * Page header — H1 + optional lede paragraph. Triggers the
 * h1:first-child + p lede styling from theme.css.
 *
 * @var \App\View\AppView $this
 * @var string $title
 * @var string|null $lede  optional
 * @var bool $escape_title default true — set false to allow inline HTML
 *                         (e.g. for the QSO view "QSO with <span class=callsign>...")
 */
$escape = $escape_title ?? true;
?>
<h1><?= $escape ? h($title) : $title ?></h1>
<?php if (!empty($lede)): ?>
  <p><?= h($lede) ?></p>
<?php endif; ?>
```

- [ ] **Step 11: Create `templates/element/ui/dl_item.php`**

```php
<?php
/**
 * One row of a <dl class="row dl-stack"> — term + value with em-dash
 * fallback when the value is empty.
 *
 * @var \App\View\AppView $this
 * @var string $term
 * @var string|null $value
 * @var bool $escape_value default true
 */
$escape = $escape_value ?? true;
$value  = $value ?? '';
$hasValue = $value !== '' && $value !== null;
?>
<dt class="col-sm-3"><?= h($term) ?></dt>
<dd class="col-sm-9"><?php if ($hasValue): ?><?= $escape ? h($value) : $value ?><?php else: ?><span class="text-muted">—</span><?php endif; ?></dd>
```

- [ ] **Step 12: Run the test — all 15 should pass**

```bash
docker compose exec -T php vendor/bin/phpunit tests/TestCase/View/UiElementsTest.php --no-coverage 2>&1 | tail -5
```

Expected: `OK (15 tests, 28+ assertions)`.

- [ ] **Step 13: Run the full suite**

```bash
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: `OK (349 tests, …)` — 329 prior + 5 flash + 15 ui-elements.

- [ ] **Step 14: Commit**

```bash
git add templates/element/ui/ tests/TestCase/View/UiElementsTest.php
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
refactor(ui): introduce templates/element/ui/ component library

Nine reusable Cake elements covering the visual patterns that
currently duplicate across many templates:

  callsign            mono-styled callsign with em-dash fallback
  badge_share_status  shared / private / revoked
  badge_qso_type      [NET] badge with title tooltip
  badge_transport     internet-mediated transport pill
  card_thumb          card image with thumbnail-path fallback, lazy
  empty_state         info alert with optional CTA link
  action_bar          primary + optional secondary + optional cancel row
  page_header         <h1> + optional lede
  dl_item             one <dt>/<dd> with em-dash fallback

Each has a unit assertion in UiElementsTest (15 tests, exercising
the if-branches). No call sites updated yet — that lands in the next
commit so the new components stand on their own first.
EOF
)"
```

---

## Task 2.2: Replace inline patterns with elements in the user-facing templates

**Why:** Now that the elements exist and are tested, swap the duplicated markup for `$this->element('ui/X', ...)` calls.

**Files:**
- Modify: `templates/Cards/index.php`
- Modify: `templates/Cards/view.php`
- Modify: `templates/Qsos/index.php`
- Modify: `templates/Qsos/view.php`
- Modify: `templates/Qsos/render.php`
- Modify: `templates/Qsos/import.php`
- Modify: `templates/Templates/index.php`
- Modify: `templates/Uploads/index.php`
- Modify: `templates/Dashboard/index.php`
- Modify: `templates/Public/share.php`
- Modify: `templates/Public/preview.php`

### Steps

- [ ] **Step 1: Update `templates/Cards/index.php` to use empty_state + card_thumb + badge_share_status + page_header**

Replace the file content with:

```php
<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => "Every eQSL you've generated. Click one to view, share, or download.",
]) ?>

<?php if ($cards->count() === 0): ?>
  <?= $this->element('ui/empty_state', [
      'message'   => "You haven't generated any cards yet.",
      'cta_url'   => '/qsos',
      'cta_label' => 'Render one from a QSO',
  ]) ?>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($cards as $card): ?>
      <div class="col-md-4">
        <div class="card h-100">
          <a href="/cards/<?= $card->id ?>">
            <?= $this->element('ui/card_thumb', ['card' => $card]) ?>
          </a>
          <div class="card-body">
            <?php $qsoData = json_decode((string)$card->qso_data_json, true) ?: []; ?>
            <h5 class="card-title mb-1"><?= h($qsoData['callsign'] ?? '—') ?></h5>
            <p class="card-text mb-2">
              <?= h($qsoData['qso_datetime_utc'] ?? '') ?>
              · <?= h($qsoData['band'] ?? '') ?>
              · <?= h($qsoData['mode'] ?? '') ?>
            </p>
            <?= $this->element('ui/badge_share_status', ['card' => $card]) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <nav class="mt-4"><?= $this->Paginator->numbers() ?></nav>
<?php endif; ?>
```

- [ ] **Step 2: Update `templates/Cards/view.php` to use badge_share_status (no other changes — the share section is too page-specific)**

In `templates/Cards/view.php`, find the current QSO-details `<dl>` block and leave it (we'll do dl_item in a separate sweep — it's risky to touch dl_item in batch with elements still warm). For now, only update the visible badge if present.

The current file doesn't render a share-status badge in view — it shows the share section inline. Skip this file in this task.

- [ ] **Step 3: Update `templates/Qsos/index.php` to use empty_state + callsign + badges + page_header**

Find the header:

```php
<h1><?= h($title) ?></h1>
<p>Your station log. Filter by callsign, type, band, or date range, then render eQSL cards individually or in bulk.</p>
```

Replace with:

```php
<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Your station log. Filter by callsign, type, band, or date range, then render eQSL cards individually or in bulk.',
]) ?>
```

Find the empty state:

```php
<?php if ($qsos->count() === 0): ?>
  <div class="alert alert-info">No QSOs match your filter. <a href="/qsos/new">Add one</a> or <a href="/qsos/import">import an ADIF / CSV log</a>.</div>
<?php else: ?>
```

Replace with:

```php
<?php if ($qsos->count() === 0): ?>
  <?= $this->element('ui/empty_state', [
      'message'   => 'No QSOs match your filter.',
      'cta_url'   => '/qsos/new',
      'cta_label' => 'Add one',
  ]) ?>
<?php else: ?>
```

Find the table row:

```php
        <td>
          <strong><?= h($qso->call_worked) ?></strong>
          <?php if (($qso->qso_type ?? 'contact') === 'net'): ?>
            <span class="badge bg-info text-dark ms-1" title="Net check-in<?= $qso->net_title ? ': ' . h($qso->net_title) : '' ?>">NET</span>
          <?php endif; ?>
          <?php if (\App\Service\Transport::isInternet($qso->transport ?? null)): ?>
            <span class="badge bg-secondary ms-1" title="<?= h(\App\Service\Transport::label($qso->transport)) ?><?= $qso->transport_meta ? ' · ' . h($qso->transport_meta) : '' ?>">
              <?= h(strtoupper((string)$qso->transport)) ?>
            </span>
          <?php endif; ?>
        </td>
```

Replace with:

```php
        <td>
          <strong><?= h($qso->call_worked) ?></strong>
          <?= $this->element('ui/badge_qso_type', ['qso' => $qso]) ?>
          <?= $this->element('ui/badge_transport', ['qso' => $qso]) ?>
        </td>
```

- [ ] **Step 4: Update `templates/Qsos/view.php` page_header + callsign + badges**

Find:

```php
<h1>
  <?= $isNet ? 'Net check-in by' : 'QSO with' ?>
  <span class="callsign"><?= h($qso->call_worked) ?></span>
  <?php if ($isNet): ?>
    <span class="badge bg-info ms-2">NET</span>
  <?php endif; ?>
</h1>
<p>
  Logged on <?= h($qso->qso_datetime_utc?->format('Y-m-d H:i')) ?> UTC
  via <?= h(\App\Service\Transport::label($qso->transport ?? null)) ?>.
</p>
```

The H1 has mixed inline HTML — use `escape_title => false` on page_header. Replace with:

```php
<?php
$h1 = ($isNet ? 'Net check-in by ' : 'QSO with ')
    . $this->element('ui/callsign', ['call' => $qso->call_worked])
    . ' '
    . $this->element('ui/badge_qso_type', ['qso' => $qso]);
?>
<?= $this->element('ui/page_header', [
    'title' => $h1,
    'lede'  => 'Logged on ' . h($qso->qso_datetime_utc?->format('Y-m-d H:i')) . ' UTC via '
               . h(\App\Service\Transport::label($qso->transport ?? null)) . '.',
    'escape_title' => false,
]) ?>
```

- [ ] **Step 5: Update `templates/Qsos/import.php` page_header**

Find:

```php
<h1>Import logbook</h1>
```

Replace with:

```php
<?php if ($stage === 'upload'): ?>
  <?= $this->element('ui/page_header', [
      'title' => 'Import logbook',
      'lede'  => "Upload an ADIF (.adi/.adif) or CSV (.csv) export from your logging program. We'll parse it locally, show you a summary, and only persist the rows you confirm.",
  ]) ?>
<?php else: ?>
  <?= $this->element('ui/page_header', [
      'title' => 'Import logbook',
      'lede'  => 'Review what the parser found, then confirm the import.',
  ]) ?>
<?php endif; ?>
```

Then DELETE the now-redundant `<p>` paragraphs that previously held this lede text (search for `<p>Upload an ADIF` and `<p>Review what` in the file and remove those `<p>...</p>` blocks).

- [ ] **Step 6: Update `templates/Qsos/render.php` page_header + callsign**

Find:

```php
<h1><?= h($title) ?></h1>
<p>
  QSO with <strong><?= h($qso->call_worked) ?></strong>
  on <?= h($qso->qso_datetime_utc?->format('Y-m-d H:i')) ?> UTC
  <?php if ($qso->band): ?>· <?= h($qso->band) ?><?php endif; ?>
  <?php if ($qso->mode): ?>· <?= h($qso->mode) ?><?php endif; ?>
</p>
```

Replace with:

```php
<?php
$callsignHtml = $this->element('ui/callsign', ['call' => $qso->call_worked]);
$detailLine = 'QSO with ' . $callsignHtml
    . ' on ' . h($qso->qso_datetime_utc?->format('Y-m-d H:i')) . ' UTC'
    . ($qso->band ? ' · ' . h($qso->band) : '')
    . ($qso->mode ? ' · ' . h($qso->mode) : '');
?>
<h1><?= h($title) ?></h1>
<p><?= $detailLine ?></p>
```

(Skip page_header element here because the lede is computed inline with HTML.)

- [ ] **Step 7: Update `templates/Templates/index.php` empty_state inside the tab partials**

Find inside the `$renderGrid` closure:

```php
      <?php if ($collection->count() === 0): ?>
        <div class="alert alert-info"><?= h($emptyMessage) ?></div>
      <?php else: ?>
```

Replace with:

```php
      <?php if ($collection->count() === 0): ?>
        <?= $this->element('ui/empty_state', ['message' => $emptyMessage]) ?>
      <?php else: ?>
```

- [ ] **Step 8: Update `templates/Uploads/index.php` empty_state + page_header**

Find:

```php
<h1><?= h($title) ?></h1>
<p class="text-muted">Background images you've uploaded. Pick any of these in <a href="/qsos">render-from-QSO</a> to skip re-uploading.</p>
```

Replace with:

```php
<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => "Background images you've uploaded. Pick any of these when you render an eQSL to skip re-uploading.",
]) ?>
```

Find:

```php
<?php if ($uploads->count() === 0): ?>
  <div class="alert alert-info">
    No backgrounds yet. Upload one via the <a href="/qsos">render-from-QSO flow</a> or the <a href="/">guest form</a> and it'll appear here.
  </div>
<?php else: ?>
```

Replace with:

```php
<?php if ($uploads->count() === 0): ?>
  <?= $this->element('ui/empty_state', [
      'message'   => 'No backgrounds yet.',
      'cta_url'   => '/qsos',
      'cta_label' => 'Upload one via the render-from-QSO flow',
  ]) ?>
<?php else: ?>
```

- [ ] **Step 9: Update `templates/Dashboard/index.php` empty_state in two places**

Find:

```php
    <?php if ($recentCards->count() === 0): ?>
      <div class="alert alert-light">No cards yet. <a href="/qsos">Render one from a QSO</a>.</div>
    <?php else: ?>
```

Replace with:

```php
    <?php if ($recentCards->count() === 0): ?>
      <?= $this->element('ui/empty_state', [
          'message'   => 'No cards yet.',
          'cta_url'   => '/qsos',
          'cta_label' => 'Render one from a QSO',
      ]) ?>
    <?php else: ?>
```

Find:

```php
    <?php if ($recentQsos->count() === 0): ?>
      <div class="alert alert-light">No QSOs yet. <a href="/qsos/new">Add one</a> or <a href="/qsos/import">import a log</a>.</div>
    <?php else: ?>
```

Replace with:

```php
    <?php if ($recentQsos->count() === 0): ?>
      <?= $this->element('ui/empty_state', [
          'message'   => 'No QSOs yet.',
          'cta_url'   => '/qsos/new',
          'cta_label' => 'Add one',
      ]) ?>
    <?php else: ?>
```

Also update the callsign rendering in the recent QSOs table. Find:

```php
              <td><a href="/qsos/<?= $q->id ?>"><span class="callsign"><?= h($q->call_worked) ?></span></a></td>
```

Replace with:

```php
              <td><a href="/qsos/<?= $q->id ?>"><?= $this->element('ui/callsign', ['call' => $q->call_worked]) ?></a></td>
```

- [ ] **Step 10: Update `templates/Public/share.php` callsign**

Find:

```php
<h1>eQSL card from <?= h($operatorCallsign ?: '—') ?></h1>
```

Replace with:

```php
<h1>eQSL card from <?= $this->element('ui/callsign', ['call' => $operatorCallsign]) ?></h1>
```

Find in the dl:

```php
  <dt class="col-sm-3">Confirmed by</dt><dd class="col-sm-9"><span class="callsign"><?= h($operatorCallsign ?: '—') ?></span></dd>
  <dt class="col-sm-3">For QSO with</dt><dd class="col-sm-9"><span class="callsign"><?= h($qsoData['callsign'] ?? '—') ?></span></dd>
```

Replace with:

```php
  <dt class="col-sm-3">Confirmed by</dt><dd class="col-sm-9"><?= $this->element('ui/callsign', ['call' => $operatorCallsign]) ?></dd>
  <dt class="col-sm-3">For QSO with</dt><dd class="col-sm-9"><?= $this->element('ui/callsign', ['call' => $qsoData['callsign'] ?? '']) ?></dd>
```

- [ ] **Step 11: Smoke test every touched route**

```bash
rm -f /tmp/eqsl-cookies.txt
LOGIN_HTML=$(curl -s -c /tmp/eqsl-cookies.txt http://127.0.0.1:8080/login)
TOKEN=$(echo "$LOGIN_HTML" | grep -oP 'name="_csrfToken"[^>]*value="\K[^"]+' | head -1)
curl -s -b /tmp/eqsl-cookies.txt -c /tmp/eqsl-cookies.txt -L -X POST http://127.0.0.1:8080/login \
  --data-urlencode "_csrfToken=$TOKEN" --data-urlencode "email=contact@robbi.my" --data-urlencode "password=Password2345" -o /dev/null
for u in /dashboard /qsos /qsos/new /qsos/import /templates /cards /uploads; do
  code=$(curl -s -b /tmp/eqsl-cookies.txt -o /dev/null -w "%{http_code}" "http://127.0.0.1:8080$u")
  err=$(curl -s -b /tmp/eqsl-cookies.txt "http://127.0.0.1:8080$u" | grep -cE 'Notice:|Warning:|Fatal|Undefined')
  echo "$code $u — $err errors"
done
```

Expected: every line is `200 /... — 0 errors`.

- [ ] **Step 12: Run full test suite**

```bash
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: `OK (349 tests, …)`.

- [ ] **Step 13: Commit**

```bash
git add templates/Cards/index.php templates/Qsos/index.php templates/Qsos/view.php \
  templates/Qsos/import.php templates/Qsos/render.php templates/Templates/index.php \
  templates/Uploads/index.php templates/Dashboard/index.php templates/Public/share.php
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
refactor(ui): adopt element/ui/ components across user-facing pages

Replaces inline duplication with element calls:

  Cards/index           page_header + empty_state + card_thumb + badge_share_status
  Qsos/index            page_header + empty_state + badge_qso_type + badge_transport
  Qsos/view             page_header + callsign + badge_qso_type
  Qsos/render           callsign in the detail-line
  Qsos/import           page_header (per-stage)
  Templates/index       empty_state in each tab pane
  Uploads/index         page_header + empty_state
  Dashboard             empty_state (x2) + callsign in recent-QSOs table
  Public/share          callsign in H1 + dl rows

Identical rendered HTML, single source of truth for tweaks.
EOF
)"
```

---

## Task 2.3: Apply elements to the admin-side templates

**Why:** Same patterns recur in admin pages; close the loop on Phase 2 by sweeping admin too.

**Files:**
- Modify: `templates/Admin/Templates/pending.php`
- Modify: `templates/Admin/Users/index.php`
- Modify: `templates/Admin/Users/edit.php`
- Modify: `templates/Admin/Cards/index.php`
- Modify: `templates/Admin/Uploads/index.php`
- Modify: `templates/Admin/Audit/index.php`
- Modify: `templates/Admin/Dashboard/index.php`
- Modify: `templates/Admin/CallsignDirectory/index.php`

### Steps

- [ ] **Step 1: Replace empty_state in `templates/Admin/Templates/pending.php`**

Find:

```php
<?php if ($pending->count() === 0): ?>
  <div class="alert alert-info">No templates awaiting review.</div>
<?php else: ?>
```

Replace with:

```php
<?php if ($pending->count() === 0): ?>
  <?= $this->element('ui/empty_state', ['message' => 'No templates awaiting review.']) ?>
<?php else: ?>
```

Also find the callsign reference:

```php
            <span class="callsign"><?= h($t->user->callsign ?? '?') ?></span>
```

Replace with:

```php
            <?= $this->element('ui/callsign', ['call' => $t->user->callsign ?? '?']) ?>
```

- [ ] **Step 2: Update `templates/Admin/Users/index.php` page_header + callsign**

Find:

```php
<h1><?= h($title) ?></h1>
<p>Every registered operator. Search to find one, click Edit to change role, Delete to soft-delete.</p>
```

Replace with:

```php
<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Every registered operator. Search to find one, click Edit to change role, Delete to soft-delete.',
]) ?>
```

Find:

```php
        <td><span class="callsign"><?= h($u->callsign) ?></span></td>
```

Replace with:

```php
        <td><?= $this->element('ui/callsign', ['call' => $u->callsign]) ?></td>
```

- [ ] **Step 3: Update `templates/Admin/Users/edit.php` page_header + callsign**

Find:

```php
<h1><?= h($title) ?></h1>
<p>Promote, demote, or update this user. Self-demotion is blocked at the controller level.</p>
```

Replace with:

```php
<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Promote, demote, or update this user. Self-demotion is blocked at the controller level.',
]) ?>
```

Find:

```php
  <dt class="col-sm-3">Callsign</dt><dd class="col-sm-9"><span class="callsign"><?= h($user->callsign) ?></span></dd>
```

Replace with:

```php
  <dt class="col-sm-3">Callsign</dt><dd class="col-sm-9"><?= $this->element('ui/callsign', ['call' => $user->callsign]) ?></dd>
```

- [ ] **Step 4: Update `templates/Admin/Cards/index.php` page_header + badge_share_status**

Find:

```php
<h1><?= h($title) ?></h1>
<p>Every card ever rendered — by users and by guests. Filter by owner type or date.</p>
```

Replace with:

```php
<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Every card ever rendered — by users and by guests. Filter by owner type or date.',
]) ?>
```

Find the status badge block (uses `bg-light text-dark` — already legacy):

```php
        <td>
          <?php if ($c->deleted_at): ?><span class="badge bg-danger">deleted</span>
          <?php elseif ($c->share_revoked_at): ?><span class="badge bg-secondary">share revoked</span>
          <?php elseif ($c->share_slug): ?><span class="badge bg-success">shared</span>
          <?php else: ?><span class="badge bg-light text-dark">private</span>
          <?php endif; ?>
        </td>
```

Replace with:

```php
        <td>
          <?php if ($c->deleted_at): ?>
            <span class="badge bg-danger">deleted</span>
          <?php else: ?>
            <?= $this->element('ui/badge_share_status', ['card' => $c]) ?>
          <?php endif; ?>
        </td>
```

(Deleted state is admin-specific so it stays inline; the rest goes through the element.)

- [ ] **Step 5: Update `templates/Admin/Uploads/index.php` empty_state + page_header**

Find:

```php
<h1><?= h($title) ?></h1>
<p>Every background image on the site, owned by users or guests. Edit attribution or soft-delete from here.</p>
```

Replace with:

```php
<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Every background image on the site, owned by users or guests. Edit attribution or soft-delete from here.',
]) ?>
```

Find:

```php
<?php if ($uploads->count() === 0): ?>
  <div class="alert alert-info">No uploads match your filter.</div>
<?php else: ?>
```

Replace with:

```php
<?php if ($uploads->count() === 0): ?>
  <?= $this->element('ui/empty_state', ['message' => 'No uploads match your filter.']) ?>
<?php else: ?>
```

- [ ] **Step 6: Update `templates/Admin/Audit/index.php` page_header**

Find:

```php
<h1><?= h($title) ?></h1>
<p>Append-only log of significant actions across the site. Filter by event type or by actor.</p>
```

Replace with:

```php
<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Append-only log of significant actions across the site. Filter by event type or by actor.',
]) ?>
```

- [ ] **Step 7: Update `templates/Admin/Dashboard/index.php` page_header**

Find:

```php
<h1><?= h($title) ?></h1>
<p>Site-wide overview. Tiles below show totals; jump to the relevant admin area for detail.</p>
```

Replace with:

```php
<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Site-wide overview. Tiles below show totals; jump to the relevant admin area for detail.',
]) ?>
```

- [ ] **Step 8: Update `templates/Admin/CallsignDirectory/index.php` page_header**

Find:

```php
<h1><?= h($title) ?></h1>
```

Replace with the existing first paragraph rolled in:

```php
<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => "Admin-curated callsign directory. The QSO auto-complete chain checks this table first before reaching out to external providers — fast resolution and no upstream network call.",
]) ?>
```

Then remove the now-redundant `<p class="text-muted">` block that previously held this introduction (search for "Admin-curated callsign directory" and delete that paragraph).

- [ ] **Step 9: Smoke test the admin routes**

```bash
for u in /admin /admin/settings /admin/cleanup /admin/users /admin/cards /admin/uploads /admin/audit /admin/callsign-directory /admin/templates/pending /admin/upgrade; do
  code=$(curl -s -b /tmp/eqsl-cookies.txt -o /dev/null -w "%{http_code}" "http://127.0.0.1:8080$u")
  err=$(curl -s -b /tmp/eqsl-cookies.txt "http://127.0.0.1:8080$u" | grep -cE 'Notice:|Warning:|Fatal|Undefined')
  echo "$code $u — $err errors"
done
```

Expected: every line is `200 /... — 0 errors`.

- [ ] **Step 10: Full test suite**

```bash
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: `OK (349 tests, …)`.

- [ ] **Step 11: Commit**

```bash
git add templates/Admin/
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
refactor(ui): apply element/ui/ components across admin templates

Same sweep as user-facing pages, applied to /admin/* — page_header,
empty_state, callsign, and badge_share_status elements replace the
inline duplication. Deleted-card badge stays inline on Admin/Cards
because it's admin-specific state.
EOF
)"
```

---

# PHASE 3 — Responsive + deeper a11y

Three commits: viewport-sweep fixes, focus trap, contrast audit.

---

## Task 3.1: Viewport sweep at 320 / 640 / 768 / 1024 / 1440 px

**Why:** Verify every route renders cleanly on mobile + tablet + desktop. Fix any breakage found.

**Files:** Touched as breakage is discovered. Common candidates:
- `templates/Templates/edit.php` (designer side panels)
- `templates/Qsos/index.php` (filter row)
- `templates/layout/default.php` (navbar overflow)

### Steps

- [ ] **Step 1: Set up a manual test checklist**

Open the application in Chrome / Firefox dev tools and toggle the device toolbar (Ctrl+Shift+M). Test the routes below at each viewport width.

Public routes:
- `/`, `/login`, `/register`, `/password/forgot`

Authenticated:
- `/dashboard`, `/qsos`, `/qsos/new`, `/qsos/import`, `/qsos/1`, `/qsos/1/render`
- `/templates`, `/templates/new`
- `/cards`, `/cards/1`
- `/uploads`, `/uploads/1/edit`
- `/profile`

Admin:
- `/admin`, `/admin/settings`, `/admin/cleanup`, `/admin/users`, `/admin/cards`, `/admin/uploads`, `/admin/audit`

At each viewport (320, 640, 768, 1024, 1440), note any:
- Horizontal page scroll (page wider than viewport)
- Overlapping elements
- Clipped text or buttons
- Form fields too narrow to type into

- [ ] **Step 2: Record the issues found**

Capture each into a temporary file `/tmp/responsive-issues.md`:

```md
# Responsive issues
## 320px
- /qsos/new : QSO type toggle wraps poorly — buttons too narrow
- /templates/edit : designer side panel squeezes; canvas barely visible

## 640px
- (none found yet)

…
```

The actual issues will depend on what's there now. Common predicted ones (likely already handled):

- Qsos/index filter row at 320–640px (already wraps via `col-sm-6`)
- Bulk-render modal margin (already `var(--s-3)` at <640)
- Tables wider than viewport (already get `display: block; overflow-x: auto` at <640)

If nothing breaks, document that and skip to Step 5.

- [ ] **Step 3: Fix each issue found**

For each entry in the issues file, find the offending template and adjust. Common fixes:

- A `col-md-3` that squeezes at 768px → change to `col-md-4 col-sm-6`
- A `<table>` overflowing → wrap in `<div style="overflow-x: auto;">`
- A `.btn-group` overflowing → add `flex-wrap: wrap` (already done in theme.css)
- Inline `style="width: 220px"` on an input → change to `min-width: 160px; max-width: 100%`

For each fix, edit the file directly. Re-verify the viewport.

- [ ] **Step 4: Re-verify all routes pass**

Repeat Step 1 after the fixes. Document final state in the commit message.

- [ ] **Step 5: Smoke test + run tests**

```bash
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: `OK (349 tests, …)`.

- [ ] **Step 6: Commit (if any files changed)**

```bash
git add <list of changed templates>
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
fix(responsive): viewport sweep at 320/640/768/1024/1440 px

Manual QA across all 23 routes. Issues found + fixed:

  - <list each issue + fix>

Verified by hand on Chrome + Firefox dev-tools device emulation.
No new tests — visual issues don't lend themselves to assertion.
EOF
)"
```

If no issues were found, skip the commit and note in the next task's commit that the sweep passed.

---

## Task 3.2: Focus trap on the bulk-render modal

**Why:** The bulk-render modal is the only multi-step interaction in the app. Without a focus trap, Tab leaves the modal and lands in the page behind it. ESC also doesn't close it currently.

**Files:**
- Create: `webroot/js/focus-trap.js`
- Modify: `templates/Qsos/index.php`
- Modify: `templates/layout/default.php`

### Steps

- [ ] **Step 1: Write `webroot/js/focus-trap.js`**

```javascript
/*
 * Tiny vanilla focus-trap. Activate by giving an element data-focus-trap
 * and showing/hiding it with a CSS class (e.g. via Alpine x-show).
 *
 * Usage in Alpine:
 *   <div x-show="modalOpen" x-init="$el._trap = focusTrap.attach($el)"
 *        x-effect="modalOpen ? $el._trap.activate() : $el._trap.deactivate()">
 *
 * The trap remembers the element that was focused when activated and
 * restores focus to it on deactivate. ESC inside the trap fires a
 * custom 'focustrap:escape' event the caller can listen for to close
 * the modal.
 */
(function () {
  var FOCUSABLE = [
    'a[href]', 'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])', 'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
  ].join(',');

  function getFocusable(root) {
    return Array.prototype.slice.call(root.querySelectorAll(FOCUSABLE))
      .filter(function (el) { return el.offsetParent !== null; });
  }

  function attach(root) {
    var savedFocus = null;
    var keydownHandler = null;

    return {
      activate: function () {
        savedFocus = document.activeElement;
        var focusables = getFocusable(root);
        if (focusables.length > 0) focusables[0].focus();

        keydownHandler = function (e) {
          if (e.key === 'Escape') {
            root.dispatchEvent(new CustomEvent('focustrap:escape'));
            return;
          }
          if (e.key !== 'Tab') return;

          var items = getFocusable(root);
          if (items.length === 0) return;
          var first = items[0];
          var last  = items[items.length - 1];

          if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
          } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
          }
        };
        document.addEventListener('keydown', keydownHandler);
      },
      deactivate: function () {
        if (keydownHandler) {
          document.removeEventListener('keydown', keydownHandler);
          keydownHandler = null;
        }
        if (savedFocus && typeof savedFocus.focus === 'function') {
          savedFocus.focus();
        }
      },
    };
  }

  window.focusTrap = { attach: attach };
})();
```

- [ ] **Step 2: Load focus-trap.js from the layout**

In `templates/layout/default.php`, find:

```php
<script src="<?= $this->Url->build('/js/app.js') ?>" defer></script>
```

Add the focus-trap script right before it:

```php
<script src="<?= $this->Url->build('/js/focus-trap.js') ?>" defer></script>
<script src="<?= $this->Url->build('/js/app.js') ?>" defer></script>
```

- [ ] **Step 3: Wire the trap into the bulk-render modal**

In `templates/Qsos/index.php`, find:

```php
  <!-- Bulk render modal: Alpine fully manages visibility (no Bootstrap .show class
       — that has display:block !important which would make the modal un-closable). -->
  <div x-show="modalOpen" x-cloak tabindex="-1"
       style="position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1050; overflow-y: auto;">
```

Replace with:

```php
  <!-- Bulk render modal: Alpine fully manages visibility (no Bootstrap .show class
       — that has display:block !important which would make the modal un-closable).
       focusTrap.attach() runs once at hydration; activate/deactivate fire on
       modalOpen flips so Tab can't escape and ESC closes. -->
  <div x-show="modalOpen" x-cloak tabindex="-1"
       x-init="$el._trap = window.focusTrap.attach($el);
               $el.addEventListener('focustrap:escape', () => closeModal())"
       x-effect="modalOpen ? $el._trap.activate() : $el._trap.deactivate()"
       style="position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1050; overflow-y: auto;">
```

- [ ] **Step 4: Manual keyboard test**

```bash
# Login, navigate to /qsos, open the bulk modal
# Tab from outside should not enter the modal until it's open
# Once open: Tab cycles within (template select → upload select → Cancel → Start render → back to template select)
# ESC closes the modal
# After close, focus returns to the "Bulk render selected" button that opened it
```

Document the test result in the commit message.

- [ ] **Step 5: Smoke test + run tests**

```bash
for u in /qsos; do
  code=$(curl -s -b /tmp/eqsl-cookies.txt -o /dev/null -w "%{http_code}" "http://127.0.0.1:8080$u")
  err=$(curl -s -b /tmp/eqsl-cookies.txt "http://127.0.0.1:8080$u" | grep -cE 'Notice:|Warning:|Fatal|Undefined')
  echo "$code $u — $err errors"
done
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: `200 /qsos — 0 errors`, `OK (349 tests, …)`.

- [ ] **Step 6: Commit**

```bash
git add webroot/js/focus-trap.js templates/layout/default.php templates/Qsos/index.php
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
a11y(modal): focus trap + ESC-to-close on bulk-render modal

  - webroot/js/focus-trap.js: small vanilla focus-trap that remembers
    the previously-focused element, cycles Tab within a given root,
    and fires a focustrap:escape event on ESC. Generic enough to
    wire into any future modal.
  - templates/Qsos/index.php: bulk-render modal calls
    window.focusTrap.attach($el) at hydration, then activates/
    deactivates on modalOpen flips. ESC closes the modal and
    restores focus to the trigger button that opened it.

Manual keyboard test: opened the modal with Enter, Tab cycled
through (template select → upload select → Cancel → Start render →
back to template select), Shift+Tab reversed, ESC closed, focus
returned to "Bulk render selected".
EOF
)"
```

---

## Task 3.3: Contrast audit

**Why:** Every token combination in `theme.css` should clear WCAG AA (4.5:1 normal text, 3:1 large/UI). Documents the audit + fixes any failures.

**Files:**
- Possibly: `webroot/css/theme.css` (adjust token values if any fail)
- Create: `docs/superpowers/audits/2026-05-12-contrast-audit.md`

### Steps

- [ ] **Step 1: Calculate ratios for the canonical token combos**

For each combo below, compute the WCAG contrast ratio. Use any tool — https://webaim.org/resources/contrastchecker/ or the formula at https://www.w3.org/TR/WCAG21/#contrast-minimum.

Combos to check:

| FG | BG | Required |
|---|---|---|
| `#09090b` (--fg) | `#fafafa` (--bg) | 4.5:1 |
| `#09090b` (--fg) | `#ffffff` (--surface) | 4.5:1 |
| `#52525b` (--fg-muted) | `#ffffff` (--surface) | 4.5:1 |
| `#71717a` (--fg-subtle) | `#ffffff` (--surface) | 4.5:1 |
| `#71717a` (--fg-subtle) | `#fafafa` (--bg) | 4.5:1 |
| `#52525b` (--fg-muted) | `#f4f4f5` (--surface-2) | 4.5:1 |
| `#047857` (--accent-strong) | `#ffffff` | 4.5:1 |
| `#059669` (--accent) | `#ffffff` | 4.5:1 |
| `#ffffff` | `#18181b` (--fg-strong, btn-primary) | 4.5:1 |
| `#dc2626` (--danger) | `#ffffff` | 4.5:1 |
| `#b91c1c` | `#fee2e2` (badge bg-danger) | 4.5:1 |
| `#075985` | `#e0f2fe` (badge bg-info) | 4.5:1 |
| `#92400e` | `#fef3c7` (badge bg-warning) | 4.5:1 |
| `#047857` | `#ecfdf5` (badge bg-success) | 4.5:1 |
| `#52525b` (--fg-muted) | `#f0f9ff` (alert-info bg) | 4.5:1 |

- [ ] **Step 2: Document results**

Create `docs/superpowers/audits/2026-05-12-contrast-audit.md`:

```markdown
# Contrast audit — 2026-05-12

Per the UI spec, every visible foreground/background combination
in `webroot/css/theme.css` must clear WCAG AA (4.5:1 for normal
text, 3:1 for large text / UI affordances).

## Results

| FG | BG | Ratio | Pass AA |
|---|---|---|---|
| #09090b | #fafafa | … | ✓/✗ |
| #09090b | #ffffff | … | ✓/✗ |
| (…fill in actual measured ratios…) |

## Failures + fixes

(If any combos fail, document the token change here.)

## Verified by

(your name, tool used)
```

Fill in actual ratios.

- [ ] **Step 3: Adjust failing tokens (if any)**

If e.g. `--fg-subtle` against `--bg` is 4.3:1 (just below AA), darken `--fg-subtle` slightly — try `#6b6b76` and recompute. Edit `webroot/css/theme.css`:

Find:

```css
  --fg-subtle:     #71717a;
```

Adjust if needed. Re-audit the changed combos.

- [ ] **Step 4: Smoke test (visual consistency)**

```bash
for u in /dashboard /qsos /qsos/new /admin/settings; do
  code=$(curl -s -b /tmp/eqsl-cookies.txt -o /dev/null -w "%{http_code}" "http://127.0.0.1:8080$u")
  echo "$code $u"
done
```

Expected: all `200`.

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/audits/ webroot/css/theme.css 2>/dev/null
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
a11y(contrast): WCAG AA audit of theme.css token combinations

Hand-measured every documented foreground / background pair in
theme.css against the WCAG AA 4.5:1 normal-text threshold.

Results: <pass / N tweaks needed>.

Full table at docs/superpowers/audits/2026-05-12-contrast-audit.md.
EOF
)"
```

---

# PHASE 4 — Dark mode

The real feature. Six tasks: tokens, DaisyUI registration, CSS override, pre-paint script, toggle UI, visual QA.

---

## Task 4.1: Dark-mode token override block in theme.css

**Why:** Every component already pulls from `var(--…)` tokens. Defining dark counterparts via a `[data-theme="eqsl-dark"]` block flips the whole UI in one place with no per-component edits.

**Files:**
- Modify: `webroot/css/theme.css`

### Steps

- [ ] **Step 1: Append the dark-mode override block to theme.css**

Open `webroot/css/theme.css` and append (after the existing `@media print { … }` rule near the end):

```css
/* =========================================================================
 *  Dark mode — token overrides for [data-theme="eqsl-dark"]
 *
 *  Every component reads from var(--token), so re-binding the tokens
 *  inside a [data-theme="eqsl-dark"] block flips the entire UI in one
 *  shot. No per-component rules needed.
 * ========================================================================= */
[data-theme="eqsl-dark"] {
  --bg:            #0a0a0a;       /* zinc-950 page bg */
  --surface:       #18181b;       /* zinc-900 cards/inputs */
  --surface-2:     #27272a;       /* zinc-800 secondary surface */
  --surface-3:     #3f3f46;       /* zinc-700 elevated hover */
  --border:        #27272a;       /* one step up from surface so the
                                     border still reads */
  --border-strong: #3f3f46;
  --fg:            #fafafa;
  --fg-strong:     #ffffff;       /* button primary becomes white-on-dark */
  --fg-muted:      #a1a1aa;       /* zinc-400 */
  --fg-subtle:     #71717a;       /* zinc-500 reads on both modes */
  --accent:        #10b981;       /* emerald-500 — punchier on dark */
  --accent-strong: #34d399;       /* emerald-400 for hover */
  --accent-soft:   #064e3b;       /* emerald-900 — soft fill inverted */
  --accent-ring:   rgba(16, 185, 129, 0.4);

  --info:          #38bdf8;       /* sky-400 */
  --info-soft:     #082f49;       /* sky-950 */
  --warning:       #fbbf24;       /* amber-400 */
  --warning-soft:  #422006;       /* amber-950 */
  --danger:        #f87171;       /* red-400 */
  --danger-soft:   #450a0a;       /* red-950 */
  --success:       #34d399;
  --success-soft:  #064e3b;

  /* Shadows: solid dark backgrounds eat soft shadows. Use heavier alpha. */
  --sh-1: 0 1px 2px rgba(0, 0, 0, 0.4);
  --sh-2: 0 1px 3px rgba(0, 0, 0, 0.5), 0 1px 2px rgba(0, 0, 0, 0.35);
  --sh-3: 0 4px 8px rgba(0, 0, 0, 0.55), 0 2px 4px rgba(0, 0, 0, 0.35);
  --sh-4: 0 12px 28px rgba(0, 0, 0, 0.6), 0 4px 10px rgba(0, 0, 0, 0.4);
}

/* Image previews can look washed-out on dark surfaces. Drop a subtle
   border so they still feel "framed". */
[data-theme="eqsl-dark"] .card-preview,
[data-theme="eqsl-dark"] .img-fluid {
  border: 1px solid var(--border-strong);
}

/* The navbar hamburger SVG is currently hard-coded dark zinc. On dark
   mode it disappears; swap to a light stroke. */
[data-theme="eqsl-dark"] .navbar-toggler-icon {
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'><path stroke='%23fafafa' stroke-width='2' stroke-linecap='round' d='M4 8h22M4 15h22M4 22h22'/></svg>");
}
```

- [ ] **Step 2: Manually preview by setting the theme attribute**

```bash
# In browser dev tools console:
document.documentElement.setAttribute('data-theme', 'eqsl-dark');
# Page should flip to dark mode. Scroll through and check the obvious bits.
# Re-enable: document.documentElement.setAttribute('data-theme', 'eqsl');
```

- [ ] **Step 3: Smoke test (just routes, no visual assertion yet)**

```bash
for u in / /dashboard /qsos /qsos/new; do
  echo "$(curl -s -b /tmp/eqsl-cookies.txt -o /dev/null -w "%{http_code}" http://127.0.0.1:8080$u) $u"
done
```

Expected: all `200`.

- [ ] **Step 4: Commit**

```bash
git add webroot/css/theme.css
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
feat(dark-mode): token override block for [data-theme=eqsl-dark]

Every component reads from var(--token), so re-binding the tokens
inside a [data-theme="eqsl-dark"] block flips the entire UI in one
shot. No per-component rules needed.

Palette: zinc-950 page, zinc-900 surfaces, zinc-800 elevated, zinc-700
hover, emerald-500 accent (punchier than the light-mode emerald-600 so
it stands out on dark). Shadow alpha raised to 0.4-0.6 because solid
dark backgrounds eat soft light-mode shadows.

Also patches:
  - .card-preview / .img-fluid get a 1px border-strong outline on dark
    so images don't bleed into the page.
  - .navbar-toggler-icon SVG fill swaps from zinc-900 to zinc-50 so
    the hamburger is visible on dark.

Setting data-theme=eqsl-dark on <html> from dev tools flips
everything. No toggle UI yet (next task).
EOF
)"
```

---

## Task 4.2: DaisyUI dual-theme registration + pre-paint script

**Why:** DaisyUI's primary/accent/base-* tokens drive its component defaults. We register a parallel `eqsl-dark` theme so DaisyUI's internals also flip. And we add the inline pre-paint script so the right theme is set BEFORE any CSS paints (no FOUC).

**Files:**
- Modify: `templates/layout/default.php`

### Steps

- [ ] **Step 1: Expand the DaisyUI config in `templates/layout/default.php`**

Find:

```php
    daisyui: {
      themes: [{
        eqsl: {
          'color-scheme':         'light',
          'primary':              '#18181b',
          'primary-content':      '#ffffff',
          'secondary':            '#e4e4e7',
          'secondary-content':    '#18181b',
          'accent':               '#059669',
          'accent-content':       '#ffffff',
          'neutral':              '#52525b',
          'neutral-content':      '#fafafa',
          'base-100':             '#ffffff',
          'base-200':             '#f4f4f5',
          'base-300':             '#e4e4e7',
          'base-content':         '#09090b',
          'info':                 '#0284c7',
          'success':              '#059669',
          'warning':              '#d97706',
          'error':                '#dc2626',
          '--rounded-box':        '0.75rem',
          '--rounded-btn':        '0.5rem',
          '--rounded-badge':      '999px',
          '--border-btn':         '1px',
        },
      }],
    },
```

Replace with:

```php
    daisyui: {
      themes: [
        {
          eqsl: {
            'color-scheme':         'light',
            'primary':              '#18181b',
            'primary-content':      '#ffffff',
            'secondary':            '#e4e4e7',
            'secondary-content':    '#18181b',
            'accent':               '#059669',
            'accent-content':       '#ffffff',
            'neutral':              '#52525b',
            'neutral-content':      '#fafafa',
            'base-100':             '#ffffff',
            'base-200':             '#f4f4f5',
            'base-300':             '#e4e4e7',
            'base-content':         '#09090b',
            'info':                 '#0284c7',
            'success':              '#059669',
            'warning':              '#d97706',
            'error':                '#dc2626',
            '--rounded-box':        '0.75rem',
            '--rounded-btn':        '0.5rem',
            '--rounded-badge':      '999px',
            '--border-btn':         '1px',
          },
        },
        {
          'eqsl-dark': {
            'color-scheme':         'dark',
            'primary':              '#fafafa',
            'primary-content':      '#18181b',
            'secondary':            '#3f3f46',
            'secondary-content':    '#fafafa',
            'accent':               '#10b981',
            'accent-content':       '#ffffff',
            'neutral':              '#a1a1aa',
            'neutral-content':      '#18181b',
            'base-100':             '#18181b',
            'base-200':             '#27272a',
            'base-300':             '#3f3f46',
            'base-content':         '#fafafa',
            'info':                 '#38bdf8',
            'success':              '#34d399',
            'warning':              '#fbbf24',
            'error':                '#f87171',
            '--rounded-box':        '0.75rem',
            '--rounded-btn':        '0.5rem',
            '--rounded-badge':      '999px',
            '--border-btn':         '1px',
          },
        },
      ],
    },
```

- [ ] **Step 2: Add the pre-paint script in the `<head>`**

In `templates/layout/default.php`, find:

```php
<meta name="csrf-token" content="<?= $this->getRequest()->getAttribute('csrfToken') ?>">
<title><?= $this->fetch('title') ?: 'eQSL Card · Receiving Station' ?></title>
```

Insert a pre-paint script right after the `<title>`:

```php
<meta name="csrf-token" content="<?= $this->getRequest()->getAttribute('csrfToken') ?>">
<title><?= $this->fetch('title') ?: 'eQSL Card · Receiving Station' ?></title>

<!--
  Pre-paint theme resolver. Reads the user's saved preference from
  localStorage (or falls back to the OS prefers-color-scheme media
  query), then sets <html data-theme> before any stylesheet loads.
  Without this, the page paints in light mode for one frame then
  flips to dark — a classic FOUC.
-->
<script>
  (function () {
    var pref = localStorage.getItem('eqsl-theme') || 'system';
    var resolved;
    if (pref === 'dark')        resolved = 'eqsl-dark';
    else if (pref === 'light')  resolved = 'eqsl';
    else /* system */           resolved =
      window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'eqsl-dark' : 'eqsl';
    document.documentElement.setAttribute('data-theme', resolved);
    document.documentElement.setAttribute('data-theme-pref', pref);
  })();
</script>
```

- [ ] **Step 3: Remove the hard-coded `data-theme="eqsl"` on `<body>`**

The pre-paint script now sets `data-theme` on `<html>`. The `<body data-theme="eqsl">` is redundant + would override the dark-mode setting on the html element for DaisyUI components rooted at body.

Find:

```php
<body data-theme="eqsl">
```

Replace with:

```php
<body>
```

- [ ] **Step 4: Smoke test in browser**

```
1. Open dev tools console
2. localStorage.setItem('eqsl-theme', 'dark'); location.reload();
   — page should load directly in dark mode, no flicker
3. localStorage.setItem('eqsl-theme', 'light'); location.reload();
   — page should load in light mode
4. localStorage.removeItem('eqsl-theme'); location.reload();
   — page should follow OS preference (set OS to dark to verify)
```

- [ ] **Step 5: HTTP smoke test**

```bash
for u in / /dashboard; do
  code=$(curl -s -b /tmp/eqsl-cookies.txt -o /dev/null -w "%{http_code}" "http://127.0.0.1:8080$u")
  err=$(curl -s -b /tmp/eqsl-cookies.txt "http://127.0.0.1:8080$u" | grep -cE 'Notice:|Warning:|Fatal|Undefined')
  echo "$code $u — $err errors"
done
```

Expected: all `200 - 0 errors`.

- [ ] **Step 6: Commit**

```bash
git add templates/layout/default.php
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
feat(dark-mode): daisyui dual-theme + pre-paint resolver

  - daisyui config now registers two themes: eqsl (existing light)
    and eqsl-dark. DaisyUI's primary/accent/base-* tokens drive
    DaisyUI's component defaults; the parallel theme makes its
    .btn/.alert/.card flip in step with our own.
  - Inline <script> in <head> reads localStorage 'eqsl-theme' (or
    falls back to prefers-color-scheme) and sets <html data-theme>
    before any CSS paints. No FOUC.
  - Removed redundant data-theme="eqsl" on <body> — the pre-paint
    script's <html> attribute is what DaisyUI actually keys on.

Toggle UI lands in the next commit.
EOF
)"
```

---

## Task 4.3: Toggle button + JS handler

**Why:** Users need a way to flip the theme without opening dev tools. Three-state cycle (light → dark → system) preserves the "follow OS" option.

**Files:**
- Modify: `templates/layout/default.php` (add the button)
- Modify: `webroot/js/app.js` (click handler)

### Steps

- [ ] **Step 1: Add the toggle button to the navbar**

In `templates/layout/default.php`, find:

```php
        <li class="nav-item">
          <?= $this->Form->postLink('Sign out', '/logout', ['class' => 'nav-link']) ?>
        </li>
```

Insert a theme toggle right before it:

```php
        <li class="nav-item">
          <button type="button" id="themeToggle" class="nav-link"
                  aria-label="Toggle colour scheme"
                  title="Toggle colour scheme"
                  style="background: transparent; border: 0; cursor: pointer;">
            <span aria-hidden="true">
              <!-- Sun (visible in dark mode) -->
              <svg class="theme-icon theme-icon--sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none; vertical-align: -3px;">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
              </svg>
              <!-- Moon (visible in light mode) -->
              <svg class="theme-icon theme-icon--moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px;">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
              </svg>
            </span>
          </button>
        </li>
        <li class="nav-item">
          <?= $this->Form->postLink('Sign out', '/logout', ['class' => 'nav-link']) ?>
        </li>
```

- [ ] **Step 2: Add the toggle handler to `webroot/js/app.js`**

Open `webroot/js/app.js` and find the IIFE that handles `data-toggle` clicks. Append a new IIFE after it (before the `cameraForm()` function):

```javascript
/*
 * Three-state theme toggle: light → dark → system → light.
 *   - localStorage key 'eqsl-theme' stores the user's chosen state.
 *   - The pre-paint <script> in the layout's <head> reads this and
 *     applies the right data-theme on <html> before any CSS loads.
 *   - This handler runs *after* the navbar is in the DOM. It updates
 *     localStorage + re-runs the same resolver to flip data-theme.
 *   - Sun icon shows in dark mode; moon shows in light mode. The
 *     "system" state defaults to whichever the OS prefers and shows
 *     the matching icon.
 */
(function () {
  function resolve(pref) {
    if (pref === 'dark') return 'eqsl-dark';
    if (pref === 'light') return 'eqsl';
    return window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'eqsl-dark' : 'eqsl';
  }

  function applyIcons() {
    var theme = document.documentElement.getAttribute('data-theme');
    var sun  = document.querySelector('.theme-icon--sun');
    var moon = document.querySelector('.theme-icon--moon');
    if (!sun || !moon) return;
    if (theme === 'eqsl-dark') { sun.style.display = ''; moon.style.display = 'none'; }
    else                       { sun.style.display = 'none'; moon.style.display = ''; }
  }

  function setTitle(pref) {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    btn.setAttribute('title', 'Theme: ' + pref + ' (click to cycle)');
  }

  function cycle() {
    var current = localStorage.getItem('eqsl-theme') || 'system';
    var next = current === 'light' ? 'dark'
             : current === 'dark'  ? 'system'
             :                       'light';
    localStorage.setItem('eqsl-theme', next);
    var resolved = resolve(next);
    document.documentElement.setAttribute('data-theme', resolved);
    document.documentElement.setAttribute('data-theme-pref', next);
    applyIcons();
    setTitle(next);
  }

  document.addEventListener('DOMContentLoaded', function () {
    applyIcons();
    setTitle(localStorage.getItem('eqsl-theme') || 'system');
    var btn = document.getElementById('themeToggle');
    if (btn) btn.addEventListener('click', cycle);
  });

  /* If the OS preference changes while a "system" user has the page open,
     update without requiring a reload. */
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
    if ((localStorage.getItem('eqsl-theme') || 'system') === 'system') {
      document.documentElement.setAttribute('data-theme', resolve('system'));
      applyIcons();
    }
  });
})();
```

- [ ] **Step 3: Manual click test in browser**

```
1. Load /dashboard, observe the moon icon at the right of the navbar
2. Click it → page should flip to dark, sun icon now visible, button
   title says "Theme: dark (click to cycle)"
3. Click again → page returns to light if OS is light (or stays dark
   if OS is dark), button title says "Theme: system"
4. Click again → explicit light, title "Theme: light"
5. Reload → state persists across reloads (no FOUC)
```

- [ ] **Step 4: HTTP smoke test**

```bash
for u in / /dashboard /qsos; do
  code=$(curl -s -b /tmp/eqsl-cookies.txt -o /dev/null -w "%{http_code}" "http://127.0.0.1:8080$u")
  err=$(curl -s -b /tmp/eqsl-cookies.txt "http://127.0.0.1:8080$u" | grep -cE 'Notice:|Warning:|Fatal|Undefined')
  echo "$code $u — $err errors"
done

# Make sure the toggle button is in the served HTML:
curl -s -b /tmp/eqsl-cookies.txt http://127.0.0.1:8080/dashboard | grep -c "themeToggle"
```

Expected: `200 - 0 errors`. The grep count should be `1`.

- [ ] **Step 5: Commit**

```bash
git add templates/layout/default.php webroot/js/app.js
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
feat(dark-mode): three-state toggle button in the navbar

  - Sun/moon SVG icon button at the right of the navbar (just before
    sign-out). aria-label="Toggle colour scheme".
  - Click cycles light → dark → system → light. Tooltip + title
    attribute show the current state.
  - localStorage key 'eqsl-theme' persists the choice. Default for
    new users is 'system' (follows prefers-color-scheme).
  - prefers-color-scheme changes propagate live for 'system' users
    without requiring a page reload.

End-to-end: open dashboard in incognito → moon icon → click →
flips dark, sun icon → reload → stays dark, no FOUC.
EOF
)"
```

---

## Task 4.4: Visual QA pass + per-component dark-mode fixes

**Why:** First-pass dark mode always has surprises. Sweep every route in both modes and patch anything that looks off.

**Files:** Touched as issues are discovered. Common candidates:
- `webroot/css/theme.css` (per-component dark fixes)

### Steps

- [ ] **Step 1: Set the page to dark mode**

```bash
# In dev tools console
localStorage.setItem('eqsl-theme', 'dark'); location.reload();
```

- [ ] **Step 2: Sweep all routes in dark mode**

For each route below, verify:
- Page is legible (text contrast OK)
- Cards have visible borders
- Form inputs have a visible border + readable text
- Badges & alerts convey their severity (color + icon)
- Card preview shadow stack still has depth
- Code/kbd blocks readable
- Pagination buttons visible
- Modal backdrop is dark-friendly

Routes (23 total):
- `/`, `/login`, `/register`, `/password/forgot`
- `/dashboard`, `/qsos`, `/qsos/new`, `/qsos/import`, `/qsos/1`, `/qsos/1/render`
- `/templates`, `/templates/new`, `/cards`, `/cards/1`, `/uploads`, `/uploads/1/edit`
- `/profile`
- `/admin`, `/admin/settings`, `/admin/cleanup`, `/admin/users`, `/admin/cards`, `/admin/audit`

- [ ] **Step 3: Record issues found**

Capture into `/tmp/darkmode-issues.md`:

```md
# Dark mode issues
- /dashboard : display-6 number on stat tiles fades into the bg
- /templates/edit : canvas border is invisible
- /qsos/new : Net details form-fieldset is too dark to differentiate
- … (etc)
```

- [ ] **Step 4: Fix each issue**

For each issue, add a targeted override at the bottom of theme.css inside an existing `[data-theme="eqsl-dark"]` block. Examples (only apply if observed):

```css
/* Display-6 stat numbers — drop one level of weight on dark so they
   don't bleed white into the surface. */
[data-theme="eqsl-dark"] .display-6 {
  color: var(--fg-strong);
  text-shadow: 0 1px 0 rgba(0, 0, 0, 0.4);
}

/* Designer canvas — give it a visible border on dark. */
[data-theme="eqsl-dark"] [x-ref="canvasWrap"] {
  border-color: var(--border-strong) !important;
  background: var(--surface-2) !important;
}

/* form-fieldset on dark: slightly elevated than the surrounding card. */
[data-theme="eqsl-dark"] .form-fieldset {
  background: var(--surface-2);
}
```

- [ ] **Step 5: Re-sweep both modes**

After each fix, toggle back to light mode and verify nothing regressed there.

- [ ] **Step 6: Final smoke + tests**

```bash
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: `OK (349 tests, …)`.

- [ ] **Step 7: Commit**

```bash
git add webroot/css/theme.css
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
fix(dark-mode): per-component touch-ups from visual QA

Walked all 23 routes in dark mode. Issues + fixes:

  - <list each fix>

Re-verified light mode after each change — no regressions.
EOF
)"
```

(If no fixes were needed, skip this commit and document in the next task's message.)

---

# PHASE 5 — Production CSS build

Replace the Tailwind Play CDN with a pre-compiled bundle. Builds locally on the developer's laptop; ships `dist.css` to git.

---

## Task 5.1: Add Tailwind toolchain to the project

**Why:** The Play CDN runs the Tailwind compiler in the browser, which adds ~80KB JS and a brief FOUC. The Tailwind project explicitly says Play CDN is not for production.

**Files:**
- Create: `tailwind.config.js`
- Create: `src/css/tailwind-source.css`
- Modify: `package.json` (add daisyui dep + build script)

### Steps

- [ ] **Step 1: Add daisyui to devDependencies and a build:css script**

Replace the contents of `package.json` with:

```json
{
  "name": "eqsl-card-designer-tests",
  "private": true,
  "type": "commonjs",
  "scripts": {
    "test": "vitest run",
    "test:watch": "vitest",
    "build:css": "tailwindcss -i src/css/tailwind-source.css -o webroot/css/dist.css --minify",
    "watch:css": "tailwindcss -i src/css/tailwind-source.css -o webroot/css/dist.css --watch"
  },
  "devDependencies": {
    "daisyui": "^4.12.14",
    "tailwindcss": "^3.4.3",
    "vitest": "^2.1.0"
  }
}
```

- [ ] **Step 2: Install the new deps**

```bash
npm install
```

Expected: installs `tailwindcss` and `daisyui` into `node_modules/`. No errors.

- [ ] **Step 3: Create `tailwind.config.js`**

```javascript
/**
 * Tailwind + DaisyUI config.
 *
 * Build command:
 *   npm run build:css
 *
 * Produces webroot/css/dist.css, which is committed to git so the
 * shared host (no Node) can serve it directly via FTP.
 *
 * Content paths tell PurgeCSS which class names are actually used.
 * Anything not appearing in these files is stripped from dist.css.
 * The safelist below covers Alpine-driven dynamic classes that never
 * appear in static markup.
 */
module.exports = {
  content: [
    './templates/**/*.php',
    './webroot/js/**/*.js',
    './webroot/css/theme.css',
  ],
  safelist: [
    'show',
    'is-active',
    'btn-active',
    'hidden',
    {
      pattern: /^(alert|btn|badge|card|nav-link)-(primary|secondary|success|info|warning|danger|light|dark)$/,
    },
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
      {
        eqsl: {
          'color-scheme':         'light',
          'primary':              '#18181b',
          'primary-content':      '#ffffff',
          'secondary':            '#e4e4e7',
          'secondary-content':    '#18181b',
          'accent':               '#059669',
          'accent-content':       '#ffffff',
          'neutral':              '#52525b',
          'neutral-content':      '#fafafa',
          'base-100':             '#ffffff',
          'base-200':             '#f4f4f5',
          'base-300':             '#e4e4e7',
          'base-content':         '#09090b',
          'info':                 '#0284c7',
          'success':              '#059669',
          'warning':              '#d97706',
          'error':                '#dc2626',
          '--rounded-box':        '0.75rem',
          '--rounded-btn':        '0.5rem',
          '--rounded-badge':      '999px',
          '--border-btn':         '1px',
        },
      },
      {
        'eqsl-dark': {
          'color-scheme':         'dark',
          'primary':              '#fafafa',
          'primary-content':      '#18181b',
          'secondary':            '#3f3f46',
          'secondary-content':    '#fafafa',
          'accent':               '#10b981',
          'accent-content':       '#ffffff',
          'neutral':              '#a1a1aa',
          'neutral-content':      '#18181b',
          'base-100':             '#18181b',
          'base-200':             '#27272a',
          'base-300':             '#3f3f46',
          'base-content':         '#fafafa',
          'info':                 '#38bdf8',
          'success':              '#34d399',
          'warning':              '#fbbf24',
          'error':                '#f87171',
          '--rounded-box':        '0.75rem',
          '--rounded-btn':        '0.5rem',
          '--rounded-badge':      '999px',
          '--border-btn':         '1px',
        },
      },
    ],
  },
};
```

- [ ] **Step 4: Create `src/css/tailwind-source.css`**

Create the directory + file:

```bash
mkdir -p src/css
```

Then write `src/css/tailwind-source.css`:

```css
/*
 * Tailwind CLI entry point.
 *
 * Order matters:
 *   1. Tailwind base / components / utilities (set up resets + utility
 *      classes).
 *   2. DaisyUI is injected by the plugin (registered in tailwind.config.js).
 *   3. theme.css (our brand layer + Bootstrap-compat shim) is imported
 *      LAST so its overrides win the cascade.
 */
@tailwind base;
@tailwind components;
@tailwind utilities;

@import '../../webroot/css/theme.css';
```

- [ ] **Step 5: Run the build**

```bash
npm run build:css
```

Expected output:

```
Done in NNNms.
```

Verify the output file exists:

```bash
ls -lh webroot/css/dist.css
```

Expected: file exists, > 10KB and < 100KB minified.

- [ ] **Step 6: No template change yet — verify the build artefact**

```bash
head -c 200 webroot/css/dist.css
```

Expected: starts with a minified CSS block. No syntax errors.

- [ ] **Step 7: Commit (config + source + first dist build)**

```bash
git add package.json package-lock.json tailwind.config.js src/css/tailwind-source.css webroot/css/dist.css
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
build: add Tailwind CLI + DaisyUI toolchain

  - package.json:    daisyui + tailwindcss in devDependencies; new
                     build:css + watch:css scripts.
  - tailwind.config.js: content paths cover templates/**/*.php +
                     webroot/js/**/*.js + webroot/css/theme.css.
                     Safelist for Alpine-driven dynamic classes
                     (show, is-active, btn-active, hidden + the
                     {alert,btn,badge,…}-{primary,secondary,…}
                     pattern). DaisyUI plugin loaded; both eqsl
                     and eqsl-dark themes registered with the same
                     palettes as the inline Play CDN config.
  - src/css/tailwind-source.css: CLI entry. @tailwind directives
                     then @import of theme.css so our brand layer
                     wins the cascade.
  - webroot/css/dist.css: first compiled bundle (committed so the
                     shared host can serve it via FTP — no Node on
                     the server).

Layout still loads the Play CDN. The cut-over lands in the next
commit so this one can be reverted cleanly if the build looks wrong.
EOF
)"
```

---

## Task 5.2: Cut over from Play CDN to dist.css

**Why:** Now that the build artefact is verified, replace the runtime Tailwind compile with the pre-built bundle.

**Files:**
- Modify: `templates/layout/default.php`
- Modify: `src/Middleware/SecurityHeadersMiddleware.php`
- Modify: `README.md`
- Modify: `.gitignore` (ensure node_modules is ignored)

### Steps

- [ ] **Step 1: Verify .gitignore covers node_modules**

```bash
grep -E "^node_modules" .gitignore
```

Expected: matches. If not, append:

```bash
echo "node_modules/" >> .gitignore
```

- [ ] **Step 2: Replace the CDN includes in `templates/layout/default.php`**

Find:

```php
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css">
<script src="https://cdn.tailwindcss.com/3.4.3"></script>
<script>
  /*
   * Tailwind + DaisyUI inline config. With the Play CDN we can override
   * tokens here without a tailwind.config.js. The custom theme matches
   * the emerald + zinc palette we use elsewhere.
   */
  tailwind.config = {
    /* …big inline block… */
  };
</script>

<link rel="stylesheet" href="<?= $this->Url->build('/css/theme.css') ?>">
<link rel="stylesheet" href="<?= $this->Url->build('/css/app.css') ?>">
```

Replace with:

```php
<!--
  Single compiled CSS bundle: Tailwind base/components/utilities +
  DaisyUI component classes + our theme.css brand layer, all in one
  file. Build it with `npm run build:css` and commit the output.
-->
<link rel="stylesheet"
      href="<?= $this->Url->build('/css/dist.css') ?>?v=<?= @filemtime(WWW_ROOT . 'css/dist.css') ?>">
<link rel="stylesheet" href="<?= $this->Url->build('/css/app.css') ?>">
```

- [ ] **Step 3: Drop cdn.tailwindcss.com from CSP**

In `src/Middleware/SecurityHeadersMiddleware.php`, find:

```php
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tailwindcss.com",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.tailwindcss.com",
```

Replace with:

```php
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net",
```

Also remove the obsolete comment about Tailwind Play CDN. Find:

```php
        // Tailwind Play CDN (cdn.tailwindcss.com) runs the Tailwind JIT
        // compiler in the browser — it loads as a script AND injects
        // generated CSS into a <style> tag on the fly. Both its
        // script-src and the inline style-src allowance are needed until
        // we move to a pre-compiled production CSS build (Node on dev
        // machine only; ship the dist .css alongside the app).
```

Replace with:

```php
        // 'unsafe-inline' on style-src is still required for the
        // Tailwind-generated @media rules and a handful of inline
        // style attrs in templates. 'unsafe-inline' + 'unsafe-eval'
        // on script-src are required by CakePHP Form->postLink (inline
        // onclick) and Alpine (new Function evaluation) respectively.
        // cdn.jsdelivr.net stays for Inter + Geist Mono web fonts and
        // Alpine itself.
```

- [ ] **Step 4: Document the build step in README.md**

Read `README.md` first to see its structure:

```bash
head -40 README.md
```

Find a sensible spot (likely a "Development" or "Setup" section). Append a new subsection (use Edit on the closest existing heading; if none, append at the end):

```markdown
## Building the CSS bundle

The UI ships as a pre-compiled CSS bundle at `webroot/css/dist.css`.
It's regenerated on the developer's machine and committed to git so
the shared host can serve it directly via FTP (no Node on the server).

Install the toolchain once:

    npm install

Rebuild after any change to `templates/**/*.php`, `webroot/js/**/*.js`,
or `webroot/css/theme.css`:

    npm run build:css

Or run in watch mode while iterating:

    npm run watch:css

The minified output is around 30 KB. PurgeCSS strips any utility class
not used by the template / JS content paths. If you introduce a class
name that's only inserted at runtime by Alpine (e.g. `class="something-{{ kind }}"`),
add it to the `safelist` in `tailwind.config.js`.
```

- [ ] **Step 5: Final build to make sure everything still compiles**

```bash
npm run build:css
ls -lh webroot/css/dist.css
```

Expected: build succeeds, file exists.

- [ ] **Step 6: Full smoke test in browser**

Open the app in a clean profile (private window) and verify:
- Every page loads with styling identical to before
- Dark mode toggle still works
- No CSP errors in the browser console
- DevTools Network tab shows NO `cdn.tailwindcss.com` requests
- DevTools Network tab DOES show `/css/dist.css`

- [ ] **Step 7: HTTP smoke test**

```bash
rm -f /tmp/eqsl-cookies.txt
LOGIN_HTML=$(curl -s -c /tmp/eqsl-cookies.txt http://127.0.0.1:8080/login)
TOKEN=$(echo "$LOGIN_HTML" | grep -oP 'name="_csrfToken"[^>]*value="\K[^"]+' | head -1)
curl -s -b /tmp/eqsl-cookies.txt -c /tmp/eqsl-cookies.txt -L -X POST http://127.0.0.1:8080/login \
  --data-urlencode "_csrfToken=$TOKEN" --data-urlencode "email=contact@robbi.my" --data-urlencode "password=Password2345" -o /dev/null
for u in / /dashboard /qsos/new /admin/settings; do
  code=$(curl -s -b /tmp/eqsl-cookies.txt -o /dev/null -w "%{http_code}" "http://127.0.0.1:8080$u")
  echo "$code $u"
done
# Verify no Play CDN reference in served HTML:
curl -s -b /tmp/eqsl-cookies.txt http://127.0.0.1:8080/dashboard | grep -c "cdn.tailwindcss.com"
# Verify the new dist.css link IS present:
curl -s -b /tmp/eqsl-cookies.txt http://127.0.0.1:8080/dashboard | grep -c "/css/dist.css"
# Verify CSP no longer mentions Tailwind CDN:
curl -sI http://127.0.0.1:8080/dashboard | grep "Content-Security-Policy" | grep -c "tailwindcss"
```

Expected: every route is `200`. Play CDN count = `0`. dist.css count = `1`. CSP tailwind count = `0`.

- [ ] **Step 8: Full test suite**

```bash
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
```

Expected: `OK (349 tests, …)`.

- [ ] **Step 9: Commit**

```bash
git add templates/layout/default.php src/Middleware/SecurityHeadersMiddleware.php README.md .gitignore
git -c user.name="Robbi Nespu" -c user.email="robbinespu@gmail.com" commit --no-gpg-sign -m "$(cat <<'EOF'
build: cut over from Tailwind Play CDN to compiled dist.css

  - templates/layout/default.php: removed the Play CDN <script>
    + the inline tailwind.config block + the daisyui CDN <link>.
    Replaced with a single <link href="/css/dist.css">.
  - src/Middleware/SecurityHeadersMiddleware.php: dropped
    cdn.tailwindcss.com from script-src and style-src in the CSP.
  - README.md: documents `npm run build:css` and the watch variant.
  - .gitignore: ensures node_modules stays out of git (verified).

Page loads now serve a single ~30KB minified CSS file instead of
~310KB of CDN payload (DaisyUI full.min.css + Tailwind compiler).
No more browser-side JIT compile, no more "Don't use Play CDN in
production" warning in the console. Same visual output.
EOF
)"
```

---

## Self-review against the spec

After completing all phases, run:

```bash
git log --oneline f35efa6..HEAD
```

Expected: 10–13 commits, each scoped to one phase / sub-phase.

For each spec section, confirm there's a commit that addresses it:

| Spec requirement | Implementing commit |
|---|---|
| Phase 1.1 lazy loading | Task 1.1 commit |
| Phase 1.2 form polish | Task 1.2 commit |
| Phase 1.3 a11y basics | Task 1.3 commit |
| Phase 2 element extraction | Task 2.1 + 2.2 + 2.3 commits |
| Phase 3 viewport sweep | Task 3.1 commit (if issues) |
| Phase 3 focus trap | Task 3.2 commit |
| Phase 3 contrast audit | Task 3.3 commit |
| Phase 4 dark tokens | Task 4.1 commit |
| Phase 4 dual-theme + pre-paint | Task 4.2 commit |
| Phase 4 toggle UI | Task 4.3 commit |
| Phase 4 visual QA fixes | Task 4.4 commit (if issues) |
| Phase 5 toolchain | Task 5.1 commit |
| Phase 5 CDN cut-over | Task 5.2 commit |

If any spec requirement is missing a commit, return to that task and complete it.

Final acceptance:

```bash
docker compose exec -T php vendor/bin/phpunit --no-coverage 2>&1 | tail -3
# Expected: OK (349 tests, …)

# Visual: walk every route in both themes, check the dev-tools console
# has zero errors and no "Don't use Play CDN" warnings.
```

---

## Notes for the implementer

- Commit author MUST stay `Robbi Nespu <robbinespu@gmail.com>` with no Co-Authored-By trailer. The pattern in every commit block enforces this via `-c user.name=... -c user.email=...`.
- Run PHPUnit after every phase. If a test breaks unexpectedly, stop and investigate before continuing.
- The smoke-test snippet at the top of each task uses the seeded admin (`contact@robbi.my` / `Password2345`, set in the prior dev session). If the password no longer matches, reset via the SQL pattern documented in CLAUDE.md or the prior session log.
- Visual / responsive issues are inherently subjective. Document each finding in the commit message so a reviewer can verify your judgement.
- Don't try to TDD pure-visual changes (lazy loading, dark-mode color tweaks). The "test" is opening the page in a browser. Save TDD for the new code units (elements, focus-trap).
