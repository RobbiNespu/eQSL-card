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
  Style pipeline:
    1. DaisyUI compiled CSS (component classes: .btn, .card, .alert, .badge,
       .input, .modal, .dropdown, .navbar, ...). Loaded first so our
       theme.css can override anything we want to look more shadcn-ish.
    2. Tailwind Play CDN — utility classes (flex, gap, grid, p-*, m-*).
       Loads as a <script> that JIT-compiles in the browser. Fine for
       this app; for high-traffic prod, pre-compile and ship dist CSS.
    3. theme.css — fonts, brand tokens, .btn/.card/.alert overrides,
       and a small Bootstrap-compat shim (.row / .col-md-* / .mb-3 /
       .d-flex …) so existing templates don't need re-classing.
    4. app.css — page-level overrides on top of everything.
-->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css">
<script src="https://cdn.tailwindcss.com/3.4.3"></script>
<script>
  /*
   * Tailwind + DaisyUI inline config. With the Play CDN we can override
   * tokens here without a tailwind.config.js. The custom theme matches
   * the emerald + zinc palette we use elsewhere.
   */
  tailwind.config = {
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
</script>

<link rel="stylesheet" href="<?= $this->Url->build('/css/theme.css') ?>">
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
        <li class="nav-item"><a class="nav-link btn btn-primary text-white" href="/register">Create account</a></li>
      <?php endif; ?>
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
