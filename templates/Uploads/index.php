<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => "Background images you've uploaded. Pick any of these when you render an eQSL to skip re-uploading.",
]) ?>

<?php if ($uploads->count() === 0): ?>
  <?= $this->element('ui/empty_state', [
      'message'   => 'No backgrounds yet.',
      'cta_url'   => '/qsos',
      'cta_label' => 'Upload one via the render-from-QSO flow',
  ]) ?>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($uploads as $u): ?>
      <div class="col-md-3">
        <div class="card h-100">
          <img src="/<?= h($u->storage_path) ?>" alt="" loading="lazy" class="card-img-top" style="height: 160px; object-fit: cover">
          <div class="card-body p-2">
            <p class="small mb-1">
              <strong><?= h($u->author_name ?: 'unknown source') ?></strong>
              <span class="text-muted">— <?= h(\App\Service\ImageLicense::label($u->license)) ?></span>
            </p>
            <p class="small text-muted mb-2">
              <?= h($u->width_px) ?>×<?= h($u->height_px) ?> ·
              <?= h(round($u->file_size_bytes / 1024)) ?> KB ·
              <?= h($u->created_at?->format('Y-m-d')) ?>
            </p>
            <a class="btn btn-sm btn-outline-primary" href="/uploads/<?= $u->id ?>/edit">Edit</a>
            <?= $this->Form->postLink('Delete', '/uploads/' . $u->id . '/delete', [
                'class' => 'btn btn-sm btn-outline-danger',
                'confirm' => 'Soft-delete this upload? Existing cards will keep rendering, but it will disappear from your library.',
            ]) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <nav class="mt-3"><?= $this->Paginator->numbers() ?></nav>
<?php endif; ?>
