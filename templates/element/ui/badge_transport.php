<?php
/**
 * Transport badge — renders for non-RF (internet-mediated) QSOs only.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Qso|object $qso
 */
$transport = $qso->transport ?? null;
?>
<?php if ($transport && \App\Service\Transport::isInternet($transport)): ?>
  <?php
  $label = \App\Service\Transport::label($transport);
  $meta = !empty($qso->transport_meta) ? ' · ' . $qso->transport_meta : '';
  ?>
  <span class="badge bg-secondary" title="<?= h($label . $meta) ?>">
    <?= h(strtoupper((string)$transport)) ?>
  </span>
<?php endif; ?>
