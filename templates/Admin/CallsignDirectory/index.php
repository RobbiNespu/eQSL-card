<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => "Admin-curated callsign directory. The QSO auto-complete chain checks this table first before reaching out to external providers — fast resolution and no upstream network call.",
]) ?>

<div class="row mb-4">
  <div class="col-md-7">
    <div class="card p-3">
      <h2 class="h5">Upload CSV</h2>
      <?= $this->Form->create(null, ['url' => '/admin/callsign-lookups/provider/local/upload', 'type' => 'file']) ?>
      <div class="mb-2">
        <label class="form-label small">CSV file</label>
        <input type="file" name="csv" class="form-control" accept=".csv,text/csv" required>
      </div>
      <div class="mb-2">
        <label class="form-label small">Source label <span class="text-muted">(optional)</span></label>
        <input type="text" name="source_label" class="form-control"
               placeholder='e.g. "MCMC 2026-Q1" or "MARTS roster"'
               maxlength="80">
      </div>
      <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-primary">Import</button>
        <a href="/files/templates/sample-callsign-directory.csv"
           download="sample-callsign-directory.csv"
           class="btn btn-outline-secondary btn-sm">
          &darr; Download sample CSV
        </a>
      </div>
      <p class="form-text small mt-2">
        Not sure of the format? Grab the sample above — it has the canonical
        headers and four example rows you can edit in any spreadsheet. Save as
        CSV and upload here.
      </p>
      <p class="form-text small mt-2">
        <strong>Headers accepted:</strong>
        <code>callsign</code> (required; aliases: <code>call</code>, <code>call_sign</code>, <code>indicatif</code>, <code>panggilan</code>),
        <code>name</code> (<code>operator</code>, <code>holder</code>, <code>nama</code>, …),
        <code>qth</code> (<code>location</code>, <code>city</code>, <code>alamat</code>, …),
        <code>country</code>, <code>grid</code> / <code>locator</code>, <code>class</code> / <code>license</code>.
        Headers are case-insensitive; a UTF-8 BOM (Excel exports) is stripped automatically.
      </p>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card p-3">
      <h2 class="h5">Directory status</h2>
      <p class="display-6"><?= h($total) ?></p>
      <p class="text-muted small">Callsigns indexed</p>
      <?php if ($total > 0): ?>
        <?= $this->Form->create(null, ['url' => '/admin/callsign-lookups/provider/local/clear']) ?>
        <button class="btn btn-outline-danger"
                onclick="return confirm('Delete all <?= h($total) ?> directory rows? This cannot be undone.')">
          Clear directory
        </button>
        <?= $this->Form->end() ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($total > 0): ?>
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
      <input type="search" name="q" value="<?= h($search) ?>" placeholder="Callsign substring" class="form-control">
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100">Search</button>
    </div>
  </form>

  <table class="table table-sm">
    <thead>
      <tr>
        <th>Callsign</th>
        <th>Name</th>
        <th>QTH</th>
        <th>Country</th>
        <th>Grid</th>
        <th>Class</th>
        <th>Source</th>
        <th>Imported</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= h($r->callsign) ?></strong></td>
          <td><?= h($r->name) ?></td>
          <td><?= h($r->qth) ?></td>
          <td><?= h($r->country) ?></td>
          <td><code><?= h($r->grid_square) ?></code></td>
          <td><?= h($r->license_class) ?></td>
          <td class="small text-muted"><?= h($r->source_label) ?></td>
          <td class="small text-muted"><?= h($r->imported_at?->format('Y-m-d')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <nav><?= $this->Paginator->numbers(['url' => ['?' => array_filter(['q' => $search ?: null])]]) ?></nav>
<?php endif; ?>
