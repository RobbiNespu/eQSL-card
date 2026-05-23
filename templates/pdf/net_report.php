<?php
/**
 * M6 T22 — Net session PDF report template.
 *
 * Self-contained HTML: inline styles only (no external assets).
 * dompdf has isRemoteEnabled=false and limited CSS support — use table
 * layout and block elements; avoid flexbox/grid.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\NetSession $session
 * @var array{checkins:int,unique:int,signal:array<int|string,int>} $stats
 * @var iterable<\App\Model\Entity\Qso> $checkins
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= h($session->net_title) ?> — Net Report</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 11px;
    color: #1a1a1a;
    background: #fff;
    padding: 18px 22px;
  }
  /* ---- Page header ---- */
  .report-header {
    border-bottom: 2px solid #2c5f2e;
    padding-bottom: 8px;
    margin-bottom: 12px;
  }
  .report-header h1 {
    font-size: 18px;
    color: #2c5f2e;
    margin-bottom: 2px;
  }
  .report-header .meta {
    font-size: 10px;
    color: #555;
  }
  .report-header .meta span {
    margin-right: 12px;
  }
  /* ---- Summary tiles ---- */
  .summary-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 14px;
  }
  .summary-table td {
    width: 33%;
    border: 1px solid #c8e6c9;
    background: #f1f8f1;
    padding: 8px 10px;
    text-align: center;
    vertical-align: middle;
  }
  .summary-table .tile-value {
    font-size: 22px;
    font-weight: bold;
    color: #2c5f2e;
    display: block;
  }
  .summary-table .tile-label {
    font-size: 9px;
    color: #555;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: block;
  }
  /* ---- Signal distribution ---- */
  .section-title {
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    color: #2c5f2e;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #c8e6c9;
    padding-bottom: 2px;
    margin-bottom: 6px;
    margin-top: 12px;
  }
  .signal-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
  }
  .signal-table td {
    padding: 2px 4px;
    font-size: 10px;
    vertical-align: middle;
  }
  .signal-label {
    width: 40px;
    text-align: right;
    color: #333;
    padding-right: 6px;
  }
  .signal-bar-cell {
    width: auto;
  }
  .signal-bar-outer {
    background: #e8f5e9;
    border: 1px solid #c8e6c9;
    width: 100%;
    height: 10px;
    position: relative;
  }
  .signal-bar-inner {
    background: #2c5f2e;
    height: 10px;
    display: block;
  }
  .signal-count {
    width: 30px;
    text-align: left;
    color: #555;
    padding-left: 4px;
  }
  /* ---- Check-in roster ---- */
  .roster-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
  }
  .roster-table th {
    background: #2c5f2e;
    color: #fff;
    font-size: 9px;
    font-weight: bold;
    text-align: left;
    padding: 4px 6px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
  .roster-table td {
    font-size: 10px;
    padding: 3px 6px;
    border-bottom: 1px solid #e8f5e9;
    vertical-align: top;
  }
  .roster-table tr:nth-child(even) td {
    background: #f9fdf9;
  }
  .badge-role {
    font-size: 8px;
    background: #e8f5e9;
    color: #2c5f2e;
    border: 1px solid #c8e6c9;
    padding: 1px 4px;
  }
  /* ---- Footer ---- */
  .report-footer {
    margin-top: 16px;
    border-top: 1px solid #c8e6c9;
    padding-top: 6px;
    font-size: 9px;
    color: #888;
    text-align: center;
  }
</style>
</head>
<body>

<!-- ================================================================
     Page header: net identity + session metadata
     ================================================================ -->
<div class="report-header">
  <h1><?= h($session->net_title) ?></h1>
  <div class="meta">
    <?php if ($session->net_organisation): ?>
      <span><?= h($session->net_organisation) ?></span>
    <?php endif; ?>
    <?php if ($session->band): ?>
      <span><?= h($session->band) ?></span>
    <?php endif; ?>
    <?php if ($session->mode): ?>
      <span><?= h($session->mode) ?></span>
    <?php endif; ?>
    <?php if ($session->frequency_mhz): ?>
      <span><?= h($session->frequency_mhz) ?> MHz</span>
    <?php endif; ?>
    <?php if ($session->started_at): ?>
      <span>Date: <?= h($session->started_at->format('Y-m-d')) ?></span>
    <?php endif; ?>
    <?php if ($session->started_at && $session->ended_at): ?>
      <span>Duration: <?= h($session->started_at->format('H:i')) ?>–<?= h($session->ended_at->format('H:i')) ?> UTC</span>
    <?php endif; ?>
  </div>
</div>

<!-- ================================================================
     Summary stat tiles
     ================================================================ -->
<table class="summary-table">
  <tr>
    <td>
      <span class="tile-value"><?= (int)$stats['checkins'] ?></span>
      <span class="tile-label">Total check-ins</span>
    </td>
    <td>
      <span class="tile-value"><?= (int)$stats['unique'] ?></span>
      <span class="tile-label">Unique callsigns</span>
    </td>
    <td>
      <span class="tile-value"><?= h(ucfirst($session->status)) ?></span>
      <span class="tile-label">Session status</span>
    </td>
  </tr>
</table>

<!-- ================================================================
     Signal distribution
     ================================================================ -->
<div class="section-title">Signal distribution</div>

<?php
$signal = $stats['signal'];
$maxCount = max(1, max(array_values($signal)));
?>
<table class="signal-table">
<?php foreach (range(1, 9) as $level): ?>
  <?php $count = (int)($signal[$level] ?? 0); ?>
  <?php $pct = (int)round($count / $maxCount * 100); ?>
  <tr>
    <td class="signal-label">S<?= $level ?></td>
    <td class="signal-bar-cell">
      <div class="signal-bar-outer">
        <span class="signal-bar-inner" style="width:<?= $pct ?>%;"></span>
      </div>
    </td>
    <td class="signal-count"><?= $count ?></td>
  </tr>
<?php endforeach; ?>
<?php $unknownCount = (int)($signal['unknown'] ?? 0); if ($unknownCount > 0): ?>
  <tr>
    <td class="signal-label" style="color:#999;">???</td>
    <td class="signal-bar-cell">
      <div class="signal-bar-outer" style="background:#f5f5f5;">
        <span class="signal-bar-inner" style="width:<?= (int)round($unknownCount / $maxCount * 100) ?>%;background:#999;"></span>
      </div>
    </td>
    <td class="signal-count" style="color:#999;"><?= $unknownCount ?></td>
  </tr>
<?php endif; ?>
</table>

<!-- ================================================================
     Check-in roster
     ================================================================ -->
<div class="section-title">Check-in roster</div>

<table class="roster-table">
  <thead>
    <tr>
      <th style="width:24px;">#</th>
      <th style="width:70px;">Callsign</th>
      <th style="width:90px;">Name</th>
      <th style="width:50px;">Grid</th>
      <th style="width:30px;">Sig</th>
      <th style="width:60px;">Role</th>
      <th style="width:60px;">Time (UTC)</th>
    </tr>
  </thead>
  <tbody>
    <?php $row = 0; ?>
    <?php foreach ($checkins as $qso): ?>
      <?php $row++; ?>
      <tr>
        <td><?= $row ?></td>
        <td><strong><?= h($qso->call_worked) ?></strong></td>
        <td><?= h($qso->operator_name ?? '') ?></td>
        <td><?= h($qso->grid_square ?? '') ?></td>
        <td><?php
          $sig = \App\Service\SignalReport::strength($qso->rst_received);
          echo $sig !== null ? 'S' . $sig : '—';
        ?></td>
        <td><?= $qso->net_role ? '<span class="badge-role">' . h($qso->net_role) . '</span>' : '' ?></td>
        <td><?= $qso->qso_datetime_utc ? h($qso->qso_datetime_utc->format('H:i')) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($row === 0): ?>
      <tr><td colspan="7" style="text-align:center;color:#999;padding:8px;">No check-ins recorded.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- ================================================================
     Footer
     ================================================================ -->
<div class="report-footer">
  Generated by eQSL-card &middot; Session #<?= (int)$session->id ?> &middot; <?= date('Y-m-d H:i') ?> UTC
</div>

</body>
</html>
