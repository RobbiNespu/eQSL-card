<?php
/**
 * /help — landing page. Shows the full table of contents as a card grid
 * so visitors can browse categories at a glance, then click into the
 * sidebar from any article.
 *
 * @var \App\View\AppView $this
 * @var array $tree (from HelpController::index)
 */
?>
<?= $this->element('ui/page_header', [
    'title' => 'Help',
    'lede'  => 'Guides for using eQSL Card — from your first contact to running a busy logbook.',
]) ?>

<div class="row g-3">
  <?php foreach ($tree as $category => $data): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card h-100 card-body">
        <h2 class="h5 mb-2"><?= h($data['label']) ?></h2>
        <ul class="list-unstyled mb-0">
          <?php foreach ($data['pages'] as $slug => $label): ?>
            <li style="padding: 2px 0;">
              <a href="/help/<?= h($category) ?>/<?= h($slug) ?>"><?= h($label) ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endforeach; ?>
</div>
