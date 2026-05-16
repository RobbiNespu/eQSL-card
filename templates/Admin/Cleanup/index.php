<h1><?= h($title) ?></h1>
<p>Storage maintenance: purge old guest cards, prune orphaned uploads, expire user cards past retention, wipe on-disk caches.</p>

<form method="get" class="row g-2 mb-4">
  <div class="col-md-2">
    <div class="field">
      <label class="form-label" for="days">Older than (days)</label>
      <input type="number" id="days" name="days" value="<?= h($days) ?>" min="1" class="form-control" placeholder="30">
      <p class="form-text">Items older than this many days are eligible for cleanup.</p>
    </div>
  </div>
  <div class="col-md-2 d-flex align-items-end">
    <button class="btn btn-primary w-100">Refresh preview</button>
  </div>
</form>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card card-body">
      <h2 class="h5">Guest cards to purge</h2>
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
        <?= $this->Form->button('Purge guest cards', [
            'class' => 'btn btn-outline-danger',
            'confirm' => 'Permanently delete ' . (int)$guestCardsCount . ' guest cards and their PNG/PDF files?',
        ]) ?>
        <?= $this->Form->end() ?>
      <?php else: ?>
        <p class="form-text">No guest cards older than <?= h($days) ?> days.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card card-body">
      <h2 class="h5">Orphaned uploads to prune</h2>
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
        <?= $this->Form->button('Prune orphans', [
            'class' => 'btn btn-outline-danger',
            'confirm' => 'Permanently delete ' . (int)$orphanUploadsCount . ' orphaned upload rows and their files?',
        ]) ?>
        <?= $this->Form->end() ?>
      <?php else: ?>
        <p class="form-text">No orphan uploads older than <?= h($days) ?> days.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12">
    <div class="card card-body">
      <h2 class="h5">Expire old user cards</h2>
      <?php if (($cardRetentionDays ?? 0) <= 0): ?>
        <p class="form-text">
          Disabled. Set <code>card_retention_days</code> in <a href="/admin/settings">Settings</a> to enable
          age-based soft-deletion of user-owned cards. Storage is reclaimed when you
          run <strong>Prune orphans</strong> after this.
        </p>
      <?php else: ?>
        <p>
          Retention: cards older than <strong><?= h($cardRetentionDays) ?> days</strong> will be
          soft-deleted. They still occupy disk until you run <strong>Prune orphans</strong> next.
        </p>
        <p class="display-6"><?= h($cardsToExpire) ?></p>
        <?php if ($cardsToExpire > 0): ?>
          <?= $this->Form->create(null, ['url' => '/admin/cleanup/expire-cards']) ?>
          <?= $this->Form->button('Expire ' . (int)$cardsToExpire . ' cards', [
              'class' => 'btn btn-outline-danger',
              'confirm' => 'Soft-delete ' . (int)$cardsToExpire . ' user-owned cards older than '
                  . (int)$cardRetentionDays . ' days?',
          ]) ?>
          <?= $this->Form->end() ?>
        <?php else: ?>
          <p class="form-text">No user cards older than <?= h($cardRetentionDays) ?> days.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$fmtBytes = static function (int $b): string {
    if ($b <= 0)            return '0 B';
    if ($b < 1024)          return $b . ' B';
    if ($b < 1024 * 1024)   return round($b / 1024, 1) . ' KB';
    return round($b / 1024 / 1024, 2) . ' MB';
};
?>

<h2>Filesystem maintenance</h2>
<p>Wipe the on-disk caches, log files, and active sessions. Files are
removed from <code>tmp/cache/</code>, <code>logs/</code>, and <code>tmp/sessions/</code>
respectively. <code>.gitkeep</code> markers and subdirectory structure are preserved.</p>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card card-body">
      <h3 class="h5">Cache</h3>
      <p class="display-6"><?= h($cacheStats['count']) ?></p>
      <p class="form-text mb-3"><?= h($fmtBytes($cacheStats['bytes'])) ?> on disk</p>
      <?= $this->Form->create(null, ['url' => '/admin/cleanup/cache']) ?>
      <?= $this->Form->button('Clear cache', [
          'class' => 'btn btn-outline-warning',
          'disabled' => $cacheStats['count'] === 0,
          'confirm' => 'Delete ' . (int)$cacheStats['count'] . ' cache files? Cake will rebuild them on the next request.',
      ]) ?>
      <?= $this->Form->end() ?>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card card-body">
      <h3 class="h5">Logs</h3>
      <p class="display-6"><?= h($logStats['count']) ?></p>
      <p class="form-text mb-3"><?= h($fmtBytes($logStats['bytes'])) ?> on disk</p>
      <?= $this->Form->create(null, ['url' => '/admin/cleanup/logs']) ?>
      <?= $this->Form->button('Clear logs', [
          'class' => 'btn btn-outline-warning',
          'disabled' => $logStats['count'] === 0,
          'confirm' => 'Delete ' . (int)$logStats['count'] . ' log files?',
      ]) ?>
      <?= $this->Form->end() ?>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card card-body">
      <h3 class="h5">Callsign cache</h3>
      <p class="display-6"><?= h($callsignCacheCount) ?></p>
      <p class="form-text mb-3">Cached external lookups</p>
      <?= $this->Form->create(null, ['url' => '/admin/cleanup/callsign-cache']) ?>
      <?= $this->Form->button('Clear callsign cache', [
          'class' => 'btn btn-outline-warning',
          'disabled' => $callsignCacheCount === 0,
          'confirm' => 'Drop ' . (int)$callsignCacheCount . ' cached callsign lookups? Subsequent lookups will hit upstream providers again.',
      ]) ?>
      <?= $this->Form->end() ?>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card card-body">
      <h3 class="h5">Sessions</h3>
      <p class="display-6"><?= h($sessionStats['count']) ?></p>
      <p class="form-text mb-2"><?= h($fmtBytes($sessionStats['bytes'])) ?> on disk</p>
      <p class="form-text text-danger mb-3"><strong>Warning:</strong> this signs out every user (including you).</p>
      <?= $this->Form->create(null, ['url' => '/admin/cleanup/sessions']) ?>
      <?= $this->Form->button('Drop sessions', [
          'class' => 'btn btn-outline-danger',
          'disabled' => $sessionStats['count'] === 0,
          'confirm' => 'Drop ' . (int)$sessionStats['count'] . ' active sessions and force every user (including you) to sign in again?',
      ]) ?>
      <?= $this->Form->end() ?>
    </div>
  </div>
</div>
