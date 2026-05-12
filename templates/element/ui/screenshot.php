<?php
/**
 * Screenshot wrapper with optional light/dark variants.
 *
 * Light image always renders. If $darkSrc is provided, a second <img>
 * renders alongside and CSS selectors hide whichever doesn't match
 * the current [data-theme]. Both images are lazy-loaded.
 *
 * @var \App\View\AppView $this
 * @var string $src         path under /files/help/, light variant
 * @var string $alt         required alt text
 * @var string|null $darkSrc optional dark variant path
 * @var string|null $caption optional caption shown below the image
 */
?>
<figure class="screenshot<?= !empty($darkSrc) ? ' has-dark' : '' ?>">
  <img class="screenshot__img screenshot__img--light"
       src="<?= h($src) ?>"
       alt="<?= h($alt) ?>"
       loading="lazy">
  <?php if (!empty($darkSrc)): ?>
    <img class="screenshot__img screenshot__img--dark"
         src="<?= h($darkSrc) ?>"
         alt="<?= h($alt) ?>"
         loading="lazy">
  <?php endif; ?>
  <?php if (!empty($caption)): ?>
    <figcaption><?= h($caption) ?></figcaption>
  <?php endif; ?>
</figure>
