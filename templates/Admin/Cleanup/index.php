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

  <div class="col-md-12">
    <div class="card p-3 mt-3">
      <h2>Expire old user cards</h2>
      <?php if (($cardRetentionDays ?? 0) <= 0): ?>
        <p class="text-muted">
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
          <button class="btn btn-danger"
                  onclick="return confirm('Soft-delete <?= h($cardsToExpire) ?> user-owned cards older than <?= h($cardRetentionDays) ?> days?')">
            Expire <?= h($cardsToExpire) ?> cards
          </button>
          <?= $this->Form->end() ?>
        <?php else: ?>
          <p class="text-muted">No user cards older than <?= h($cardRetentionDays) ?> days.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$fmtBytes = static function (int $b): string {
    if ($b <= 0) {
        return '0 B';
    }
    if ($b < 1024) {
        return $b . ' B';
    }
    if ($b < 1024 * 1024) {
        return round($b / 1024, 1) . ' KB';
    }
    return round($b / 1024 / 1024, 2) . ' MB';
};
?>

<h2 class="mt-5">Filesystem maintenance</h2>
<p class="text-muted">Wipe the on-disk caches, log files, and active sessions. Files are
removed from <code>tmp/cache/</code>, <code>logs/</code>, and <code>tmp/sessions/</code>
respectively. <code>.gitkeep</code> markers and subdirectory structure are preserved.</p>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card p-3">
      <h3 class="h5">Cache</h3>
      <p class="display-6"><?= h($cacheStats['count']) ?></p>
      <p class="text-muted small"><?= h($fmtBytes($cacheStats['bytes'])) ?> on disk</p>
      <?= $this->Form->create(null, ['url' => '/admin/cleanup/cache']) ?>
      <button class="btn btn-warning"
              <?= $cacheStats['count'] === 0 ? 'disabled' : '' ?>
              onclick="return confirm('Delete <?= h($cacheStats['count']) ?> cache files? Cake will rebuild them on the next request.')">
        Clear cache
      </button>
      <?= $this->Form->end() ?>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card p-3">
      <h3 class="h5">Logs</h3>
      <p class="display-6"><?= h($logStats['count']) ?></p>
      <p class="text-muted small"><?= h($fmtBytes($logStats['bytes'])) ?> on disk</p>
      <?= $this->Form->create(null, ['url' => '/admin/cleanup/logs']) ?>
      <button class="btn btn-warning"
              <?= $logStats['count'] === 0 ? 'disabled' : '' ?>
              onclick="return confirm('Delete <?= h($logStats['count']) ?> log files?')">
        Clear logs
      </button>
      <?= $this->Form->end() ?>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card p-3">
      <h3 class="h5">Callsign cache</h3>
      <p class="display-6"><?= h($callsignCacheCount) ?></p>
      <p class="text-muted small">Cached external lookups</p>
      <?= $this->Form->create(null, ['url' => '/admin/cleanup/callsign-cache']) ?>
      <button class="btn btn-warning"
              <?= $callsignCacheCount === 0 ? 'disabled' : '' ?>
              onclick="return confirm('Drop <?= h($callsignCacheCount) ?> cached callsign lookups? Subsequent lookups will hit upstream providers again.')">
        Clear callsign cache
      </button>
      <?= $this->Form->end() ?>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card p-3">
      <h3 class="h5">Sessions</h3>
      <p class="display-6"><?= h($sessionStats['count']) ?></p>
      <p class="text-muted small"><?= h($fmtBytes($sessionStats['bytes'])) ?> on disk</p>
      <p class="text-danger small"><strong>Warning:</strong> this signs out every user (including you).</p>
      <?= $this->Form->create(null, ['url' => '/admin/cleanup/sessions']) ?>
      <button class="btn btn-danger"
              <?= $sessionStats['count'] === 0 ? 'disabled' : '' ?>
              onclick="return confirm('Drop <?= h($sessionStats['count']) ?> active sessions and force every user (including you) to sign in again?')">
        Drop sessions
      </button>
      <?= $this->Form->end() ?>
    </div>
  </div>
</div>
