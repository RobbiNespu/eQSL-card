<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="<?= $this->getRequest()->getAttribute('csrfToken') ?>">
<title><?= $this->fetch('title') ?: 'eQSL Card' ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $this->Url->build('/css/app.css') ?>">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand" href="/">eQSL Card</a>
    <ul class="navbar-nav ms-auto">
      <?php if ($this->getRequest()->getAttribute('identity')): ?>
        <li class="nav-item"><a class="nav-link" href="/dashboard">Dashboard</a></li>
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
<script src="<?= $this->Url->build('/js/app.js') ?>" defer></script>
</body>
</html>
