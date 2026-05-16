<h1><?= h($title) ?></h1>
<p class="form-text">
  Configure which providers the QSO form's auto-complete consults, and in what
  order. Use the "Settings →" buttons per row for provider-specific config
  (the local directory has CSV upload there, for example). To browse every
  callsign the install knows about, jump to the combined list.
</p>

<p class="mb-4">
  <a class="btn btn-primary" href="/admin/callsign-lookups/all">
    View all known callsigns &rarr;
  </a>
</p>

<!-- ── Source-of-data settings ──────────────────────────────────────── -->
<h2 class="h5">Source of data</h2>
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
