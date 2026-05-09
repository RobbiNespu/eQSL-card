<h1><?= h($title) ?></h1>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-2">
    <label class="form-label">Older than (days)</label>
    <input type="number" name="days" value="<?= h($days) ?>" min="1" class="form-control">
  </div>
  <div class="col-md-2 align-self-end">
    <button class="btn btn-primary w-100">Refresh preview</button>
  </div>
</form>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card p-3">
      <h2>Guest cards to purge</h2>
      <p class="display-6"><?= h($guestCardsCount) ?></p>
      <?php if ($guestCardsCount > 0): ?>
        <table class="table table-sm">
          <thead><tr><th>ID</th><th>Visit</th><th>Created</th></tr></thead>
          <tbody>
            <?php foreach ($guestCardsSample as $c): ?>
              <tr>
                <td>#<?= h($c->id) ?></td>
                <td>v#<?= h($c->guest_visit_id) ?></td>
                <td><?= h($c->created_at?->format('Y-m-d')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?= $this->Form->create(null, ['url' => '/admin/cleanup/purge-guests']) ?>
        <input type="hidden" name="days" value="<?= h($days) ?>">
        <button class="btn btn-danger" onclick="return confirm('Permanently delete <?= h($guestCardsCount) ?> guest cards and their PNG/PDF files?')">Purge guest cards</button>
        <?= $this->Form->end() ?>
      <?php else: ?>
        <p class="text-muted">No guest cards older than <?= h($days) ?> days.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card p-3">
      <h2>Orphaned uploads to prune</h2>
      <p class="display-6"><?= h($orphanUploadsCount) ?></p>
      <?php if ($orphanUploadsCount > 0): ?>
        <table class="table table-sm">
          <thead><tr><th>ID</th><th>Owner</th><th>Created</th><th>Size</th></tr></thead>
          <tbody>
            <?php foreach ($orphanUploadsSample as $u): ?>
              <tr>
                <td>#<?= h($u->id) ?></td>
                <td><?= $u->user_id ? 'u#' . h($u->user_id) : ($u->guest_visit_id ? 'g#' . h($u->guest_visit_id) : '?') ?></td>
                <td><?= h($u->created_at?->format('Y-m-d')) ?></td>
                <td><?= h(round(((int)$u->file_size_bytes) / 1024)) ?> KB</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?= $this->Form->create(null, ['url' => '/admin/cleanup/prune-uploads']) ?>
        <input type="hidden" name="days" value="<?= h($days) ?>">
        <button class="btn btn-danger" onclick="return confirm('Permanently delete <?= h($orphanUploadsCount) ?> orphaned upload rows and their files?')">Prune orphans</button>
        <?= $this->Form->end() ?>
      <?php else: ?>
        <p class="text-muted">No orphan uploads older than <?= h($days) ?> days.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
