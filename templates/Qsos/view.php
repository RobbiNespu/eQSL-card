<?php $isNet = ($qso->qso_type ?? 'contact') === 'net'; ?>
<h1>
  <?= $isNet ? 'Net check-in by' : 'QSO with' ?>
  <span class="callsign"><?= h($qso->call_worked) ?></span>
  <?php if ($isNet): ?>
    <span class="badge bg-info ms-2">NET</span>
  <?php endif; ?>
</h1>
<p>
  Logged on <?= h($qso->qso_datetime_utc?->format('Y-m-d H:i')) ?> UTC
  via <?= h(\App\Service\Transport::label($qso->transport ?? null)) ?>.
</p>

<?php if ($isNet): ?>
  <h2 class="h5">Net details</h2>
  <dl class="row dl-stack mb-4">
    <dt class="col-sm-3">NCS</dt>
    <dd class="col-sm-9"><span class="callsign"><?= h($qso->ncs_callsign) ?></span></dd>

    <dt class="col-sm-3">Net title</dt>
    <dd class="col-sm-9"><?= h($qso->net_title) ?></dd>

    <?php if (!empty($qso->net_organisation)): ?>
      <dt class="col-sm-3">Organisation</dt>
      <dd class="col-sm-9"><?= h($qso->net_organisation) ?></dd>
    <?php endif; ?>
  </dl>
<?php endif; ?>

<h2 class="h5">QSO details</h2>
<dl class="row dl-stack">
  <dt class="col-sm-3">Date / Time UTC</dt>
  <dd class="col-sm-9"><?= h($qso->qso_datetime_utc?->format('Y-m-d H:i')) ?></dd>

  <dt class="col-sm-3">Transport</dt>
  <dd class="col-sm-9">
    <?= h(\App\Service\Transport::label($qso->transport ?? null)) ?>
    <?php if (!empty($qso->transport_meta)): ?>
      <span class="text-muted">· <?= h($qso->transport_meta) ?></span>
    <?php endif; ?>
  </dd>

  <dt class="col-sm-3">Frequency</dt>
  <dd class="col-sm-9"><?= $qso->frequency_mhz !== null && $qso->frequency_mhz !== '' ? h($qso->frequency_mhz) . ' MHz' : '<span class="text-muted">—</span>' ?></dd>

  <dt class="col-sm-3">Band</dt>
  <dd class="col-sm-9"><?= $qso->band ? h($qso->band) : '<span class="text-muted">—</span>' ?></dd>

  <dt class="col-sm-3">Mode</dt>
  <dd class="col-sm-9"><?= $qso->mode ? h($qso->mode) : '<span class="text-muted">—</span>' ?></dd>

  <dt class="col-sm-3">RST sent / received</dt>
  <dd class="col-sm-9"><?= h($qso->rst_sent) ?> / <?= h($qso->rst_received) ?></dd>

  <dt class="col-sm-3">Operator name</dt>
  <dd class="col-sm-9"><?= h($qso->operator_name) ?: '<span class="text-muted">—</span>' ?></dd>

  <dt class="col-sm-3">QTH</dt>
  <dd class="col-sm-9"><?= h($qso->operator_qth) ?: '<span class="text-muted">—</span>' ?></dd>

  <dt class="col-sm-3">Grid</dt>
  <dd class="col-sm-9"><?= h($qso->grid_square) ?: '<span class="text-muted">—</span>' ?></dd>

  <?php if (!empty($qso->notes)): ?>
    <dt class="col-sm-3">Notes</dt>
    <dd class="col-sm-9"><?= nl2br(h($qso->notes)) ?></dd>
  <?php endif; ?>
</dl>

<div class="d-flex gap-2 mt-4 flex-wrap">
  <a class="btn btn-primary" href="/qsos/<?= $qso->id ?>/render">Render eQSL</a>
  <a class="btn btn-outline-primary" href="/qsos/<?= $qso->id ?>/edit">Edit</a>
  <a class="btn btn-link" href="/qsos">Back to logbook</a>
  <span class="ms-auto">
    <?= $this->Form->postLink('Delete', '/qsos/' . $qso->id . '/delete', [
        'class' => 'btn btn-outline-danger',
        'confirm' => 'Permanently delete this QSO? This cannot be undone.',
    ]) ?>
  </span>
</div>
