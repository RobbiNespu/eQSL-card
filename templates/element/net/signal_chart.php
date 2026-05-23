<?php
/**
 * Net signal distribution chart element.
 *
 * Emits the chart container + inline JSON that DOMContentLoaded in
 * net-charts.js reads and renders into [data-signal-chart].
 *
 * @var array $signal  Signal distribution array from NetMetrics::sessionStats()['signal']
 */
?>
<div class="net-signal-chart">
  <div class="label">Signal distribution</div>
  <div data-signal-chart></div>
  <script type="application/json" data-signal-json><?= json_encode($signal ?? []) ?></script>
</div>
