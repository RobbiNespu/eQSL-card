<h1><?= h($title) ?></h1>

<p>After uploading a new release zip via FTP, run pending migrations and clear caches here.</p>

<?php if ($migrationsResult): ?>
  <div class="alert alert-<?= $migrationsResult['ok'] ? 'success' : 'danger' ?>">
    <?= h($migrationsResult['message']) ?>
  </div>
<?php endif; ?>

<h2>Migration status</h2>
<table class="table table-sm">
  <thead><tr><th>Status</th><th>ID</th><th>Name</th></tr></thead>
  <tbody>
  <?php foreach ($statusRows as $row): ?>
    <tr class="<?= ($row['status'] ?? '') === 'down' ? 'table-warning' : '' ?>">
      <td><?= h($row['status'] ?? '') ?></td>
      <td><?= h($row['id'] ?? '') ?></td>
      <td><?= h($row['name'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?= $this->Form->create(null) ?>
<button class="btn btn-primary">Apply pending migrations &amp; clear caches</button>
<?= $this->Form->end() ?>
