<?php
/**
 * Docs portal page wrapper. Article templates extend this via
 * <?php $this->extend('/Help/view'); ?>.
 *
 * @var \App\View\AppView $this
 * @var string $category
 * @var string $slug
 * @var bool $useMermaid (optional; opt-in to Mermaid CDN load)
 */
$useMermaid = $useMermaid ?? false;
$neighbours = \App\Service\HelpCatalog::neighbours($category, $slug);
?>
<div class="help-shell">
  <?= $this->element('ui/help_sidebar', [
      'activeCategory' => $category,
      'activeSlug' => $slug,
  ]) ?>

  <article class="help-content">
    <?= $this->fetch('content') ?>

    <nav class="help-prev-next" aria-label="Previous and next">
      <?php if (!empty($neighbours['prev'])): ?>
        <a href="/help/<?= h($neighbours['prev']['category']) ?>/<?= h($neighbours['prev']['slug']) ?>">
          <span class="help-prev-next__label">&larr; Previous</span>
          <?= h(\App\Service\HelpCatalog::pageLabel($neighbours['prev']['category'], $neighbours['prev']['slug'])) ?>
        </a>
      <?php else: ?>
        <span></span>
      <?php endif; ?>

      <?php if (!empty($neighbours['next'])): ?>
        <a class="help-prev-next__next"
           href="/help/<?= h($neighbours['next']['category']) ?>/<?= h($neighbours['next']['slug']) ?>">
          <span class="help-prev-next__label">Next &rarr;</span>
          <?= h(\App\Service\HelpCatalog::pageLabel($neighbours['next']['category'], $neighbours['next']['slug'])) ?>
        </a>
      <?php else: ?>
        <span></span>
      <?php endif; ?>
    </nav>
  </article>
</div>

<?php if ($useMermaid): ?>
  <?php $this->start('script'); ?>
  <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
  <script>
    mermaid.initialize({
      startOnLoad: true,
      theme: document.documentElement.getAttribute('data-theme') === 'eqsl-dark' ? 'dark' : 'default'
    });
  </script>
  <?php $this->end(); ?>
<?php endif; ?>
