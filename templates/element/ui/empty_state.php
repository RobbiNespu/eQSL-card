<?php
/**
 * Empty-state banner — info alert with an optional call-to-action link.
 *
 * @var \App\View\AppView $this
 * @var string $message
 * @var string|null $cta_url    optional
 * @var string|null $cta_label  optional (only used when $cta_url set)
 */
?>
<div class="alert alert-info" role="status">
  <?= h($message) ?>
  <?php if (!empty($cta_url)): ?>
    <a href="<?= h($cta_url) ?>"><?= h($cta_label ?? 'Go') ?> &rarr;</a>
  <?php endif; ?>
</div>
