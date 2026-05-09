<h1>QSO with <?= h($qso->call_worked) ?></h1>

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

<a class="btn btn-primary" href="/qsos/<?= $qso->id ?>/edit">Edit</a>
<a class="btn btn-secondary" href="/qsos">Back to logbook</a>
<?= $this->Form->postLink('Delete', '/qsos/' . $qso->id . '/delete', [
    'class' => 'btn btn-outline-danger',
    'confirm' => 'Permanently delete this QSO? This cannot be undone.',
]) ?>
