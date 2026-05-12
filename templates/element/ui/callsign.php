<?php
/**
 * Renders a callsign in the mono typeface used everywhere callsigns appear.
 *
 * @var \App\View\AppView $this
 * @var string|null $call
 */
?>
<span class="callsign"><?= h($call ?: '—') ?></span>
