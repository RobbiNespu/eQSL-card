<?php
/**
 * Card share-status badge — shared / private / share-revoked.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Card|object $card
 */
?>
<?php if (!empty($card->share_revoked_at)): ?>
  <span class="badge bg-secondary">Share revoked</span>
<?php elseif (!empty($card->share_slug)): ?>
  <span class="badge bg-success">Shared</span>
<?php else: ?>
  <span class="badge bg-light">Private</span>
<?php endif; ?>
