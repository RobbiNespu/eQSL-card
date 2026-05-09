<h1><?= h($title) ?></h1>

<p><a class="btn btn-primary" href="/templates/new">+ New template</a></p>

<ul class="nav nav-tabs mb-3" id="tplTabs" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mine">My templates (<?= $mine->count() ?>)</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#public">Public (<?= $public->count() ?>)</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#system">System (<?= $system->count() ?>)</button></li>
</ul>

<div class="tab-content">
  <?php
  $renderGrid = function ($collection, $tab, $emptyMessage, $showEdit, $showClone) use ($mine) { ?>
    <div class="tab-pane <?= $tab === 'mine' ? 'show active' : '' ?>" id="<?= h($tab) ?>">
      <?php if ($collection->count() === 0): ?>
        <div class="alert alert-info"><?= h($emptyMessage) ?></div>
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
                <div class="card-body p-2">
                  <h5 class="card-title h6"><?= h($t->name) ?></h5>
                  <p class="small text-muted mb-1"><?= h($t->description) ?></p>
                  <p>
                    <?php if ($t->is_system): ?>
                      <span class="badge bg-info">System</span>
                    <?php elseif ($t->is_public && $t->is_approved): ?>
                      <span class="badge bg-success">Public</span>
                    <?php elseif ($t->is_public): ?>
                      <span class="badge bg-warning text-dark">Pending review</span>
                    <?php endif; ?>
                  </p>
                  <div>
                    <?php if ($showEdit): ?>
                      <a class="btn btn-outline-primary btn-sm" href="/templates/<?= $t->id ?>/edit">Edit</a>
                    <?php endif; ?>
                    <?php if ($showClone): ?>
                      <?= $this->Form->postLink('Clone', '/templates/' . $t->id . '/clone', ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
  $renderGrid($mine, 'mine', 'No templates yet. Click "+ New template" to create one.', true, false);
  $renderGrid($public, 'public', 'No public templates yet.', false, true);
  $renderGrid($system, 'system', 'No system templates seeded.', false, true);
  ?>
</div>
