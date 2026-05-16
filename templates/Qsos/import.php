<?php if ($stage === 'upload'): ?>
  <?= $this->element('ui/page_header', [
      'title' => 'Import logbook',
      'lede'  => "Upload an ADIF (.adi/.adif) or CSV (.csv) export from your logging program. We'll parse it locally, show you a summary, and only persist the rows you confirm.",
  ]) ?>

  <?= $this->Form->create(null, ['type' => 'file']) ?>
    <div class="field">
      <label class="form-label" for="adif_csv">Logbook file</label>
      <input type="file" id="adif_csv" name="adif_csv" accept=".adi,.adif,.csv" class="form-control" required>
    </div>
    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-primary">Parse</button>
      <a class="btn btn-secondary" href="/qsos">Cancel</a>
    </div>
  <?= $this->Form->end() ?>
<?php else: ?>
  <?= $this->element('ui/page_header', [
      'title' => 'Import logbook',
      'lede'  => 'Review what the parser found, then confirm the import.',
  ]) ?>

  <h2 class="h5">Summary</h2>
  <ul>
    <li><strong><?= h($valid - $duplicates) ?></strong> new QSOs ready to import</li>
    <li><?= h($duplicates) ?> already in your logbook (will be skipped)</li>
    <li><?= h($invalid) ?> rows could not be parsed</li>
  </ul>

  <?php if (!empty($errors)): ?>
    <details class="mb-3">
      <summary>Parser warnings (<?= count($errors) ?>)</summary>
      <pre style="white-space: pre-wrap"><?php foreach ($errors as $e) { echo h($e) . "\n"; } ?></pre>
    </details>
  <?php endif; ?>

  <?php if (!empty($sample)): ?>
    <h3 class="h5">First few records</h3>
    <table class="table table-sm">
      <thead><tr><th>Call</th><th>UTC</th><th>Band</th><th>Mode</th></tr></thead>
      <tbody>
      <?php foreach ($sample as $s): ?>
        <tr>
          <td><span class="callsign"><?= h($s['call_worked']) ?></span></td>
          <td><?= h($s['qso_datetime_utc']) ?></td>
          <td><?= h($s['band']) ?></td>
          <td><?= h($s['mode']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?= $this->Form->create(null) ?>
    <input type="hidden" name="confirm_token" value="<?= h($token) ?>">
    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-primary" <?= $valid - $duplicates === 0 ? 'disabled' : '' ?>>Import these QSOs</button>
      <a class="btn btn-secondary" href="/qsos/import">Re-upload</a>
    </div>
  <?= $this->Form->end() ?>
<?php endif; ?>
