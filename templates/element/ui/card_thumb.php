<?php
/**
 * Card preview thumbnail with the thumb path fallback to the full image.
 * Always lazy-loaded.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Card|object $card
 * @var string $alt
 */
$thumbPath = \App\Service\CardRenderer::thumbPathFor($card->png_path);
$previewSrc = is_file(WWW_ROOT . $thumbPath) ? $thumbPath : $card->png_path;
?>
<img src="/<?= h($previewSrc) ?>"
     class="card-img-top"
     alt="<?= h($alt ?? 'eQSL card preview') ?>"
     loading="lazy">
