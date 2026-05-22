<?php
/**
 * Net cockpit — roster table.
 *
 * Server-renders existing check-ins newest-first (the controller orders by
 * qso_datetime_utc DESC). The data-net-roster attribute and per-row
 * data-checkin-id attributes are hooks for Task 12 JS (net-merge.js) to
 * merge live updates without a page reload.
 *
 * Received variables:
 *   @var iterable<\App\Model\Entity\Qso> $checkins
 *   @var \App\View\AppView $this
 */
$rows = iterator_to_array($checkins);
$total = count($rows);
?>
<div class="net-roster-wrap">
  <table class="table net-roster" data-net-roster>
    <thead>
      <tr>
        <th class="net-roster__seq">#</th>
        <th class="net-roster__call">Callsign</th>
        <th class="net-roster__name">Name</th>
        <th class="net-roster__grid">Grid</th>
        <th class="net-roster__sig">Sig</th>
        <th class="net-roster__role">Role</th>
        <th class="net-roster__by">By</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($total === 0): ?>
        <tr data-roster-empty>
          <td colspan="7" class="text-center text-muted py-3">
            No check-ins yet. Use the entry bar above to log the first station.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $i => $q): ?>
          <?php
            $seq = $total - $i;
            $sig = \App\Service\SignalReport::strength($q->rst_received);
            $sigLabel = $sig !== null ? 'S' . $sig : '';
          ?>
          <tr data-checkin-id="<?= (int)$q->id ?>">
            <td class="net-roster__seq text-muted"><?= $seq ?></td>
            <td class="net-roster__call callsign"><strong><?= h($q->call_worked) ?></strong></td>
            <td class="net-roster__name"><?= h($q->operator_name ?: '—') ?></td>
            <td class="net-roster__grid"><?= h($q->grid_square ?: '—') ?></td>
            <td class="net-roster__sig">
              <?php if ($sigLabel !== ''): ?>
                <span class="net-sig"><?= h($sigLabel) ?></span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="net-roster__role"><?= h($q->net_role ?: '—') ?></td>
            <td class="net-roster__by text-muted small">
              <?= h($q->logged_by_user_id ?: '') ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  <p class="form-text small text-muted mt-1">
    Newest first · inline edit/delete on hover (Task 12) · live rows fade in as co-loggers add them
  </p>
</div>
