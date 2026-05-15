<h1><?= h($title) ?></h1>
<p class="form-text">
  Manage the cache the QSO auto-complete writes when a provider returns useful data.
  Editing or deleting an entry here only affects what the auto-complete suggests next time —
  your QSO history is never touched.
</p>

<!-- ── Source-of-data settings ──────────────────────────────────────── -->
<h2 class="h5 mt-4">Source of data</h2>
<?= $this->Form->create(null, ['url' => '/admin/callsign-lookups/settings']) ?>
  <div class="form-check mb-2">
    <input type="hidden" name="callsign_lookup_enabled" value="0">
    <input type="checkbox" class="form-check-input" id="callsignLookupEnabled"
           name="callsign_lookup_enabled" value="1" <?= $callsignEnabled ? 'checked' : '' ?>>
    <label class="form-check-label" for="callsignLookupEnabled">
      Enable callsign auto-complete for the QSO form
    </label>
  </div>
  <p class="form-text mb-3">
    When enabled, typing a callsign in the add-QSO form fetches name / QTH / grid square
    from the enabled providers below in the listed order. First useful answer wins; the
    result is cached for 90 days. Disable globally to stop all outbound lookups.
  </p>

  <div class="form-fieldset mb-3">
    <span class="form-fieldset__legend">Provider chain</span>
    <p class="form-text mb-2">
      Tick to enable. Top-to-bottom order = priority. The local directory should usually
      be first so admin-curated data wins before external scrapers run.
    </p>
    <?php foreach ($providerMap as $code => $label): ?>
      <div class="d-flex align-items-center justify-content-between gap-2 py-1">
        <div class="form-check mb-0 flex-grow-1">
          <input type="checkbox" class="form-check-input" id="cs_provider_<?= h($code) ?>"
                 name="callsign_provider[<?= h($code) ?>]" value="1"
                 <?= in_array($code, $enabledProviders, true) ? 'checked' : '' ?>>
          <label class="form-check-label" for="cs_provider_<?= h($code) ?>">
            <code><?= h($code) ?></code> — <?= h($label) ?>
          </label>
        </div>
        <a class="btn btn-outline-secondary btn-sm"
           href="/admin/callsign-lookups/provider/<?= h($code) ?>">Settings &rarr;</a>
      </div>
    <?php endforeach; ?>
  </div>

  <button class="btn btn-primary btn-sm">Save settings</button>
<?= $this->Form->end() ?>

<hr class="my-4">

<!-- ── Data-source summary ─────────────────────────────────────────── -->
<!--
  The chain has two distinct stores. Show both counts side-by-side so an
  admin who just uploaded a CSV to the local directory doesn't go looking
  for those rows in the cache table below.
-->
<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card p-3 h-100">
      <h2 class="h6 mb-1">Local directory <span class="badge bg-info ms-1">provider/local</span></h2>
      <p class="display-6 mb-0"><?= h($directoryCount) ?></p>
      <p class="text-muted small mb-2">Admin-uploaded rows. Always checked first.</p>
      <a class="btn btn-outline-primary btn-sm" href="/admin/callsign-lookups/provider/local">
        Manage local directory &rarr;
      </a>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3 h-100">
      <h2 class="h6 mb-1">External cache <span class="badge bg-secondary ms-1">callsign_lookups</span></h2>
      <p class="display-6 mb-0"><?= h($totalCount) ?></p>
      <p class="text-muted small mb-0">Auto-filled when the QSO form looks up a callsign and an external provider returns data. Listed below.</p>
    </div>
  </div>
</div>

<p class="mb-3">
  <a class="btn btn-outline-primary btn-sm" href="/admin/callsign-lookups/all">
    View all known callsigns (combined) &rarr;
  </a>
</p>

<!-- ── Cache list ──────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
  <div>
    <h2 class="h5 mb-0">External lookup cache</h2>
    <p class="form-text small mb-0">
      Rows here come from the QSO form's auto-complete contacting an external
      provider. CSV-uploaded rows live in the
      <a href="/admin/callsign-lookups/provider/local">local directory</a> instead.
    </p>
  </div>
  <?php if ($totalCount > 0): ?>
    <?= $this->Form->postLink('Clear all cached lookups', '/admin/callsign-lookups/clear', [
        'class'   => 'btn btn-outline-danger btn-sm',
        'confirm' => 'Delete every cached row? The chain will re-fetch on demand. QSO history is untouched.',
    ]) ?>
  <?php endif; ?>
</div>

<form method="get" class="mb-3" action="/admin/callsign-lookups">
  <div class="input-group" style="max-width: 360px;">
    <input type="search" name="q" value="<?= h($q) ?>" class="form-control form-control-sm"
           placeholder="Search callsign…" autocapitalize="characters">
    <button class="btn btn-secondary btn-sm">Search</button>
    <?php if ($q !== ''): ?>
      <a href="/admin/callsign-lookups" class="btn btn-outline-secondary btn-sm">Clear</a>
    <?php endif; ?>
  </div>
</form>

<?php if ($lookups->count() === 0): ?>
  <?= $this->element('ui/empty_state', [
      'message' => $q !== ''
          ? 'No cached lookups match that search.'
          : 'No external lookups cached yet. Rows appear here once a user types a callsign in the QSO form and an external provider returns useful data. Bulk imports go to the local directory instead.',
  ]) ?>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Callsign</th>
          <th>Name</th>
          <th>QTH</th>
          <th>Country</th>
          <th>Grid</th>
          <th>Source</th>
          <th>Fetched</th>
          <th>Expires</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lookups as $row): ?>
          <tr>
            <td><strong><?= h($row->callsign) ?></strong></td>
            <td><?= h($row->name ?? '—') ?></td>
            <td><?= h($row->qth ?? '—') ?></td>
            <td><?= h($row->country ?? '—') ?></td>
            <td><?= h($row->grid_square ?? '—') ?></td>
            <td><code><?= h($row->source) ?></code></td>
            <td class="small text-muted"><?= h($row->fetched_at?->format('Y-m-d H:i')) ?></td>
            <td class="small text-muted">
              <?php if ($row->expires_at): ?>
                <?= h($row->expires_at->format('Y-m-d')) ?>
              <?php else: ?>
                <em>never</em>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a class="btn btn-outline-primary btn-sm" href="/admin/callsign-lookups/<?= h($row->id) ?>/edit">Edit</a>
              <?= $this->Form->postLink('Delete', '/admin/callsign-lookups/' . $row->id . '/delete', [
                  'class'   => 'btn btn-outline-danger btn-sm',
                  'confirm' => sprintf('Delete the cached entry for %s?', $row->callsign),
              ]) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?= $this->Paginator->numbers() ?>
<?php endif; ?>
