<?php
/*
 * M6 T9 — Net session detail view.
 *
 * Shows session metadata, status badge, and action buttons.
 * Cockpit / Analytics / Export are stubs for later tasks (T11/T17/T21/T22).
 */
$this->assign('title', $title);
?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Net session detail — owner view.',
]) ?>

<div class="card mb-4">
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">Status</dt>
      <dd class="col-sm-9">
        <?php if ($session->status === 'live'): ?>
          <span class="badge bg-success">Live</span>
        <?php elseif ($session->status === 'scheduled'): ?>
          <span class="badge bg-secondary">Scheduled</span>
        <?php else: ?>
          <span class="badge bg-dark">Ended</span>
        <?php endif; ?>
      </dd>

      <?php if ($session->net_organisation): ?>
        <dt class="col-sm-3">Organisation</dt>
        <dd class="col-sm-9"><?= h($session->net_organisation) ?></dd>
      <?php endif; ?>

      <?php if ($session->frequency_mhz): ?>
        <dt class="col-sm-3">Frequency</dt>
        <dd class="col-sm-9"><?= h($session->frequency_mhz) ?> MHz</dd>
      <?php endif; ?>

      <?php if ($session->band): ?>
        <dt class="col-sm-3">Band</dt>
        <dd class="col-sm-9"><?= h($session->band) ?></dd>
      <?php endif; ?>

      <?php if ($session->mode): ?>
        <dt class="col-sm-3">Mode</dt>
        <dd class="col-sm-9"><?= h($session->mode) ?></dd>
      <?php endif; ?>

      <?php if ($session->started_at): ?>
        <dt class="col-sm-3">Started</dt>
        <dd class="col-sm-9"><?= h($session->started_at->format('Y-m-d H:i')) ?> UTC</dd>
      <?php endif; ?>

      <?php if ($session->ended_at): ?>
        <dt class="col-sm-3">Ended</dt>
        <dd class="col-sm-9"><?= h($session->ended_at->format('Y-m-d H:i')) ?> UTC</dd>
      <?php endif; ?>

      <?php if ($session->notes): ?>
        <dt class="col-sm-3">Notes</dt>
        <dd class="col-sm-9"><?= nl2br(h($session->notes)) ?></dd>
      <?php endif; ?>
    </dl>
  </div>
</div>

<div class="d-flex flex-wrap gap-2">
  <?php if ($session->status === 'scheduled'): ?>
    <?= $this->Form->postLink('Start net', '/net-sessions/' . $session->id . '/start', [
        'class' => 'btn btn-success',
        'confirm' => 'Start "' . h($session->net_title) . '" now?',
    ]) ?>
  <?php endif; ?>

  <?php if ($session->status === 'live'): ?>
    <a class="btn btn-primary" href="/net-sessions/<?= $session->id ?>/cockpit">Cockpit</a>
    <?= $this->Form->postLink('End net', '/net-sessions/' . $session->id . '/end', [
        'class' => 'btn btn-warning',
        'confirm' => 'End "' . h($session->net_title) . '"?',
    ]) ?>
  <?php endif; ?>

  <?php if ($session->status === 'ended'): ?>
    <a class="btn btn-outline-secondary" href="/net-sessions/<?= $session->id ?>/analytics">Analytics</a>
    <a class="btn btn-outline-secondary" href="/net-sessions/<?= $session->id ?>/export.adi">Export ADIF</a>
    <a class="btn btn-outline-secondary" href="/net-sessions/<?= $session->id ?>/report.pdf">Export PDF</a>
  <?php endif; ?>

  <a class="btn btn-outline-secondary" href="/net-sessions/<?= $session->id ?>/edit">Edit</a>

  <?= $this->Form->postLink('Delete', '/net-sessions/' . $session->id . '/delete', [
      'class' => 'btn btn-outline-danger',
      'confirm' => 'Delete "' . h($session->net_title) . '"? This cannot be undone.',
  ]) ?>

  <a class="btn btn-secondary" href="/net-sessions">Back to list</a>
</div>
