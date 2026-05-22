<?php
/**
 * M6 T18 — Net session analytics page.
 *
 * Static review page (no live polling). Shows:
 *  - Session header: title, org/freq/band/mode, status
 *  - Summary stat tiles: total check-ins, unique callsigns
 *  - Signal distribution chart (rendered by net-charts.js)
 *  - Retention block: last-session rate, regulars list, per-session history
 *  - Map placeholder for T20 (Leaflet wired in next task)
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\NetSession $session
 * @var array{checkins:int,unique:int,signal:array} $stats
 * @var list<array{callsign:string,grid:string,lat:float,lon:float,signal:?int}> $mapPoints
 * @var array{sessions:list<array{id:int,unique:int}>,regulars:list<string>,retention:?float} $retention
 * @var string $title
 */
$this->assign('title', $title);
?>

<!-- ================================================================
     Analytics header
     ================================================================ -->
<div class="net-analytics-header mb-4">
  <div class="d-flex align-items-center gap-3 flex-wrap">
    <?php if ($session->status === 'live'): ?>
      <span class="badge bg-success">Live</span>
    <?php elseif ($session->status === 'scheduled'): ?>
      <span class="badge bg-secondary">Scheduled</span>
    <?php else: ?>
      <span class="badge bg-dark">Ended</span>
    <?php endif; ?>

    <h1 class="h3 mb-0"><?= h($session->net_title) ?></h1>
  </div>

  <p class="text-muted mt-1 mb-0">
    <?php $meta = array_filter([
        $session->net_organisation,
        $session->frequency_mhz ? h($session->frequency_mhz) . ' MHz' : null,
        $session->band,
        $session->mode,
    ]); ?>
    <?= implode(' &middot; ', array_map('h', array_filter([
        $session->net_organisation,
        $session->band,
        $session->mode,
    ]))) ?>
    <?php if ($session->frequency_mhz): ?>&middot; <?= h($session->frequency_mhz) ?> MHz<?php endif; ?>
  </p>
</div>

<!-- ================================================================
     Summary stat tiles
     ================================================================ -->
<div class="net-stat-tiles mb-4">
  <div class="net-stat-tile">
    <div class="net-stat-tile__value"><?= (int)$stats['checkins'] ?></div>
    <div class="net-stat-tile__label">Check-ins</div>
  </div>
  <div class="net-stat-tile">
    <div class="net-stat-tile__value"><?= (int)$stats['unique'] ?></div>
    <div class="net-stat-tile__label">Unique calls</div>
  </div>
</div>

<!-- ================================================================
     Signal distribution chart
     ================================================================ -->
<?= $this->element('net/signal_chart', ['signal' => $stats['signal']]) ?>

<!-- ================================================================
     Retention block
     ================================================================ -->
<div class="card mt-4 mb-4">
  <div class="card-header">Retention (last <?= \App\Service\NetMetrics::WINDOW ?> sessions)</div>
  <div class="card-body">

    <div class="mb-3">
      <strong>Session-to-session retention:</strong>
      <?php if ($retention['retention'] !== null): ?>
        <span class="badge bg-info"><?= round($retention['retention'] * 100, 1) ?>%</span>
      <?php else: ?>
        <span class="text-muted">&mdash; (need at least 2 ended sessions with same title)</span>
      <?php endif; ?>
    </div>

    <div class="mb-3">
      <strong>Regulars</strong>
      <small class="text-muted">(appeared in &ge;50% of recent sessions)</small>
      <?php if (!empty($retention['regulars'])): ?>
        <div class="d-flex flex-wrap gap-1 mt-1">
          <?php foreach ($retention['regulars'] as $call): ?>
            <span class="badge bg-secondary"><?= h($call) ?></span>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="text-muted mb-0 mt-1">No regulars yet.</p>
      <?php endif; ?>
    </div>

    <?php if (!empty($retention['sessions'])): ?>
      <div>
        <strong>Recent session history</strong>
        <table class="table table-sm mt-1">
          <thead>
            <tr><th>Session ID</th><th>Unique check-ins</th></tr>
          </thead>
          <tbody>
            <?php foreach ($retention['sessions'] as $s): ?>
              <tr>
                <td><?= (int)$s['id'] ?></td>
                <td><?= (int)$s['unique'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- ================================================================
     Map placeholder — T20 will wire net-map.js to consume [data-map-json]
     ================================================================ -->
<div class="net-map-wrap mb-4">
  <div class="label">Participant map</div>
  <div data-net-map></div>
  <script type="application/json" data-map-json><?= json_encode($mapPoints) ?></script>
</div>

<div class="mt-3">
  <a class="btn btn-outline-secondary" href="/net-sessions/<?= (int)$session->id ?>">Back to session</a>
</div>

<?php $this->append('script'); ?>
<script src="<?= $this->Url->build('/js/net-charts.js') ?>" type="module" defer></script>
<?php $this->end(); ?>
