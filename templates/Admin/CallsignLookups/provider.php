<h1><?= h($title) ?></h1>
<p class="form-text">
  <a href="/admin/callsign-lookups">&larr; Back to callsign auto-complete</a>
</p>

<div class="card mb-4">
  <div class="card-body">
    <h2 class="h5 mb-2"><code><?= h($code) ?></code> &mdash; <?= h($label) ?></h2>
    <p class="mb-2"><?= h($description) ?></p>
    <p class="mb-0">
      Status:
      <?php if ($isEnabled): ?>
        <span class="badge bg-success">Enabled in chain</span>
      <?php else: ?>
        <span class="badge bg-secondary">Disabled</span>
      <?php endif; ?>
      &nbsp;&middot;&nbsp;
      <span class="text-muted">Cached rows from this source: <strong><?= h($rowCount) ?></strong></span>
    </p>
  </div>
</div>

<?php if ($code === 'radioid_database_dump'): ?>
<div class="card mb-4">
  <div class="card-body">
    <h2 class="h5">Local lookup cache</h2>
    <p class="mb-1">
      Rows currently cached: <strong><?= h(number_format($registryCount ?? 0)) ?></strong>
    </p>
    <p class="mb-3 text-muted small">
      <?php if (!empty($registryLastImport)): ?>
        Last synced <?= h($registryLastImport) ?> UTC.
      <?php else: ?>
        Cache is empty — click below to populate it from the upstream registry.
      <?php endif; ?>
    </p>
    <?= $this->Form->postLink(
        ($registryCount ? '↻ Sync now' : '↓ Populate cache now (first run)'),
        '/admin/callsign-lookups/provider/radioid_database_dump/refresh',
        [
            'class'   => 'btn btn-primary btn-sm',
            'confirm' => 'Pull the latest RadioID user registry into the local cache? Takes 5–15 seconds.',
        ]
    ) ?>
    <p class="form-text small mt-2 mb-0">
      Sync pulls the user registry that <strong>RadioID</strong> publishes
      for download, stores it in a local table on this install, and uses
      it to answer subsequent callsign lookups without further upstream
      calls. The dataset is not redistributed by this app — it's
      internal lookup storage only. Use this sync sparingly (weekly is
      plenty) so we stay within the spirit of
      <a href="https://radioid.net/api_use_policy" rel="noopener">RadioID's API use policy</a>.
    </p>
  </div>
</div>
<?php endif; ?>

<h2 class="h5">Settings</h2>
<p class="form-text">
  This provider currently has no configurable settings.
  <?php if ($code === 'qrz'): ?>
    QRZ requires a paid XML key — when that integration is finished, the API
    key field will land here.
  <?php elseif ($code === 'radioid_database_dump'): ?>
    Lookups are served from a local cache populated on demand from the
    RadioID user registry. The cache is internal storage — we don't
    re-publish the dataset, we just hold it to keep the QSO form's
    auto-fill instant without pinging the upstream on every keystroke.
    Sync on the cadence that suits your traffic; weekly is comfortable
    and matches RadioID's expectation of polite, low-frequency fetches
    (see their <a href="https://radioid.net/api_use_policy" rel="noopener">API use policy</a>).
  <?php elseif ($code === 'radioid_api'): ?>
    Calls <code>https://radioid.net/api/users?callsign=…</code>, the broader
    users endpoint at RadioID.net. The site is occasionally fronted by
    Cloudflare's bot challenge — our requests send browser-shaped headers
    (User-Agent, Accept, Referer, X-Requested-With) which clear the casual
    "no UA" heuristic, but if Cloudflare serves a real challenge page we
    log the failure and the chain continues to the next provider. No
    server-side challenge solver is built in (and won't be); if blocks
    become persistent, route this provider via a residential proxy or
    fall back on the local directory.
  <?php elseif ($code === 'mcmc'): ?>
    MCMC is scraped live from the public apparatus-assignments register; no
    credentials are needed. Future knobs (cache TTL override, prefix
    allow-list) will land here.
  <?php elseif ($code === 'marts'): ?>
    MARTS is currently a stub (the site is unstable). For Malaysia 9M / 9W
    callsigns, upload a CSV to the <a href="/admin/callsign-lookups/provider/local">local directory</a>
    instead — it's checked first in the chain.
  <?php elseif ($code === 'rapi'): ?>
    RAPI is currently a stub (sources are PDF-only). For Indonesian YB / YC
    callsigns, extract from the official PDFs and upload to the
    <a href="/admin/callsign-lookups/provider/local">local directory</a>.
  <?php endif; ?>
</p>

<h2 class="h5 mt-4">Toggle in chain</h2>
<p class="form-text mb-3">
  Enable / disable / reorder this provider on the
  <a href="/admin/callsign-lookups">main settings page</a> — the provider
  chain is configured there so the order is visible at a glance.
</p>
