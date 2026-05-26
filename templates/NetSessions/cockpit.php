<?php
/**
 * M6 T11 — NCS Live Cockpit.
 *
 * Server-rendered shell. The JS layer (net-cockpit.js / net-merge.js,
 * Task 12) attaches to the data-* hooks and adds live polling, entry
 * submission, and roster animation. The page is fully usable without JS:
 * the entry bar POSTs normally and the roster reflects server state on reload.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\NetSession $session
 * @var iterable<\App\Model\Entity\Qso> $checkins
 * @var string $title
 */
$this->assign('title', $title);
$isLive = $session->status === 'live';
?>

<!-- ============================================================
     Cockpit top bar — status, title, org/freq/band/mode, elapsed,
     public link, end-net button.
     ============================================================ -->
<div class="net-cockpit-bar" aria-label="Net cockpit controls">
  <div class="net-cockpit-bar__left">

    <?php if ($isLive): ?>
      <span class="net-status-live" aria-label="Net is live">
        <span class="net-status-live__dot" aria-hidden="true"></span>
        LIVE
      </span>
    <?php else: ?>
      <span class="badge bg-dark text-uppercase"><?= h($session->status) ?></span>
    <?php endif; ?>

    <strong class="net-cockpit-bar__title"><?= h($session->net_title) ?></strong>

    <span class="net-cockpit-bar__meta text-muted">
      <?php $parts = array_filter([
          $session->net_organisation,
          $session->frequency_mhz ? h($session->frequency_mhz) . ' MHz' : null,
          $session->band,
          $session->mode,
      ]); ?>
      · <?= implode(' · ', array_map('h', array_filter([
          $session->net_organisation,
          $session->band,
          $session->mode,
      ]))) ?>
      <?php if ($session->frequency_mhz): ?> · <?= h($session->frequency_mhz) ?> MHz<?php endif; ?>
    </span>

  </div>

  <div class="net-cockpit-bar__right">

    <?php if ($isLive && $session->started_at): ?>
      <span class="net-cockpit-bar__elapsed text-muted" aria-label="Elapsed time">
        &#9203;
        <span data-net-elapsed
              data-started="<?= h($session->started_at->format('c')) ?>">
          &mdash;
        </span>
      </span>
    <?php endif; ?>

    <a class="btn btn-sm btn-outline-secondary"
       href="/net/<?= h($session->public_slug) ?>"
       data-public-url="/net/<?= h($session->public_slug) ?>"
       target="_blank" rel="noopener"
       title="Open public listener view">
      &#128279; Public link
    </a>

    <?php if ($isLive): ?>
      <?= $this->Form->postLink(
          '&#9632; End net',
          '/net-sessions/' . (int)$session->id . '/end',
          [
              'class'   => 'btn btn-sm btn-danger',
              'confirm' => 'End "' . h($session->net_title) . '"? The net will close and no more check-ins can be added.',
              'escape'  => false,
          ]
      ) ?>
    <?php endif; ?>

  </div>
</div>

<!-- ============================================================
     Two-column cockpit body: main (entry bar + roster) + right rail
     ============================================================ -->
<div class="net-cockpit-body">

  <!-- Main column: entry bar + roster -->
  <div class="net-cockpit-main">

    <?php if ($isLive): ?>
      <?= $this->element('net/entry_bar', ['session' => $session]) ?>
    <?php else: ?>
      <div class="alert alert-secondary net-cockpit-ended-note" role="status">
        This net has ended — check-in logging is closed. View the roster and stats below.
      </div>
    <?php endif; ?>

    <?= $this->element('net/roster', ['checkins' => $checkins]) ?>

  </div>

  <!-- Right rail: stat tiles, signal chart placeholder, map placeholder -->
  <?= $this->element('net/stat_tiles') ?>

</div>

<?php $this->append('script'); ?>
<script>
  window.NET = {
    id:      <?= (int)$session->id ?>,
    feedUrl: '/net-sessions/<?= (int)$session->id ?>/checkins',
    postUrl: '/net-sessions/<?= (int)$session->id ?>/checkins',
    status:  <?= json_encode($session->status) ?>,
  };
</script>
<script src="<?= $this->Url->build('/js/net-merge.js') ?>" type="module" defer></script>
<script src="<?= $this->Url->build('/js/net-cockpit.js') ?>" type="module" defer></script>
<script src="<?= $this->Url->build('/js/net-poll.js') ?>" type="module" defer></script>
<script src="<?= $this->Url->build('/js/net-charts.js') ?>" type="module" defer></script>
<link rel="stylesheet" href="<?= $this->Url->build('/js/vendor/leaflet/leaflet.css') ?>">
<script src="<?= $this->Url->build('/js/vendor/leaflet/leaflet.js') ?>" defer></script>
<script src="<?= $this->Url->build('/js/net-map.js') ?>" defer></script>
<?php $this->end(); ?>
