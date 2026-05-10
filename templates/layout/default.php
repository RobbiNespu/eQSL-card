<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="<?= $this->getRequest()->getAttribute('csrfToken') ?>">
<title><?= $this->fetch('title') ?: 'eQSL Card' ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $this->Url->build('/css/app.css') ?>">
<?= $this->fetch('meta') ?>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand" href="/">eQSL Card</a>
    <ul class="navbar-nav ms-auto">
      <?php if ($this->getRequest()->getAttribute('identity')): ?>
        <li class="nav-item"><a class="nav-link" href="/dashboard">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/qsos">Logbook</a></li>
        <li class="nav-item"><a class="nav-link" href="/cards">Cards</a></li>
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
              <li><a class="dropdown-item" href="/admin/audit">Audit log</a></li>
              <li><a class="dropdown-item" href="/admin/cleanup">Cleanup</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/admin/upgrade">Run migrations</a></li>
            </ul>
          </li>
        <?php endif; ?>
        <li class="nav-item">
          <?= $this->Form->postLink('Logout', '/logout', ['class' => 'nav-link']) ?>
        </li>
      <?php else: ?>
        <li class="nav-item"><a class="nav-link" href="/login">Sign in</a></li>
        <li class="nav-item"><a class="nav-link btn btn-primary text-white" href="/register">Create account</a></li>
      <?php endif; ?>
    </ul>
  </div>
</nav>
<main class="container py-4">
  <?= $this->Flash->render() ?>
  <?= $this->fetch('content') ?>
</main>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="<?= $this->Url->build('/js/app.js') ?>" defer></script>
<?= $this->fetch('script') ?>
</body>
</html>
