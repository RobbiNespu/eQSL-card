<?php
/**
 * Page header — H1 + optional lede paragraph. Triggers the
 * h1:first-child + p lede styling from theme.css.
 *
 * @var \App\View\AppView $this
 * @var string $title
 * @var string|null $lede  optional
 * @var bool $escape_title default true — set false to allow inline HTML
 *                         (e.g. for the QSO view "QSO with <span class=callsign>...")
 */
$escape = $escape_title ?? true;
?>
<h1><?= $escape ? h($title) : $title ?></h1>
<?php if (!empty($lede)): ?>
  <p><?= h($lede) ?></p>
<?php endif; ?>
