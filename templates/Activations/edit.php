<?php
/*
 * M5 T14 — Activations edit form.
 *
 * Lets the operator rename, fix the grid square, or update notes
 * without ending the activation. The started_at / ended_at fields
 * are NOT exposed — those are operational signals owned by the
 * server-side start/end actions.
 */
?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Update the metadata for this activation.',
]) ?>

<?= $this->Form->create($activation, ['url' => '/activations/' . $activation->id . '/edit']) ?>
<div x-data="activationGpsHelper()">
  <div class="row g-2">
    <div class="col-md-5">
      <div class="field">
        <label class="form-label" for="act-code">Code <span class="req">*</span></label>
        <input type="text" id="act-code" name="code" class="form-control"
               value="<?= h($activation->code) ?>" required>
      </div>
    </div>
    <div class="col-md-7">
      <div class="field">
        <label class="form-label" for="act-name">Name <span class="req">*</span></label>
        <input type="text" id="act-name" name="name" class="form-control"
               value="<?= h($activation->name) ?>" required>
      </div>
    </div>
    <div class="col-md-4">
      <div class="field">
        <label class="form-label" for="act-grid">Grid <span class="form-label small">(optional)</span></label>
        <div class="input-group">
          <input type="text" id="act-grid" name="grid_square" class="form-control"
                 x-ref="gridInput"
                 value="<?= h($activation->grid_square ?? '') ?>"
                 maxlength="8" pattern="[A-Ra-r]{2}[0-9]{2}([A-Xa-x]{2})?">
          <button type="button" class="btn btn-outline-secondary"
                  @click="fillGridFromGps()"
                  :disabled="gpsState === 'asking'"
                  title="Use my current GPS location">
            <span x-show="gpsState !== 'asking'" aria-hidden="true">📍</span>
            <span x-show="gpsState === 'asking'" aria-hidden="true">⏳</span>
            <span class="visually-hidden">Use my location</span>
          </button>
        </div>
        <p class="form-text small mb-0" x-show="!gpsMessage">Maidenhead 4 or 6 char.</p>
        <p class="form-text small mb-0"
           x-show="gpsMessage" x-cloak
           :class="{ 'text-success': gpsState === 'ok', 'text-danger': gpsState === 'denied' || gpsState === 'error', 'text-muted': gpsState === 'asking' }"
           x-text="gpsMessage" role="status" aria-live="polite"></p>
      </div>
    </div>
    <div class="col-12">
      <div class="field">
        <label class="form-label" for="act-notes">Notes</label>
        <textarea id="act-notes" name="notes" class="form-control" rows="3"><?= h($activation->notes ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <?php if ($activation->getErrors()): ?>
    <div class="alert alert-danger mt-2">
      <ul class="mb-0">
        <?php foreach ($activation->getErrors() as $field => $fieldErrors): ?>
          <?php foreach ($fieldErrors as $msg): ?>
            <li><strong><?= h($field) ?>:</strong> <?= h($msg) ?></li>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="d-flex gap-2 form-actions-mobile mt-4">
    <button class="btn btn-primary">Save changes</button>
    <a class="btn btn-secondary" href="/activations">Cancel</a>
  </div>
</div>
<?= $this->Form->end() ?>

<hr>

<p class="form-text small">
  Started <?= h($activation->started_at?->format('Y-m-d H:i')) ?> UTC
  <?php if ($activation->ended_at): ?>
    · Ended <?= h($activation->ended_at->format('Y-m-d H:i')) ?> UTC
  <?php else: ?>
    · <strong class="text-success">Still active</strong>
  <?php endif; ?>
</p>
