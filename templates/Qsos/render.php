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
<p class="text-muted small">Pick "site default" to use the global default image, reuse one of your previous uploads, or attach a new image below.</p>
<div class="row g-2 mb-3">
  <div class="col-md-2">
    <label class="d-block text-center">
      <input type="radio" name="upload_id" value="0" checked>
      <div class="border rounded p-3 small text-muted">Site default</div>
    </label>
  </div>
  <?php foreach ($existingUploads as $u): ?>
    <div class="col-md-2">
      <label class="d-block text-center">
        <input type="radio" name="upload_id" value="<?= (int)$u->id ?>">
        <img src="/<?= h($u->storage_path) ?>" alt="" class="img-thumbnail" loading="lazy">
      </label>
    </div>
  <?php endforeach; ?>
</div>

<details class="mb-3">
  <summary class="small">Or upload a new image instead</summary>
  <input type="file" name="background_upload" accept="image/jpeg,image/png,image/webp" class="form-control mt-2">
  <div class="row g-2 mt-2">
    <div class="col-md-6">
      <label class="form-label small">Author / photographer</label>
      <input type="text" name="background_author" class="form-control" placeholder="Leave blank if unknown">
    </div>
    <div class="col-md-6">
      <label class="form-label small">License</label>
      <select name="background_license" class="form-select">
        <?php foreach (\App\Service\ImageLicense::options() as $code => $label): ?>
          <option value="<?= h($code) ?>"<?= $code === 'unknown' ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <p class="form-text small mt-2">
    Selecting a file here overrides the radio choice above and saves the upload to your library for re-use.
    Author + license are stored on the upload row and shown as a credit line on every card that uses it.
  </p>
</details>

<button class="btn btn-primary">Generate eQSL</button>
<a class="btn btn-link" href="/qsos/<?= (int)$qso->id ?>">Cancel</a>
<?= $this->Form->end() ?>
