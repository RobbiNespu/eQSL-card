<h1><?= h($title) ?></h1>
<p>Pick a template to design from, clone a public one, or start from scratch.</p>

<div class="d-flex align-items-center gap-2 mb-3">
  <a class="btn btn-primary" href="/templates/new">+ New template</a>
</div>

<div x-data="{ tab: 'mine' }">
  <!-- Tab buttons. Plain buttons styled as a segmented control via .tab-tabs / .tab-tab. -->
  <ul class="tab-tabs" role="tablist">
    <li><button type="button" role="tab" class="tab-tab" :class="tab==='mine'   && 'is-active'" @click="tab='mine'"   :aria-selected="tab==='mine'">My templates <span class="text-muted">(<?= $mine->count() ?>)</span></button></li>
    <li><button type="button" role="tab" class="tab-tab" :class="tab==='public' && 'is-active'" @click="tab='public'" :aria-selected="tab==='public'">Public <span class="text-muted">(<?= $public->count() ?>)</span></button></li>
    <li><button type="button" role="tab" class="tab-tab" :class="tab==='system' && 'is-active'" @click="tab='system'" :aria-selected="tab==='system'">System <span class="text-muted">(<?= $system->count() ?>)</span></button></li>
  </ul>

  <?php
  $renderGrid = function ($collection, $tabName, $emptyMessage, $showEdit, $showClone, $showDelete = false) { ?>
    <div role="tabpanel" x-show="tab === <?= "'" . h($tabName) . "'" ?>" x-cloak>
      <?php if ($collection->count() === 0): ?>
        <?= $this->element('ui/empty_state', ['message' => $emptyMessage]) ?>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($collection as $t): ?>
            <div class="col-md-3">
              <div class="card h-100">
                <?php if ($t->thumbnail_path): ?>
                  <img src="/<?= h($t->thumbnail_path) ?>" class="card-img-top" alt="<?= h($t->name) ?>" loading="lazy">
                <?php else: ?>
                  <div class="bg-light text-center text-muted small py-5">No thumbnail yet</div>
                <?php endif; ?>
                <div class="card-body p-3">
                  <h5 class="card-title h6 mb-1"><?= h($t->name) ?></h5>
                  <p class="card-text small mb-2"><?= h($t->description) ?></p>
                  <p class="mb-2">
                    <?php if ($t->is_system): ?>
                      <span class="badge bg-info">System</span>
                    <?php elseif ($t->is_public && $t->is_approved): ?>
                      <span class="badge bg-success">Public</span>
                    <?php elseif ($t->is_public): ?>
                      <span class="badge bg-warning">Pending review</span>
                    <?php endif; ?>
                  </p>
                  <div class="d-flex gap-1">
                    <?php if ($showEdit): ?>
                      <a class="btn btn-outline-primary btn-sm" href="/templates/<?= $t->id ?>/edit">Edit</a>
                    <?php endif; ?>
                    <?php if ($showClone): ?>
                      <?= $this->Form->postLink('Clone', '/templates/' . $t->id . '/clone', ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                    <?php if ($showDelete): ?>
                      <?= $this->Form->postLink('Delete', '/templates/' . $t->id . '/delete', [
                          'class' => 'btn btn-outline-danger btn-sm',
                          'confirm' => 'Delete this template? This cannot be undone.',
                      ]) ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php };
  // The first panel — "mine" — uses `:x-show="tab==='mine'"` with no x-cloak guard
  // for the initial pass, because Alpine hasn't hydrated yet at first render
  // and we want the right tab visible on initial paint. The other two are
  // x-cloak-hidden until Alpine kicks in.
  $renderGrid($mine, 'mine', 'No templates yet. Click "+ New template" to create one.', true, false, true);
  $renderGrid($public, 'public', 'No public templates yet.', false, true);
  $renderGrid($system, 'system', 'No system templates seeded.', false, true);
  ?>
</div>
