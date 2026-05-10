<h1><?= h($title) ?></h1>

<div class="row">
  <div class="col-md-4">
    <img src="/<?= h($upload->storage_path) ?>" alt="" class="img-fluid rounded">
    <p class="text-muted small mt-2">
      <?= h($upload->original_filename) ?><br>
      <?= h($upload->width_px) ?>×<?= h($upload->height_px) ?> ·
      <?= h(round($upload->file_size_bytes / 1024)) ?> KB ·
      <?= h($upload->created_at?->format('Y-m-d H:i')) ?>
    </p>
  </div>
  <div class="col-md-8">
    <?= $this->Form->create(null) ?>
    <div class="mb-3">
      <label class="form-label">Author / photographer</label>
      <input type="text" name="author_name"
             value="<?= h($upload->author_name ?? '') ?>"
             class="form-control" placeholder="Leave blank if unknown">
    </div>
    <div class="mb-3">
      <label class="form-label">License</label>
      <select name="license" class="form-select">
        <?php $current = (string)($upload->license ?? 'unknown'); ?>
        <?php foreach (\App\Service\ImageLicense::options($current) as $code => $label): ?>
          <option value="<?= h($code) ?>"<?= $code === $current ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <button class="btn btn-primary">Save</button>
    <a class="btn btn-link" href="/uploads">Back to library</a>
    <?= $this->Form->end() ?>

    <hr>
    <p class="text-muted small">
      Edits apply to <em>future</em> renders only. Already-generated cards using this image
      keep the attribution they were rendered with — cards are immutable historical artefacts.
    </p>
  </div>
</div>
