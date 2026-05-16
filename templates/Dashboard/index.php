<h1>Welcome back, <span class="callsign"><?= h($user->callsign) ?></span></h1>
<p>Your station at a glance. Recent activity below; jump into the logbook or template designer from the quick actions.</p>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card card-body text-center">
      <div class="display-6"><?= h($stats['qsos_total']) ?></div>
      <div class="form-text">QSOs in your logbook</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-body text-center">
      <div class="display-6"><?= h($stats['cards_total']) ?></div>
      <div class="form-text">eQSL cards</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-body text-center">
      <div class="display-6"><?= h($stats['shared_total']) ?></div>
      <div class="form-text">Currently shared</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-body">
      <h2 class="h6 mb-2">Quick actions</h2>
      <div class="d-flex flex-column gap-2">
        <a href="/qsos/new" class="btn btn-primary btn-sm">+ New QSO</a>
        <a href="/qsos/import" class="btn btn-outline-primary btn-sm">Import ADIF / CSV</a>
        <a href="/templates/new" class="btn btn-outline-secondary btn-sm">Design template</a>
      </div>
    </div>
  </div>
</div>

<?php if ($user->role === 'admin'): ?>
  <div class="alert alert-info mb-4">
    You're signed in as admin. <a href="/admin">Open the admin dashboard &rarr;</a>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-md-6">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h2 class="h5 mb-0">Recent cards</h2>
      <a href="/cards" class="btn btn-link btn-sm">View all &rarr;</a>
    </div>
    <?php if ($recentCards->count() === 0): ?>
      <?= $this->element('ui/empty_state', [
          'message'   => 'No cards yet.',
          'cta_url'   => '/qsos',
          'cta_label' => 'Render one from a QSO',
      ]) ?>
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
      <h2 class="h5 mb-0">Recent QSOs</h2>
      <a href="/qsos" class="btn btn-link btn-sm">View all &rarr;</a>
    </div>
    <?php if ($recentQsos->count() === 0): ?>
      <?= $this->element('ui/empty_state', [
          'message'   => 'No QSOs yet.',
          'cta_url'   => '/qsos/new',
          'cta_label' => 'Add one',
      ]) ?>
    <?php else: ?>
      <table class="table table-sm">
        <thead><tr><th>Callsign</th><th>UTC</th><th>Band</th><th>Mode</th></tr></thead>
        <tbody>
          <?php foreach ($recentQsos as $q): ?>
            <tr>
              <td>
                <a href="/qsos/<?= $q->id ?>"><?= $this->element('ui/callsign', ['call' => $q->call_worked]) ?></a>
                <?= $this->element('ui/badge_qso_type', ['qso' => $q]) ?>
                <?= $this->element('ui/badge_transport', ['qso' => $q]) ?>
              </td>
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
