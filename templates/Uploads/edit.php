<h1><?= h($title) ?></h1>
<p>Update the author / license shown as the credit line on every future card that uses this image.</p>

<div class="row g-4">
  <div class="col-md-4">
    <img src="/<?= h($upload->storage_path) ?>" alt="" class="img-fluid rounded" loading="lazy">
    <p class="form-text mt-2">
      <?= h($upload->original_filename) ?><br>
      <?= h($upload->width_px) ?>×<?= h($upload->height_px) ?> ·
      <?= h(round($upload->file_size_bytes / 1024)) ?> KB ·
      <?= h($upload->created_at?->format('Y-m-d H:i')) ?>
    </p>
  </div>
  <div class="col-md-8">
    <?= $this->Form->create(null) ?>
      <div class="field">
        <label class="form-label" for="author_name">Author / photographer</label>
        <input type="text" id="author_name" name="author_name"
               value="<?= h($upload->author_name ?? '') ?>"
               class="form-control" placeholder="Leave blank if unknown">
      </div>
      <div class="field">
        <label class="form-label" for="license">License</label>
        <select id="license" name="license" class="form-select">
          <?php $current = (string)($upload->license ?? 'unknown'); ?>
          <?php foreach (\App\Service\ImageLicense::options($current) as $code => $label): ?>
            <option value="<?= h($code) ?>"<?= $code === $current ? ' selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary">Save</button>
        <a class="btn btn-secondary" href="/uploads">Back to library</a>
      </div>
    <?= $this->Form->end() ?>

    <hr>
    <p class="form-text">
      Edits apply to <em>future</em> renders only. Already-generated cards using this image
      keep the attribution they were rendered with — cards are immutable historical artefacts.
    </p>
  </div>
</div>
