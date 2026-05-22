<?php
/*
 * M6 T9 — Create a new net session.
 * POST target: /net-sessions/new  → NetSessionsController::add()
 */
$this->assign('title', $title);
?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Schedule a new net session. You can start it immediately from the dashboard or come back later.',
]) ?>

<?= $this->Form->create($session, ['url' => '/net-sessions/new']) ?>
  <div class="row g-3">
    <div class="col-md-8">
      <div class="field">
        <label class="form-label" for="net-title">Net title <span class="req">*</span></label>
        <input type="text" id="net-title" name="net_title" class="form-control"
               value="<?= h($session->net_title ?? '') ?>"
               placeholder="e.g. MARTS Daily Net" required>
      </div>
    </div>
    <div class="col-md-4">
      <div class="field">
        <label class="form-label" for="net-org">Organisation</label>
        <input type="text" id="net-org" name="net_organisation" class="form-control"
               value="<?= h($session->net_organisation ?? '') ?>"
               placeholder="e.g. MARTS">
      </div>
    </div>
    <div class="col-md-4">
      <div class="field">
        <label class="form-label" for="net-freq">Frequency (MHz)</label>
        <input type="text" id="net-freq" name="frequency_mhz" class="form-control"
               value="<?= h($session->frequency_mhz ?? '') ?>"
               placeholder="e.g. 7.110">
      </div>
    </div>
    <div class="col-md-4">
      <div class="field">
        <label class="form-label" for="net-band">Band</label>
        <input type="text" id="net-band" name="band" class="form-control"
               value="<?= h($session->band ?? '') ?>"
               placeholder="e.g. 40m">
      </div>
    </div>
    <div class="col-md-4">
      <div class="field">
        <label class="form-label" for="net-mode">Mode</label>
        <input type="text" id="net-mode" name="mode" class="form-control"
               value="<?= h($session->mode ?? '') ?>"
               placeholder="e.g. SSB">
      </div>
    </div>
    <div class="col-12">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="net-public" name="is_public" value="1"
               <?= ($session->is_public ?? false) ? 'checked' : '' ?>>
        <label class="form-check-label" for="net-public">Public net (visible via shareable link)</label>
      </div>
    </div>
    <div class="col-12">
      <div class="field">
        <label class="form-label" for="net-notes">Notes</label>
        <textarea id="net-notes" name="notes" class="form-control" rows="3"
                  placeholder="Anything the co-loggers should know"><?= h($session->notes ?? '') ?></textarea>
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
    <button class="btn btn-primary">Create net session</button>
    <a class="btn btn-secondary" href="/net-sessions">Cancel</a>
  </div>
<?= $this->Form->end() ?>
