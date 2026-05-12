<?php
/**
 * Form action button row — primary submit, optional secondary, optional cancel.
 * Renders inside an existing <form>; just emits the buttons.
 *
 * @var \App\View\AppView $this
 * @var string|null $primary_label   default "Save"
 * @var string|null $secondary_label optional
 * @var string|null $secondary_url   optional
 * @var string|null $cancel_label    default "Cancel"
 * @var string|null $cancel_url      optional; if set, renders the cancel link
 */
$primaryLabel   = $primary_label   ?? 'Save';
$cancelLabel    = $cancel_label    ?? 'Cancel';
$secondaryLabel = $secondary_label ?? null;
$secondaryUrl   = $secondary_url   ?? null;
$cancelUrl      = $cancel_url      ?? null;
?>
<div class="d-flex gap-2 mt-4 flex-wrap">
  <button class="btn btn-primary"><?= h($primaryLabel) ?></button>
  <?php if ($secondaryLabel && $secondaryUrl): ?>
    <a class="btn btn-outline-primary" href="<?= h($secondaryUrl) ?>"><?= h($secondaryLabel) ?></a>
  <?php endif; ?>
  <?php if ($cancelUrl): ?>
    <a class="btn btn-secondary" href="<?= h($cancelUrl) ?>"><?= h($cancelLabel) ?></a>
  <?php endif; ?>
</div>
