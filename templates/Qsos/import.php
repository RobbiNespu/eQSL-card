<h1>Import logbook</h1>

<?php if ($stage === 'upload'): ?>
  <p>Upload an ADIF (.adi/.adif) or CSV (.csv) export from your logging program.</p>
  <?= $this->Form->create(null, ['type' => 'file']) ?>
  <div class="mb-3">
    <input type="file" name="adif_csv" accept=".adi,.adif,.csv" class="form-control" required>
  </div>
  <button class="btn btn-primary">Parse</button>
  <a class="btn btn-link" href="/qsos">Cancel</a>
  <?= $this->Form->end() ?>
<?php else: ?>
  <h2>Summary</h2>
  <ul>
    <li><strong><?= h($valid - $duplicates) ?></strong> new QSOs ready to import</li>
    <li><?= h($duplicates) ?> already in your logbook (will be skipped)</li>
    <li><?= h($invalid) ?> rows could not be parsed</li>
  </ul>

  <?php if (!empty($errors)): ?>
    <details class="mb-3">
      <summary>Parser warnings (<?= count($errors) ?>)</summary>
      <pre style="white-space: pre-wrap"><?php foreach ($errors as $e) {
          echo h($e) . "\n";
      } ?></pre>
    </details>
  <?php endif; ?>

  <?php if (!empty($sample)): ?>
    <h3>First few records</h3>
    <table class="table table-sm">
      <thead><tr><th>Call</th><th>UTC</th><th>Band</th><th>Mode</th></tr></thead>
      <tbody>
      <?php foreach ($sample as $s): ?>
        <tr>
          <td><?= h($s['call_worked']) ?></td>
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
  <button class="btn btn-primary" <?= $valid - $duplicates === 0 ? 'disabled' : '' ?>>Import these QSOs</button>
  <a class="btn btn-link" href="/qsos/import">Re-upload</a>
  <?= $this->Form->end() ?>
<?php endif; ?>
