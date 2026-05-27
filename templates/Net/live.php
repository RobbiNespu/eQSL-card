<?php
/**
 * M6 T16 — Public read-only live net view.
 *
 * No authentication required. No entry bar, no edit/delete controls.
 * The "By" column is intentionally blank — logged_by_user_id is never
 * sent to the public feed. JS fills the roster via net-live.js.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\NetSession $session
 * @var string $title
 */
$this->assign('title', $title);
$isLive = $session->status === 'live';
?>

<div class="net-cockpit-bar" aria-label="Net status">
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
      · <?= implode(' · ', array_map('h', array_filter([
          $session->net_organisation,
          $session->band,
          $session->mode,
      ]))) ?>
      <?php if ($session->frequency_mhz): ?> · <?= h($session->frequency_mhz) ?> MHz<?php endif; ?>
    </span>

  </div>
</div>

<div class="net-cockpit-layout">
  <div class="net-cockpit-main">
    <?= $this->element('net/roster', ['checkins' => new \ArrayObject()]) ?>
  </div>
  <div class="net-cockpit-rail">
    <?= $this->element('net/stat_tiles') ?>
  </div>
</div>

<?php $this->append('script'); ?>
<script>window.NET = { feedUrl: '/net/<?= h($session->public_slug) ?>/live', status: <?= json_encode($session->status) ?> };</script>
<script src="<?= $this->Url->build('/js/net-merge.js') ?>" type="module" defer></script>
<script src="<?= $this->Url->build('/js/net-live.js') ?>" type="module" defer></script>
<script src="<?= $this->Url->build('/js/net-charts.js') ?>" type="module" defer></script>
<link rel="stylesheet" href="<?= $this->Url->build('/js/vendor/leaflet/leaflet.css') ?>">
<script src="<?= $this->Url->build('/js/vendor/leaflet/leaflet.js') ?>" defer></script>
<script src="<?= $this->Url->build('/js/net-map.js') ?>" defer></script>
<?php $this->end(); ?>
