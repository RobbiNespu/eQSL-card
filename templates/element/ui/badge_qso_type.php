<?php
/**
 * NET badge — renders when the QSO is a net check-in, otherwise nothing.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Qso|object $qso
 */
?>
<?php if (($qso->qso_type ?? 'contact') === 'net'): ?>
  <?php $title = !empty($qso->net_title) ? 'Net check-in: ' . $qso->net_title : 'Net check-in'; ?>
  <span class="badge bg-info" title="<?= h($title) ?>">NET</span>
<?php endif; ?>
