<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="<?= $this->getRequest()->getAttribute('csrfToken') ?>">
<title><?= $this->fetch('title') ?: 'eQSL Card · Receiving Station' ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $this->Url->build('/css/theme.css') ?>">
<link rel="stylesheet" href="<?= $this->Url->build('/css/app.css') ?>">
<?= $this->fetch('meta') ?>
</head>
<body>
<nav class="navbar navbar-expand">
  <div class="container">
    <a class="navbar-brand" href="/" aria-label="eQSL Card — Receiving Station">
      eQSL Card
      <span class="brand-mark" aria-hidden="true"></span>
    </a>
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
            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button">Admin</a>
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
</nav>
<main class="container">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="<?= $this->Url->build('/js/app.js') ?>" defer></script>
<?= $this->fetch('script') ?>
<!-- Alpine is intentionally loaded LAST: it auto-starts in a microtask
     as soon as its script executes, and defer scripts run in document
     order. If Alpine ran before the page-specific scripts (designer.js,
     app.js factories), `x-data="designer(...)"` would resolve against
     a `designer` global that isn't defined yet and Alpine would fall
     back to an empty data scope — every directive inside the affected
     subtree then errors with "X is not defined" and the panel renders
     blank. Keep Alpine at the bottom so every factory global is set
     by the time Alpine processes the DOM. -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</body>
</html>
