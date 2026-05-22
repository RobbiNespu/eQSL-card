<?php
/*
 * M6 T9 — Edit an existing net session.
 * Operational fields (status, started_at, ended_at, public_slug, logger_token)
 * are server-controlled and intentionally excluded from this form.
 */
$this->assign('title', $title);
?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Update the metadata for this net session.',
]) ?>

<?= $this->Form->create($session, ['url' => '/net-sessions/' . $session->id . '/edit']) ?>
  <div class="row g-3">
    <div class="col-md-8">
      <div class="field">
        <label class="form-label" for="net-title">Net title <span class="req">*</span></label>
        <input type="text" id="net-title" name="net_title" class="form-control"
               value="<?= h($session->net_title) ?>" required>
      </div>
    </div>
    <div class="col-md-4">
      <div class="field">
        <label class="form-label" for="net-org">Organisation</label>
        <input type="text" id="net-org" name="net_organisation" class="form-control"
               value="<?= h($session->net_organisation ?? '') ?>">
      </div>
    </div>
    <div class="col-md-4">
      <div class="field">
        <label class="form-label" for="net-freq">Frequency (MHz)</label>
        <input type="text" id="net-freq" name="frequency_mhz" class="form-control"
               value="<?= h($session->frequency_mhz ?? '') ?>">
      </div>
    </div>
    <div class="col-md-4">
      <div class="field">
        <label class="form-label" for="net-band">Band</label>
        <input type="text" id="net-band" name="band" class="form-control"
               value="<?= h($session->band ?? '') ?>">
      </div>
    </div>
    <div class="col-md-4">
      <div class="field">
        <label class="form-label" for="net-mode">Mode</label>
        <input type="text" id="net-mode" name="mode" class="form-control"
               value="<?= h($session->mode ?? '') ?>">
      </div>
    </div>
    <div class="col-12">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="net-public" name="is_public" value="1"
               <?= $session->is_public ? 'checked' : '' ?>>
        <label class="form-check-label" for="net-public">Public net (visible via shareable link)</label>
      </div>
    </div>
    <div class="col-12">
      <div class="field">
        <label class="form-label" for="net-notes">Notes</label>
        <textarea id="net-notes" name="notes" class="form-control" rows="3"><?= h($session->notes ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <?php if ($session->getErrors()): ?>
    <div class="alert alert-danger mt-3">
      <ul class="mb-0">
        <?php foreach ($session->getErrors() as $field => $fieldErrors): ?>
          <?php foreach ($fieldErrors as $msg): ?>
            <li><strong><?= h($field) ?>:</strong> <?= h($msg) ?></li>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="d-flex gap-2 form-actions-mobile mt-4">
    <button class="btn btn-primary">Save changes</button>
    <a class="btn btn-secondary" href="/net-sessions/<?= $session->id ?>">Cancel</a>
  </div>
<?= $this->Form->end() ?>

<hr>

<p class="form-text small">
  Status: <strong><?= h($session->status) ?></strong>
  <?php if ($session->started_at): ?>
    · Started <?= h($session->started_at->format('Y-m-d H:i')) ?> UTC
  <?php endif; ?>
  <?php if ($session->ended_at): ?>
    · Ended <?= h($session->ended_at->format('Y-m-d H:i')) ?> UTC
  <?php endif; ?>
</p>
