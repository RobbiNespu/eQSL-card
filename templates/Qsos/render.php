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

<p class="form-text mb-3">
  Backgrounds are part of the template — pick a template above whose look you like.
  Want a different background? <a href="/templates">Clone an existing template</a> and
  swap the background in the designer. Templates without a bound background fall back to the
  admin's site-default image at render time.
</p>

<div class="d-flex gap-2 mt-3">
  <button class="btn btn-primary">Generate eQSL</button>
  <a class="btn btn-secondary" href="/qsos/<?= (int)$qso->id ?>">Cancel</a>
</div>
<?= $this->Form->end() ?>
