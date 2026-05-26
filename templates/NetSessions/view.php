<?php
/*
 * M6 T9 — Net session detail view.
 *
 * Shows session metadata, status badge, and action buttons.
 * Cockpit / Analytics / Export are stubs for later tasks (T11/T17/T21/T22).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\NetSession $session
 * @var iterable<\App\Model\Entity\NetSessionLogger> $loggers
 * @var string $title
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

<!-- ============================================================
     Co-logger management (T15)
     Invite link lets another operator join as a co-logger.
     Owner can also add by user ID directly, or remove existing ones.
     ============================================================ -->
<?php if ($session->logger_token): ?>
<div class="card mt-4">
  <div class="card-header">Co-logger management</div>
  <div class="card-body">

    <p class="mb-2"><strong>Invite link</strong> — share this URL to let someone join as a co-logger:</p>
    <div class="input-group mb-3">
      <input type="text" class="form-control form-control-sm"
             value="<?= h($this->Url->build('/net-sessions/join/' . $session->logger_token, ['fullBase' => true])) ?>"
             readonly aria-label="Co-logger invite link">
    </div>
    <?= $this->Form->postLink('Regenerate invite link',
        '/net-sessions/' . (int)$session->id . '/rotate-token',
        ['confirm' => 'Replace the current invite link? Outstanding links will stop working.',
         'class' => 'btn btn-sm btn-outline-secondary mb-3']) ?>

    <?php if (!empty($loggers) && count($loggers) > 0): ?>
      <p class="mb-2"><strong>Current co-loggers</strong></p>
      <ul class="list-group mb-3">
        <?php foreach ($loggers as $logger): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= h($logger->user->callsign ?? $logger->user->name ?? ('User #' . $logger->user_id)) ?>
            <small class="text-muted"><?= h($logger->added_via) ?></small>
            <?= $this->Form->postLink(
                'Remove',
                '/net-sessions/' . (int)$session->id . '/loggers/' . (int)$logger->user_id,
                ['class' => 'btn btn-sm btn-outline-danger', 'confirm' => 'Remove this co-logger?']
            ) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="text-muted">No co-loggers yet.</p>
    <?php endif; ?>

    <p class="mb-2"><strong>Add co-logger by user ID</strong></p>
    <?= $this->Form->create(null, ['url' => '/net-sessions/' . (int)$session->id . '/loggers', 'class' => 'd-flex gap-2 align-items-end']) ?>
      <div>
        <label for="co-logger-uid" class="form-label form-label-sm">User ID</label>
        <input type="number" id="co-logger-uid" name="user_id" class="form-control form-control-sm" min="1" required>
      </div>
      <button type="submit" class="btn btn-sm btn-primary">Add</button>
    <?= $this->Form->end() ?>

  </div>
</div>
<?php endif; ?>
