<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<!--
  interactive-widget=resizes-content tells Chrome/Edge on Android to
  shrink the layout viewport (not just the visual viewport) when the
  virtual keyboard opens, so position: sticky elements naturally sit
  above the keyboard. iOS Safari ignores it but the Visual Viewport
  API listener in app.js (initKeyboardAware) handles iOS by writing
  a --keyboard-inset CSS variable that the sticky elements use as a
  bottom offset. M5 T11 — quick-add submit-button sticky behaviour.
-->
<meta name="viewport" content="width=device-width,initial-scale=1,interactive-widget=resizes-content">
<meta name="csrf-token" content="<?= $this->getRequest()->getAttribute('csrfToken') ?>">

<?php /* M5 T18 — PWA manifest + iOS standalone-app hints.
       Android Chrome / Edge / Firefox read manifest.webmanifest and
       offer "Install" / "Add to Home Screen" when the app passes the
       installability criteria (HTTPS, manifest, service worker, icons).
       iOS Safari ignores the manifest's icons + start_url and uses
       these <meta> + <link> tags instead. apple-mobile-web-app-capable
       hides the Safari chrome when launched from the home screen. */ ?>
<link rel="manifest" href="<?= $this->Url->build('/manifest.webmanifest') ?>">
<meta name="theme-color" content="#059669">
<link rel="apple-touch-icon" href="<?= $this->Url->build('/img/apple-touch-icon.png') ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="eQSL">

<title><?= $this->fetch('title') ?: 'eQSL Card · Receiving Station' ?></title>

<!--
  Pre-paint theme resolver. Reads the user's saved preference from
  localStorage (or falls back to the OS prefers-color-scheme media
  query), then sets <html data-theme> before any stylesheet loads.
  Without this, the page paints in light mode for one frame then
  flips to dark — a classic FOUC.
-->
<?php /* M5 T18/T19 — expose the deploy's base path to client-side JS
       (theme resolver uses it for nothing yet, but the SW registration
       and any future Alpine component that builds URLs needs it).
       Empty string on root deploys ('/' → ''); '/qsl' on subfolder.
       The middleware-injected webroot attribute is the source of truth. */ ?>
<?php /* M5 T27 — peek at the identity here so the pref can be emitted
       in the same head-block as EQSL_BASE. The full $identity / $userData
       / $isAdmin trio is recomputed for the body sections further down. */ ?>
<?php
$_headIdent = $this->getRequest()->getAttribute('identity');
$_headUser = $_headIdent && method_exists($_headIdent, 'getOriginalData')
    ? $_headIdent->getOriginalData() : null;
?>
<script>
  window.EQSL_BASE = <?= json_encode(rtrim((string)$this->getRequest()->getAttribute('webroot', '/'), '/'), JSON_UNESCAPED_SLASHES) ?>;
  /* M5 T27 + T29 — per-user preferences exposed to Alpine.
     quickAddForm reads:
       - EQSL_PREFS.block_dupes_in_activation — disables Save when
         the dupe-check badge is red (T27).
       - EQSL_PREFS.voice_input_callsign — gates the NATO-phonetic
         mic button on the callsign field (T29).
     False for guests / unauthenticated requests. */
  window.EQSL_PREFS = <?= json_encode([
      'block_dupes_in_activation' => (bool)($_headUser?->block_dupes_in_activation ?? false),
      'voice_input_callsign' => (bool)($_headUser?->voice_input_callsign ?? false),
  ], JSON_UNESCAPED_SLASHES) ?>;
</script>
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

<!--
  Single compiled CSS bundle: Tailwind base/components/utilities +
  DaisyUI component classes + our theme.css brand layer, all in one
  file. Build it with `npm run build:css` and commit the output.
-->
<link rel="stylesheet"
      href="<?= $this->Url->build('/css/dist.css') ?>?v=<?= @filemtime(WWW_ROOT . 'css/dist.css') ?>">
<link rel="stylesheet"
      href="<?= $this->Url->build('/css/app.css') ?>?v=<?= @filemtime(WWW_ROOT . 'css/app.css') ?>">
<?= $this->fetch('meta') ?>
</head>
<body>
<?php
/* M5 T3 — Determine active route once for the bottom-tab highlight.
   $tabActive('/qsos') returns true for /qsos AND /qsos/123/edit etc.
   Root path '/' must equality-match so the home tab doesn't claim every
   page. */
$tabPath = $this->getRequest()->getPath();
$tabActive = function (string $prefix) use ($tabPath): bool {
    return $prefix === '/' ? $tabPath === '/' : str_starts_with($tabPath, $prefix);
};
$identity = $this->getRequest()->getAttribute('identity');
$userData = $identity && method_exists($identity, 'getOriginalData') ? $identity->getOriginalData() : null;
$isAdmin = is_object($userData) && (string)($userData->role ?? '') === 'admin';
?>
<a href="#main-content" class="skip-link">Skip to main content</a>
<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand" href="/" aria-label="eQSL Card — Receiving Station">
      eQSL Card
      <span class="brand-mark" aria-hidden="true"></span>
    </a>

    <!-- Theme toggle lives outside the collapse menu so it stays reachable
         on mobile (where the collapse menu is hidden in favour of the
         bottom-tab bar). On desktop, flex order-lg-last keeps it on the
         far right after the nav-links group. -->
    <button type="button" id="themeToggle" class="theme-toggle-btn order-lg-last"
            aria-label="Toggle colour scheme"
            title="Toggle colour scheme">
      <span aria-hidden="true">
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
        <svg class="theme-icon theme-icon--moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px;">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
      </span>
    </button>

    <div class="navbar-collapse" id="mainNav">
    <ul class="navbar-nav ms-auto">
      <?php if ($identity): ?>
        <li class="nav-item"><a class="nav-link" href="/dashboard">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/qsos">Logbook</a></li>
        <li class="nav-item"><a class="nav-link" href="/cards">Cards</a></li>
        <li class="nav-item"><a class="nav-link" href="/activations">Activations</a></li>
        <li class="nav-item"><a class="nav-link" href="/card-backgrounds">Backgrounds</a></li>
        <li class="nav-item"><a class="nav-link" href="/templates">Templates</a></li>
        <li class="nav-item">
          <a class="nav-link" href="/net-sessions">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align: -2px; margin-right: 3px;"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1"/></svg>Net control</a>
        </li>
        <li class="nav-item"><a class="nav-link" href="/help">Help</a></li>
        <?php if ($isAdmin): ?>
          <li class="nav-item">
            <details class="dropdown dropdown-end">
              <summary class="nav-link cursor-pointer select-none list-none">Admin &#9662;</summary>
              <ul class="dropdown-content menu bg-base-100 rounded-box shadow-lg p-1 mt-1 w-52 border border-base-300" style="z-index:1010;">
                <li><a href="/admin">Dashboard</a></li>
                <li><a href="/admin/settings">Settings</a></li>
                <li><a href="/admin/templates/pending">Pending templates</a></li>
                <li><a href="/admin/users">Users</a></li>
                <li><a href="/admin/cards">All cards</a></li>
                <li><a href="/admin/card-backgrounds">All backgrounds</a></li>
                <li><a href="/admin/audit">Audit log</a></li>
                <li><a href="/admin/callsign-lookups">Callsign auto-complete</a></li>
                <li><a href="/admin/cleanup">Cleanup</a></li>
                <li><hr class="my-1 border-base-300 opacity-50"></li>
                <li><a href="/admin/upgrade">Run migrations</a></li>
              </ul>
            </details>
          </li>
        <?php endif; ?>
        <li class="nav-item">
          <?= $this->Form->postLink('Sign out', '/logout', ['class' => 'nav-link']) ?>
        </li>
      <?php else: ?>
        <li class="nav-item"><a class="nav-link" href="/login">Sign in</a></li>
        <li class="nav-item"><a class="nav-link" href="/help">Help</a></li>
        <li class="nav-item"><a class="nav-link btn btn-primary text-white" href="/register">Create account</a></li>
      <?php endif; ?>
    </ul>
    </div>
  </div>
</nav>
<main class="container" id="main-content" tabindex="-1">
  <?= $this->Flash->render() ?>

  <?php /* M5 T23 — Sync status pill. Visible only when there's
         something to communicate (queued rows or active sync).
         Logged-in only because guests have no quick-add path that
         could enqueue. Tap to expand the pending list with per-row
         retry / delete. */ ?>
  <?php if ($identity): ?>
    <div x-data="syncStatusPill()" x-show="visible" x-cloak class="sync-pill-wrap">
      <button type="button" :class="pillClass" @click="toggleExpanded()"
              :aria-expanded="expanded.toString()"
              :aria-label="label + (expanded ? ' (collapse)' : ' (expand)')">
        <span class="sync-pill__dot" aria-hidden="true"></span>
        <span x-text="label"></span>
      </button>
      <div x-show="expanded" x-cloak class="sync-pill__panel">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <strong>Pending QSOs</strong>
          <button type="button" class="btn btn-sm btn-outline-primary"
                  @click="retry()"
                  :disabled="state === 'syncing' || !online">Retry now</button>
        </div>
        <p x-show="rows.length === 0" x-cloak class="form-text">No pending rows.</p>
        <ul class="sync-pill__rows" x-show="rows.length > 0" x-cloak>
          <template x-for="row in rows" :key="row.uuid">
            <li class="sync-pill__row">
              <div>
                <strong x-text="row.data.call_worked"></strong>
                <span class="text-muted small ms-2">
                  <span x-text="row.data.frequency_mhz || '—'"></span> ·
                  <span x-text="row.data.mode || '—'"></span> ·
                  <span x-text="formatTime(row.queued_at)"></span>
                </span>
                <p x-show="row.last_error" x-cloak class="text-danger small mb-0" x-text="row.last_error"></p>
              </div>
              <button type="button" class="btn btn-sm btn-outline-danger"
                      @click="deleteRow(row.uuid)"
                      :aria-label="`Delete queued QSO for ${row.data.call_worked}`">Delete</button>
            </li>
          </template>
        </ul>
        <p x-show="lastError" x-cloak class="text-danger small mt-2 mb-0">
          Last error: <span x-text="lastError"></span>
        </p>
      </div>
    </div>
  <?php endif; ?>

  <?= $this->fetch('content') ?>
</main>

<?php /* ================================================================
       M5 T3 — Mobile bottom-tab navigation
       Visible only below the lg breakpoint (< 992 px). Replaces the
       hamburger-collapse menu on phones with one-thumb navigation. The
       "More" tab opens a bottom sheet via Alpine.js with secondary
       destinations (Backgrounds, Templates, Help, Admin, Sign out).
       Quick add deep-links to /qsos/new today; will swap to /qsos/quick
       once T7 lands the dedicated portable-entry route.
       ================================================================ */ ?>
<?php if ($identity): ?>
<div x-data="{ moreOpen: false }" @keydown.escape.window="moreOpen = false">
  <nav class="mobile-tabbar" aria-label="Primary mobile navigation">
    <a class="mobile-tabbar__btn" href="/dashboard" <?= $tabActive('/dashboard') ? 'aria-current="page"' : '' ?>>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
      <span>Home</span>
    </a>
    <a class="mobile-tabbar__btn" href="/qsos" <?= $tabActive('/qsos') && !str_starts_with($tabPath, '/qsos/new') && !str_starts_with($tabPath, '/qsos/quick') ? 'aria-current="page"' : '' ?>>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      <span>Logbook</span>
    </a>
    <a class="mobile-tabbar__btn mobile-tabbar__btn--primary" href="/qsos/quick" <?= $tabActive('/qsos/quick') ? 'aria-current="page"' : '' ?>>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      <span>Quick add</span>
    </a>
    <a class="mobile-tabbar__btn" href="/cards" <?= $tabActive('/cards') ? 'aria-current="page"' : '' ?>>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span>Cards</span>
    </a>
    <button type="button" class="mobile-tabbar__btn" @click="moreOpen = true"
            :aria-expanded="moreOpen.toString()" aria-haspopup="dialog"
            aria-controls="mobileMoreSheet">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg>
      <span>More</span>
    </button>
  </nav>

  <div class="mobile-sheet" id="mobileMoreSheet" x-show="moreOpen" x-cloak
       role="dialog" aria-modal="true" aria-label="More navigation">
    <div class="mobile-sheet__backdrop" @click="moreOpen = false"></div>
    <div class="mobile-sheet__panel" @click.stop x-transition.opacity>
      <div class="mobile-sheet__handle" aria-hidden="true"></div>

      <div class="mobile-sheet__heading">Library</div>
      <a class="mobile-sheet__item" href="/activations" @click="moreOpen = false">Activations</a>
      <a class="mobile-sheet__item" href="/card-backgrounds" @click="moreOpen = false">Backgrounds</a>
      <a class="mobile-sheet__item" href="/templates" @click="moreOpen = false">Templates</a>
      <a class="mobile-sheet__item" href="/net-sessions" @click="moreOpen = false">Net control</a>
      <a class="mobile-sheet__item" href="/help" @click="moreOpen = false">Help</a>

      <?php if ($isAdmin): ?>
        <div class="mobile-sheet__divider"></div>
        <div class="mobile-sheet__heading">Admin</div>
        <a class="mobile-sheet__item" href="/admin" @click="moreOpen = false">Dashboard</a>
        <a class="mobile-sheet__item" href="/admin/settings" @click="moreOpen = false">Settings</a>
        <a class="mobile-sheet__item" href="/admin/templates/pending" @click="moreOpen = false">Pending templates</a>
        <a class="mobile-sheet__item" href="/admin/users" @click="moreOpen = false">Users</a>
        <a class="mobile-sheet__item" href="/admin/cards" @click="moreOpen = false">All cards</a>
        <a class="mobile-sheet__item" href="/admin/card-backgrounds" @click="moreOpen = false">All backgrounds</a>
        <a class="mobile-sheet__item" href="/admin/audit" @click="moreOpen = false">Audit log</a>
        <a class="mobile-sheet__item" href="/admin/callsign-lookups" @click="moreOpen = false">Callsign auto-complete</a>
        <a class="mobile-sheet__item" href="/admin/cleanup" @click="moreOpen = false">Cleanup</a>
        <a class="mobile-sheet__item" href="/admin/upgrade" @click="moreOpen = false">Run migrations</a>
      <?php endif; ?>

      <div class="mobile-sheet__divider"></div>
      <?= $this->Form->postLink('Sign out', '/logout', ['class' => 'mobile-sheet__item mobile-sheet__item--danger']) ?>
    </div>
  </div>
</div>
<?php else: /* Guest mobile nav — smaller surface, no More sheet needed */ ?>
<nav class="mobile-tabbar" aria-label="Primary mobile navigation">
  <a class="mobile-tabbar__btn" href="/" <?= $tabActive('/') ? 'aria-current="page"' : '' ?>>
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
    <span>Home</span>
  </a>
  <a class="mobile-tabbar__btn" href="/help" <?= $tabActive('/help') ? 'aria-current="page"' : '' ?>>
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 4 2c-.7.5-1.5 1-1.5 2"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <span>Help</span>
  </a>
  <a class="mobile-tabbar__btn" href="/login" <?= $tabActive('/login') ? 'aria-current="page"' : '' ?>>
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
    <span>Sign in</span>
  </a>
  <a class="mobile-tabbar__btn mobile-tabbar__btn--primary" href="/register" <?= $tabActive('/register') ? 'aria-current="page"' : '' ?>>
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="17" y1="11" x2="23" y2="11"/></svg>
    <span>Register</span>
  </a>
</nav>
<?php endif; ?>
<footer class="site-footer">
  <div class="container">
    <span class="site-footer__rule" aria-hidden="true"></span>
    <p>
      <span class="eyebrow">Station log</span>
      Open-source eQSL card workbench for amateur radio operators ·
      <a href="/help">Help</a> ·
      Built by <a href="https://robbi.my" rel="noopener">Robbi Nespu</a> ·
      9W2NSP · <span class="footer-mono"><?= date('Y-m-d') ?> UTC</span>
    </p>
  </div>
</footer>
<script src="<?= $this->Url->build('/js/focus-trap.js') ?>" defer></script>
<script src="<?= $this->Url->build('/js/maidenhead.js') ?>" defer></script>
<script src="<?= $this->Url->build('/js/bands.js') ?>" defer></script>
<script src="<?= $this->Url->build('/js/nato.js') ?>" defer></script>
<script src="<?= $this->Url->build('/js/offline-queue.js') ?>" defer></script>
<script src="<?= $this->Url->build('/js/offline-sync.js') ?>" defer></script>
<script src="<?= $this->Url->build('/js/app.js') ?>" defer></script>
<?= $this->fetch('script') ?>
<!--
  Alpine is intentionally loaded LAST: it auto-starts in a microtask as
  soon as its script executes, and defer scripts run in document order.
  If Alpine ran before the page-specific scripts (designer.js, app.js
  factories), `x-data="designer(...)"` would resolve against a `designer`
  global that isn't defined yet and Alpine would fall back to an empty
  data scope — every directive inside the affected subtree then errors
  with "X is not defined" and the panel renders blank. Keep Alpine at
  the bottom so every factory global is set by the time Alpine processes
  the DOM.
-->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</body>
</html>
