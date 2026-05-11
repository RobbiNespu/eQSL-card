<?php $isNet = ($qso->qso_type ?? 'contact') === 'net'; ?>
<h1>
  <?= $isNet ? 'Net check-in by' : 'QSO with' ?>
  <?= h($qso->call_worked) ?>
  <?php if ($isNet): ?>
    <span class="badge bg-info text-dark align-middle ms-2">NET</span>
  <?php endif; ?>
</h1>

<?php if ($isNet): ?>
  <dl class="row mb-4">
    <dt class="col-sm-3">NCS</dt>
    <dd class="col-sm-9"><strong><?= h($qso->ncs_callsign) ?></strong></dd>

    <dt class="col-sm-3">Net title</dt>
    <dd class="col-sm-9"><?= h($qso->net_title) ?></dd>

    <?php if (!empty($qso->net_organisation)): ?>
      <dt class="col-sm-3">Organisation</dt>
      <dd class="col-sm-9"><?= h($qso->net_organisation) ?></dd>
    <?php endif; ?>
  </dl>
<?php endif; ?>

<dl class="row">
  <dt class="col-sm-3">Date/Time UTC</dt>
  <dd class="col-sm-9"><?= h($qso->qso_datetime_utc?->format('Y-m-d H:i')) ?></dd>

  <dt class="col-sm-3">Frequency</dt>
  <dd class="col-sm-9"><?= h($qso->frequency_mhz) ?> MHz</dd>

  <dt class="col-sm-3">Band</dt>
  <dd class="col-sm-9"><?= h($qso->band) ?></dd>

  <dt class="col-sm-3">Mode</dt>
  <dd class="col-sm-9"><?= h($qso->mode) ?></dd>

  <dt class="col-sm-3">RST sent / received</dt>
  <dd class="col-sm-9"><?= h($qso->rst_sent) ?> / <?= h($qso->rst_received) ?></dd>

  <dt class="col-sm-3">Operator name</dt>
  <dd class="col-sm-9"><?= h($qso->operator_name) ?></dd>

  <dt class="col-sm-3">QTH</dt>
  <dd class="col-sm-9"><?= h($qso->operator_qth) ?></dd>

  <dt class="col-sm-3">Grid</dt>
  <dd class="col-sm-9"><?= h($qso->grid_square) ?></dd>

  <dt class="col-sm-3">Notes</dt>
  <dd class="col-sm-9"><?= nl2br(h($qso->notes)) ?></dd>
</dl>

<a class="btn btn-primary" href="/qsos/<?= $qso->id ?>/render">Render eQSL</a>
<a class="btn btn-outline-secondary" href="/qsos/<?= $qso->id ?>/edit">Edit</a>
<a class="btn btn-link" href="/qsos">Back to logbook</a>
<?= $this->Form->postLink('Delete', '/qsos/' . $qso->id . '/delete', [
    'class' => 'btn btn-outline-danger',
    'confirm' => 'Permanently delete this QSO? This cannot be undone.',
]) ?>
