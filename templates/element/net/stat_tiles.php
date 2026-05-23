<?php
/**
 * Net cockpit — live stats right rail.
 *
 * The four stat tiles and two placeholder containers are wired entirely by
 * Task 12 / Task 19 / Task 20 JS. The PHP template only emits the shell;
 * the <span data-stat-value> elements start with "—" and are filled once the
 * polling client receives its first feed response.
 *
 * No PHP variables required.
 *
 * @var \App\View\AppView $this
 */
?>
<aside class="net-stats-rail" aria-label="Live net statistics">

  <div class="net-stat-tiles">

    <div class="net-stat-tile" data-stat="checkins">
      <span class="net-stat-tile__value" data-stat-value>&mdash;</span>
      <span class="net-stat-tile__label">Check-ins</span>
    </div>

    <div class="net-stat-tile" data-stat="unique">
      <span class="net-stat-tile__value" data-stat-value>&mdash;</span>
      <span class="net-stat-tile__label">Unique calls</span>
    </div>

    <div class="net-stat-tile" data-stat="new">
      <span class="net-stat-tile__value net-stat-tile__value--highlight" data-stat-value>&mdash;</span>
      <span class="net-stat-tile__label">New tonight</span>
    </div>

    <div class="net-stat-tile" data-stat="rate">
      <span class="net-stat-tile__value" data-stat-value>&mdash;</span>
      <span class="net-stat-tile__label">Check-ins/min</span>
    </div>

  </div>

  <div class="net-signal-chart-wrap card">
    <div class="card-body p-2">
      <p class="form-label small text-muted mb-1">Signal distribution (from RST)</p>
      <div data-signal-chart aria-label="Signal distribution chart">
        <p class="form-text text-center text-muted small py-2 mb-0">Loading signal chart&hellip;</p>
      </div>
    </div>
  </div>

</aside>
