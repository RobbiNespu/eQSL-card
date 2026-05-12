<h1><?= h($title) ?></h1>
<p>Site-wide overview. Tiles below show totals; jump to the relevant admin area for detail.</p>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card card-body text-center">
      <div class="display-6"><?= h($stats['users_total']) ?></div>
      <div class="form-text">Users (<?= h($stats['users_admin']) ?> admin)</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-body text-center">
      <div class="display-6"><?= h($stats['cards_total']) ?></div>
      <div class="form-text">Cards (<?= h($stats['cards_guest']) ?> guest-generated)</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-body text-center">
      <div class="display-6"><?= h($stats['templates_total']) ?></div>
      <div class="form-text">Templates (<?= h($stats['templates_pending']) ?> awaiting review)</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-body text-center">
      <div class="display-6"><?= h($stats['storage_mb_uploads']) ?> MB</div>
      <div class="form-text">Upload storage</div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-md-6">
    <h2 class="h5">Quick links</h2>
    <ul class="list-group">
      <li class="list-group-item"><a href="/admin/settings">Settings (default eQSL background, SMTP, etc.)</a></li>
      <li class="list-group-item"><a href="/admin/templates/pending">Pending template moderation (<?= h($stats['templates_pending']) ?>)</a></li>
      <li class="list-group-item"><a href="/admin/users">User management</a></li>
      <li class="list-group-item"><a href="/admin/cards">All cards browser</a></li>
      <li class="list-group-item"><a href="/admin/audit">Audit log viewer</a></li>
      <li class="list-group-item"><a href="/admin/cleanup">Cleanup tools (purge old guest cards, prune orphan uploads)</a></li>
      <li class="list-group-item"><a href="/admin/upgrade">Run pending migrations</a></li>
    </ul>
  </div>
  <div class="col-md-6">
    <h2 class="h5">Recent activity</h2>
    <table class="table table-sm">
      <thead><tr><th>When</th><th>Event</th><th>Actor</th><th>Target</th></tr></thead>
      <tbody>
      <?php foreach ($recentAudit as $log): ?>
        <tr>
          <td><span title="<?= h($log->created_at?->format('Y-m-d H:i:s')) ?>"><?= h($log->created_at?->format('m-d H:i')) ?></span></td>
          <td><code><?= h($log->event) ?></code></td>
          <td><?= h($log->user->callsign ?? ($log->actor_user_id ? '#' . $log->actor_user_id : 'guest')) ?></td>
          <td><?= h($log->target_type) ?> <?= $log->target_id ? '#' . h($log->target_id) : '' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
