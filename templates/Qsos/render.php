<?php
$callsignHtml = $this->element('ui/callsign', ['call' => $qso->call_worked]);
$detailLine = 'QSO with ' . $callsignHtml
    . ' on ' . h($qso->qso_datetime_utc?->format('Y-m-d H:i')) . ' UTC'
    . ($qso->band ? ' · ' . h($qso->band) : '')
    . ($qso->mode ? ' · ' . h($qso->mode) : '');
?>
<h1><?= h($title) ?></h1>
<p><?= $detailLine ?></p>

<?= $this->Form->create(null, ['type' => 'file']) ?>

<h2>Template</h2>
<div class="row g-3 mb-3">
  <?php foreach ($templates as $t): ?>
    <div class="col-md-3">
      <label class="radio-card">
        <input type="radio" name="template_id" value="<?= (int)$t->id ?>" required
               <?= (isset($defaultTemplateId) && (int)$t->id === (int)$defaultTemplateId) ? 'checked' : '' ?>>
        <span class="d-block fw-semibold"><?= h($t->name) ?></span>
        <span class="d-block small text-muted"><?= h($t->description) ?></span>
      </label>
    </div>
  <?php endforeach; ?>
</div>

<h2>Background</h2>
<?php
// Defaulting policy: if the user has any previous uploads, pre-select the
// most recent one so the path-of-least-resistance is "reuse what I had
// before". Only when the library is empty does "Site default" get the
// initial check. This avoids accidentally producing a fresh upload row
// every time the user expands the file-input details and attaches a
// new copy of essentially the same background.
$preselectUploadId = 0;
if ($existingUploads->count() > 0) {
    $preselectUploadId = (int)$existingUploads->first()->id;
}
?>
<p class="form-text mb-3">
  Reuse one of your previous uploads (most recent is pre-selected), pick
  "site default" for the global image, or attach a new image below. Identical
  files are auto-deduplicated by content hash — but pictures that look the
  same to you can still differ byte-for-byte (different EXIF, re-export, etc.)
  and produce a new row.
</p>
<div class="row g-3 mb-3">
  <div class="col-md-2">
    <label class="radio-card text-center">
      <input type="radio" name="upload_id" value="0" <?= $preselectUploadId === 0 ? 'checked' : '' ?>>
      <div class="small text-muted py-3">Site default</div>
    </label>
  </div>
  <?php foreach ($existingUploads as $u): ?>
    <div class="col-md-2">
      <label class="radio-card text-center" style="padding: var(--s-2);">
        <input type="radio" name="upload_id" value="<?= (int)$u->id ?>"
               <?= $preselectUploadId === (int)$u->id ? 'checked' : '' ?>>
        <img src="/<?= h($u->storage_path) ?>" alt="" class="img-fluid rounded" loading="lazy"
             style="aspect-ratio: 3/2; object-fit: cover;">
      </label>
    </div>
  <?php endforeach; ?>
</div>

<details class="mb-3">
  <summary>Or upload a new image instead</summary>
  <div class="field mt-2">
    <input type="file" name="background_upload" accept="image/jpeg,image/png,image/webp" class="form-control">
  </div>
  <div class="row g-3">
    <div class="col-md-6">
      <div class="field">
        <label class="form-label" for="background_author">Author / photographer</label>
        <input type="text" id="background_author" name="background_author"
               class="form-control" placeholder="Leave blank if unknown">
      </div>
    </div>
    <div class="col-md-6">
      <div class="field">
        <label class="form-label" for="background_license">License</label>
        <select id="background_license" name="background_license" class="form-select">
          <?php foreach (\App\Service\ImageLicense::options() as $code => $label): ?>
            <option value="<?= h($code) ?>"<?= $code === 'unknown' ? ' selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
  <p class="form-text mt-2">
    Selecting a file here overrides the radio choice above and saves the upload to your library for re-use.
    Author + license are stored on the upload row and shown as a credit line on every card that uses it.
  </p>
</details>

<div class="d-flex gap-2 mt-3">
  <button class="btn btn-primary">Generate eQSL</button>
  <a class="btn btn-secondary" href="/qsos/<?= (int)$qso->id ?>">Cancel</a>
</div>
<?= $this->Form->end() ?>
