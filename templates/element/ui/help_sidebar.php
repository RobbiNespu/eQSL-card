<?php
/**
 * Docs portal sidebar — renders the full category tree from
 * App\Service\HelpCatalog. The active link gets aria-current="page".
 *
 * @var \App\View\AppView $this
 * @var string|null $activeCategory
 * @var string|null $activeSlug
 */
use App\Service\HelpCatalog;
?>
<nav class="help-sidebar" aria-label="Documentation navigation">
  <details class="help-sidebar__mobile-toggle">
    <summary>
      <span class="help-sidebar__crumb"><?= h(HelpCatalog::categoryLabel($activeCategory ?? '') ?: 'Help') ?></span>
      <?php if ($activeSlug): ?>
        <span class="help-sidebar__crumb-sep">·</span>
        <span class="help-sidebar__crumb"><?= h(HelpCatalog::pageLabel($activeCategory, $activeSlug)) ?></span>
      <?php endif; ?>
    </summary>
    <ul class="help-sidebar__list">
      <?php foreach (HelpCatalog::TREE as $cat => $data): ?>
        <li class="help-sidebar__category">
          <span class="help-sidebar__category-label"><?= h($data['label']) ?></span>
          <ul>
            <?php foreach ($data['pages'] as $slug => $label): ?>
              <?php $isActive = $cat === $activeCategory && $slug === $activeSlug; ?>
              <li>
                <a class="help-sidebar__link<?= $isActive ? ' is-active' : '' ?>" href="/help/<?= h($cat) ?>/<?= h($slug) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
              </li>
            <?php endforeach; ?>
          </ul>
        </li>
      <?php endforeach; ?>
    </ul>
  </details>
</nav>
