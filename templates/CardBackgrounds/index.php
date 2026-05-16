<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => "Background images you've uploaded for QSL cards. Pick any of these when designing a template — bound backgrounds carry through to every card rendered from that template.",
]) ?>

<?php if ($backgrounds->count() === 0): ?>
  <?= $this->element('ui/empty_state', [
      'message'   => 'No backgrounds yet.',
      'cta_url'   => '/templates',
      'cta_label' => 'Upload one via the template designer',
  ]) ?>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($backgrounds as $bg): ?>
      <div class="col-md-3">
        <div class="card h-100">
          <img src="/<?= h($bg->storage_path) ?>" alt="" loading="lazy" class="card-img-top" style="height: 160px; object-fit: cover">
          <div class="card-body p-2">
            <p class="small mb-1">
              <strong><?= h($bg->author_name ?: 'unknown source') ?></strong>
              <span class="text-muted">— <?= h(\App\Service\ImageLicense::label($bg->license)) ?></span>
            </p>
            <p class="small text-muted mb-2">
              <?= h($bg->width_px) ?>×<?= h($bg->height_px) ?> ·
              <?= h(round($bg->file_size_bytes / 1024)) ?> KB ·
              <?= h($bg->created_at?->format('Y-m-d')) ?>
            </p>
            <?php $usedByThis = $usedBy[(int)$bg->id] ?? []; ?>
            <?php if (!empty($usedByThis)): ?>
              <p class="small mb-2">
                <span class="text-muted">Used by:</span>
                <?php foreach ($usedByThis as $i => $tpl): ?>
                  <?php if ($i > 0): ?>, <?php endif; ?>
                  <a href="/templates/<?= h($tpl->id) ?>/edit"><?= h($tpl->name) ?></a>
                <?php endforeach; ?>
              </p>
            <?php else: ?>
              <p class="small text-muted mb-2"><em>Not bound to any template.</em></p>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-primary" href="/card-backgrounds/<?= $bg->id ?>/edit">Edit</a>
            <?= $this->Form->postLink('Delete', '/card-backgrounds/' . $bg->id . '/delete', [
                'class' => 'btn btn-sm btn-outline-danger',
                'confirm' => 'Soft-delete this background? Existing cards will keep rendering, but it will disappear from your library.',
            ]) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <nav class="mt-3"><?= $this->Paginator->numbers() ?></nav>
<?php endif; ?>
