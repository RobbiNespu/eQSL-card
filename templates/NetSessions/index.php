<?php
/*
 * M6 T9 — Net sessions dashboard: Live / Upcoming / Recent sections.
 *
 * Layout:
 *   1. Live nets — running right now with End button.
 *   2. Upcoming (scheduled) — nets not yet started, with Start button.
 *   3. Recent — ended nets, read-only summary.
 *   4. Link to create a new net session.
 */
$this->assign('title', $title);
?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Manage your net control sessions. Start a net to open the cockpit and begin logging check-ins.',
]) ?>

<div class="mb-3">
  <a class="btn btn-primary" href="/net-sessions/new">New net session</a>
</div>

<!-- ============ Live nets ============ -->
<section class="net-sessions__live mb-4" aria-label="Live net sessions">
  <h2 class="h5">Live now</h2>
  <?php if ($live->count() === 0): ?>
    <p class="form-text">No nets running right now.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Net title</th>
          <th>Freq / Band / Mode</th>
          <th>Started</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($live as $s): ?>
          <tr>
            <td><strong><?= h($s->net_title) ?></strong> <span class="badge bg-success">Live</span></td>
            <td><?= h($s->frequency_mhz ?? '—') ?> MHz · <?= h($s->band ?? '—') ?> · <?= h($s->mode ?? '—') ?></td>
            <td><span class="small text-muted"><?= h($s->started_at?->format('Y-m-d H:i')) ?></span></td>
            <td>
              <a class="btn btn-sm btn-outline-secondary" href="/net-sessions/<?= $s->id ?>">View</a>
              <?= $this->Form->postLink('End', '/net-sessions/' . $s->id . '/end', [
                  'class' => 'btn btn-sm btn-outline-danger',
                  'confirm' => 'End "' . h($s->net_title) . '"?',
              ]) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<!-- ============ Upcoming (scheduled) ============ -->
<section class="net-sessions__upcoming mb-4" aria-label="Upcoming net sessions">
  <h2 class="h5">Upcoming</h2>
  <?php if ($upcoming->count() === 0): ?>
    <p class="form-text">No scheduled nets. <a href="/net-sessions/new">Create one.</a></p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Net title</th>
          <th>Freq / Band / Mode</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($upcoming as $s): ?>
          <tr>
            <td><?= h($s->net_title) ?> <span class="badge bg-secondary">Scheduled</span></td>
            <td><?= h($s->frequency_mhz ?? '—') ?> MHz · <?= h($s->band ?? '—') ?> · <?= h($s->mode ?? '—') ?></td>
            <td>
              <a class="btn btn-sm btn-outline-secondary" href="/net-sessions/<?= $s->id ?>">View</a>
              <a class="btn btn-sm btn-outline-secondary" href="/net-sessions/<?= $s->id ?>/edit">Edit</a>
              <?= $this->Form->postLink('Start', '/net-sessions/' . $s->id . '/start', [
                  'class' => 'btn btn-sm btn-success',
                  'confirm' => 'Start "' . h($s->net_title) . '" now?',
              ]) ?>
              <?= $this->Form->postLink('Delete', '/net-sessions/' . $s->id . '/delete', [
                  'class' => 'btn btn-sm btn-outline-danger',
                  'confirm' => 'Delete "' . h($s->net_title) . '"?',
              ]) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<!-- ============ Recent (ended) ============ -->
<section class="net-sessions__recent" aria-label="Recent net sessions">
  <h2 class="h5">Recent</h2>
  <?php if ($recent->count() === 0): ?>
    <p class="form-text">No ended nets yet.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Net title</th>
          <th>Started</th>
          <th>Ended</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $s): ?>
          <tr>
            <td><?= h($s->net_title) ?> <span class="badge bg-secondary">Ended</span></td>
            <td><span class="small text-muted"><?= h($s->started_at?->format('Y-m-d H:i')) ?></span></td>
            <td><span class="small text-muted"><?= h($s->ended_at?->format('Y-m-d H:i')) ?></span></td>
            <td>
              <a class="btn btn-sm btn-outline-secondary" href="/net-sessions/<?= $s->id ?>">View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
