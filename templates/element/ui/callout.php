<?php
/**
 * Inline callout box for help articles — Note / Tip / Warning.
 *
 * @var \App\View\AppView $this
 * @var string $body
 * @var string $variant 'note' (default) | 'tip' | 'warning'
 */
$variant = $variant ?? 'note';
if (!in_array($variant, ['note', 'tip', 'warning'], true)) {
    $variant = 'note';
}
$labels = ['note' => 'Note', 'tip' => 'Tip', 'warning' => 'Warning'];
?>
<aside class="callout callout-<?= h($variant) ?>" role="note">
  <strong class="callout__label"><?= h($labels[$variant]) ?></strong>
  <p><?= h($body ?? '') ?></p>
</aside>
