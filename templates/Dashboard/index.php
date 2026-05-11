<h1>Welcome, <?= h($user->callsign) ?></h1>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card text-center p-3">
      <div class="display-6"><?= h($stats['qsos_total']) ?></div>
      <div class="text-muted small">QSOs in your logbook</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center p-3">
      <div class="display-6"><?= h($stats['cards_total']) ?></div>
      <div class="text-muted small">eQSL cards</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center p-3">
      <div class="display-6"><?= h($stats['shared_total']) ?></div>
      <div class="text-muted small">Currently shared</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3">
      <h2 class="h6 mb-2">Quick actions</h2>
      <a href="/qsos/new" class="btn btn-primary btn-sm mb-1">+ New QSO</a>
      <a href="/qsos/import" class="btn btn-outline-primary btn-sm mb-1">Import ADIF/CSV</a>
      <a href="/templates/new" class="btn btn-outline-secondary btn-sm">Design template</a>
    </div>
  </div>
</div>

<?php if ($user->role === 'admin'): ?>
  <div class="alert alert-info mb-4">
    You're logged in as admin. <a href="/admin" class="alert-link">Open admin dashboard →</a>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-md-6">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h2 class="h4 mb-0">Recent cards</h2>
      <a href="/cards" class="btn btn-link btn-sm">View all →</a>
    </div>
    <?php if ($recentCards->count() === 0): ?>
      <div class="alert alert-light">No cards yet. <a href="/qsos">Render one from a QSO</a>.</div>
    <?php else: ?>
      <div class="row g-2">
        <?php foreach ($recentCards as $c): ?>
          <?php
          $thumbPath = \App\Service\CardRenderer::thumbPathFor($c->png_path);
          $previewSrc = is_file(WWW_ROOT . $thumbPath) ? $thumbPath : $c->png_path;
          ?>
          <div class="col-6">
            <a href="/cards/<?= $c->id ?>" class="text-decoration-none">
              <img src="/<?= h($previewSrc) ?>" class="img-fluid rounded" alt="" loading="lazy">
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="col-md-6">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h2 class="h4 mb-0">Recent QSOs</h2>
      <a href="/qsos" class="btn btn-link btn-sm">View all →</a>
    </div>
    <?php if ($recentQsos->count() === 0): ?>
      <div class="alert alert-light">No QSOs yet. <a href="/qsos/new">Add one</a> or <a href="/qsos/import">import a log</a>.</div>
    <?php else: ?>
      <table class="table table-sm">
        <thead><tr><th>Callsign</th><th>UTC</th><th>Band</th><th>Mode</th></tr></thead>
        <tbody>
          <?php foreach ($recentQsos as $q): ?>
            <tr>
              <td><a href="/qsos/<?= $q->id ?>"><strong><?= h($q->call_worked) ?></strong></a></td>
              <td><?= h($q->qso_datetime_utc?->format('Y-m-d H:i')) ?></td>
              <td><?= h($q->band) ?></td>
              <td><?= h($q->mode) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
