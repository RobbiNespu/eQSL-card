<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
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

<!--
  Single compiled CSS bundle: Tailwind base/components/utilities +
  DaisyUI component classes + our theme.css brand layer, all in one
  file. Build it with `npm run build:css` and commit the output.
-->
<link rel="stylesheet"
      href="<?= $this->Url->build('/css/dist.css') ?>?v=<?= @filemtime(WWW_ROOT . 'css/dist.css') ?>">
<link rel="stylesheet" href="<?= $this->Url->build('/css/app.css') ?>">
<?= $this->fetch('meta') ?>
</head>
<body>
<a href="#main-content" class="skip-link">Skip to main content</a>
<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand" href="/" aria-label="eQSL Card — Receiving Station">
      eQSL Card
      <span class="brand-mark" aria-hidden="true"></span>
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse"
            data-target="#mainNav" aria-controls="mainNav"
            aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
    <ul class="navbar-nav ms-auto">
      <?php if ($this->getRequest()->getAttribute('identity')): ?>
        <li class="nav-item"><a class="nav-link" href="/dashboard">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/qsos">Logbook</a></li>
        <li class="nav-item"><a class="nav-link" href="/cards">Cards</a></li>
        <li class="nav-item"><a class="nav-link" href="/uploads">Library</a></li>
        <li class="nav-item"><a class="nav-link" href="/templates">Templates</a></li>
        <li class="nav-item"><a class="nav-link" href="/help">Help</a></li>
        <?php
        $identity = $this->getRequest()->getAttribute('identity');
        $userData = method_exists($identity, 'getOriginalData') ? $identity->getOriginalData() : null;
        $isAdmin = is_object($userData) && (string)($userData->role ?? '') === 'admin';
        ?>
        <?php if ($isAdmin): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#" role="button"
               aria-expanded="false">Admin</a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="/admin">Dashboard</a></li>
              <li><a class="dropdown-item" href="/admin/settings">Settings</a></li>
              <li><a class="dropdown-item" href="/admin/templates/pending">Pending templates</a></li>
              <li><a class="dropdown-item" href="/admin/users">Users</a></li>
              <li><a class="dropdown-item" href="/admin/cards">All cards</a></li>
              <li><a class="dropdown-item" href="/admin/uploads">All uploads</a></li>
              <li><a class="dropdown-item" href="/admin/audit">Audit log</a></li>
              <li><a class="dropdown-item" href="/admin/callsign-directory">Callsign directory</a></li>
              <li><a class="dropdown-item" href="/admin/cleanup">Cleanup</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/admin/upgrade">Run migrations</a></li>
            </ul>
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
    </ul>
    </div>
  </div>
</nav>
<main class="container" id="main-content" tabindex="-1">
  <?= $this->Flash->render() ?>
  <?= $this->fetch('content') ?>
</main>
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
