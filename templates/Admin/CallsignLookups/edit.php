<h1><?= h($title) ?></h1>
<p class="form-text">
  Edit a cached lookup row. Changes apply only to the auto-complete cache —
  the user's QSO history is independent and not modified.
</p>

<?= $this->Form->create($entity) ?>
<div class="row g-3" style="max-width: 720px;">
  <div class="col-md-6">
    <div class="field">
      <label class="form-label">Callsign</label>
      <input type="text" class="form-control" value="<?= h($entity->callsign) ?>" disabled>
      <p class="form-text small mb-0">
        The natural key — immutable. Delete + re-fetch if a callsign needs renaming.
      </p>
    </div>
  </div>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label">Source</label>
      <input type="text" class="form-control" value="<?= h($entity->source) ?>" disabled>
    </div>
  </div>

  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="name">Name</label>
      <input type="text" id="name" name="name" class="form-control"
             value="<?= h($entity->name ?? '') ?>"
             placeholder="Operator name">
    </div>
  </div>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="qth">QTH</label>
      <input type="text" id="qth" name="qth" class="form-control"
             value="<?= h($entity->qth ?? '') ?>"
             placeholder="City, region">
    </div>
  </div>

  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="country">Country</label>
      <input type="text" id="country" name="country" class="form-control"
             value="<?= h($entity->country ?? '') ?>"
             placeholder="e.g. Malaysia">
    </div>
  </div>
  <div class="col-md-3">
    <div class="field">
      <label class="form-label" for="grid_square">Grid square</label>
      <input type="text" id="grid_square" name="grid_square" class="form-control"
             value="<?= h($entity->grid_square ?? '') ?>"
             placeholder="e.g. OJ02wx" autocapitalize="off" spellcheck="false">
    </div>
  </div>
  <div class="col-md-3">
    <div class="field">
      <label class="form-label" for="license_class">License class</label>
      <input type="text" id="license_class" name="license_class" class="form-control"
             value="<?= h($entity->license_class ?? '') ?>"
             placeholder="e.g. Class A">
    </div>
  </div>

  <div class="col-12">
    <div class="field">
      <label class="form-label" for="source_url">Source URL</label>
      <input type="url" id="source_url" name="source_url" class="form-control"
             value="<?= h($entity->source_url ?? '') ?>"
             placeholder="https://…">
      <p class="form-text small mb-0">Optional. Where this row was scraped from, for audit.</p>
    </div>
  </div>
</div>

<div class="d-flex gap-2 mt-4">
  <button class="btn btn-primary">Save changes</button>
  <a class="btn btn-secondary" href="/admin/callsign-lookups">Cancel</a>
  <?= $this->Form->postLink('Delete', '/admin/callsign-lookups/' . $entity->id . '/delete', [
      'class'   => 'btn btn-outline-danger ms-auto',
      'confirm' => sprintf('Delete the cached entry for %s?', $entity->callsign),
  ]) ?>
</div>
<?= $this->Form->end() ?>

<hr class="mt-5">
<details>
  <summary class="small text-muted">Raw provider payload</summary>
  <?php if (!empty($entity->raw_payload)): ?>
    <pre class="small mt-2" style="white-space:pre-wrap; max-height:240px; overflow:auto;"><?= h($entity->raw_payload) ?></pre>
  <?php else: ?>
    <p class="form-text">No raw payload stored for this row.</p>
  <?php endif; ?>
  <p class="form-text small mt-2">
    Fetched <?= h($entity->fetched_at?->format('Y-m-d H:i:s')) ?>
    <?php if ($entity->expires_at): ?>
      · expires <?= h($entity->expires_at->format('Y-m-d')) ?>
    <?php endif; ?>
  </p>
</details>
