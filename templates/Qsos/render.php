<h1><?= h($title) ?></h1>

<p>
  QSO with <strong><?= h($qso->call_worked) ?></strong>
  on <?= h($qso->qso_datetime_utc?->format('Y-m-d H:i')) ?> UTC
  <?php if ($qso->band): ?>· <?= h($qso->band) ?><?php endif; ?>
  <?php if ($qso->mode): ?>· <?= h($qso->mode) ?><?php endif; ?>
</p>

<?= $this->Form->create(null, ['type' => 'file']) ?>

<h2>Template</h2>
<div class="row g-2 mb-3">
  <?php foreach ($templates as $t): ?>
    <div class="col-md-3">
      <label class="card p-2">
        <input type="radio" name="template_id" value="<?= (int)$t->id ?>" required>
        <span><strong><?= h($t->name) ?></strong></span>
        <span class="d-block small text-muted"><?= h($t->description) ?></span>
      </label>
    </div>
  <?php endforeach; ?>
</div>

<h2>Background</h2>
<?php if ($existingUploads->count() > 0): ?>
  <p>Pick one of your previous backgrounds, or upload a new image:</p>
  <div class="row g-2 mb-3">
    <?php foreach ($existingUploads as $u): ?>
      <div class="col-md-2">
        <label>
          <input type="radio" name="upload_id" value="<?= (int)$u->id ?>">
          <img src="/<?= h($u->storage_path) ?>" alt="" class="img-thumbnail" loading="lazy">
        </label>
      </div>
    <?php endforeach; ?>
    <div class="col-md-2">
      <label>
        <input type="radio" name="upload_id" value="0" checked>
        <span>Upload new</span>
      </label>
    </div>
  </div>
<?php else: ?>
  <input type="hidden" name="upload_id" value="0">
<?php endif; ?>

<input type="file" name="background_upload" accept="image/jpeg,image/png,image/webp" class="form-control mb-3">

<button class="btn btn-primary">Generate eQSL</button>
<a class="btn btn-link" href="/qsos/<?= (int)$qso->id ?>">Cancel</a>
<?= $this->Form->end() ?>
