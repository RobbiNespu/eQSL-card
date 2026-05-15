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
      <div class="form-check">
        <input type="checkbox" class="form-check-input" id="cs_provider_<?= h($code) ?>"
               name="callsign_provider[<?= h($code) ?>]" value="1"
               <?= in_array($code, $enabledProviders, true) ? 'checked' : '' ?>>
        <label class="form-check-label" for="cs_provider_<?= h($code) ?>">
          <code><?= h($code) ?></code> — <?= h($label) ?>
        </label>
      </div>
    <?php endforeach; ?>
  </div>

  <button class="btn btn-primary btn-sm">Save settings</button>
<?= $this->Form->end() ?>

<hr class="my-4">

<!-- ── Cache list ──────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
  <h2 class="h5 mb-0">Cached callsigns <span class="text-muted">(<?= h($totalCount) ?>)</span></h2>
  <?= $this->Form->postLink('Clear all cached lookups', '/admin/callsign-lookups/clear', [
      'class'   => 'btn btn-outline-danger btn-sm',
      'confirm' => 'Delete every cached row? The chain will re-fetch on demand. QSO history is untouched.',
  ]) ?>
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
          : 'The cache is empty. Entries will appear here once a user looks up a callsign in the QSO form.',
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
