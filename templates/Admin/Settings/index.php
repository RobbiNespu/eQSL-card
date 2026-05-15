<h1><?= h($title) ?></h1>
<p>Site-wide configuration: default eQSL background, SMTP, callsign-lookup providers, security toggles, retention.</p>

<h2>Default background</h2>
<p class="form-text mb-3">
  Image used when a guest generates an eQSL without uploading their own background.
  Falls back to the bundled <code>_demo-bg.jpg</code> when no admin override is set.
</p>
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <?php if ($hasCustomBg): ?>
      <p class="form-text mb-2"><strong>Active:</strong> admin override</p>
      <img src="/files/templates/_default-bg.jpg?v=<?= h(filemtime(WWW_ROOT . 'files/templates/_default-bg.jpg')) ?>"
           class="img-fluid rounded" style="max-width: 240px" alt="default bg" loading="lazy">
      <?= $this->Form->postLink('Reset to bundled default', '/admin/settings/background/reset', [
          'class'   => 'btn btn-sm btn-outline-danger mt-2',
          'confirm' => 'Delete admin override and fall back to the bundled background?',
      ]) ?>
    <?php elseif ($hasBundledBg): ?>
      <p class="form-text mb-2"><strong>Active:</strong> bundled fallback (<code>_demo-bg.jpg</code>)</p>
      <img src="/files/templates/_demo-bg.jpg" class="img-fluid rounded" style="max-width: 240px" alt="bundled bg" loading="lazy">
    <?php else: ?>
      <p class="form-text text-danger">No background available — guests cannot generate eQSLs without uploading.</p>
    <?php endif; ?>
  </div>
  <div class="col-md-8">
    <?= $this->Form->create(null, ['url' => '/admin/settings/background', 'type' => 'file']) ?>
      <div class="field">
        <label class="form-label" for="default_background">Upload a new default background</label>
        <input type="file" id="default_background" name="default_background"
               accept="image/jpeg,image/png,image/webp" class="form-control" required>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="field">
            <label class="form-label" for="default_background_author">Author / photographer</label>
            <input type="text" id="default_background_author" name="default_background_author"
                   value="<?= h($settings['default_background_author'] ?? '') ?>"
                   class="form-control" placeholder="Leave blank if unknown">
          </div>
        </div>
        <div class="col-md-6">
          <div class="field">
            <label class="form-label" for="default_background_license">License</label>
            <select id="default_background_license" name="default_background_license" class="form-select">
              <?php $currentLicense = (string)($settings['default_background_license'] ?? 'unknown'); ?>
              <?php foreach (\App\Service\ImageLicense::options($currentLicense) as $code => $label): ?>
                <option value="<?= h($code) ?>"<?= $code === $currentLicense ? ' selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <button class="btn btn-primary mt-3">Save background</button>
      <p class="form-text mt-2">Auto-resized to fit a 2000×1500 bounding box and saved as JPEG. Attribution shows on every card that falls back to this image.</p>
    <?= $this->Form->end() ?>
  </div>
</div>

<?= $this->Form->create(null) ?>

<h2>General</h2>
<div class="row g-3">
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="site_name">Site name</label>
      <input type="text" id="site_name" name="site_name"
             value="<?= h($settings['site_name'] ?? 'eQSL Card') ?>" class="form-control">
    </div>
  </div>
  <div class="col-md-3">
    <div class="field">
      <label class="form-label" for="max_upload_mb">Max upload (MB)</label>
      <input type="number" id="max_upload_mb" name="max_upload_mb"
             value="<?= h($settings['max_upload_mb'] ?? 2) ?>" class="form-control">
      <p class="form-text">Post-optimize cap.</p>
    </div>
  </div>
  <div class="col-md-12">
    <div class="field">
      <label class="form-label" for="share_base_url">Share base URL (og:url)</label>
      <input type="text" id="share_base_url" name="share_base_url"
             value="<?= h($settings['share_base_url'] ?? '') ?>"
             class="form-control" placeholder="https://yourdomain">
    </div>
  </div>
</div>

<h2>eQSL credit footer</h2>
<p class="form-text mb-2">
  Drawn at the bottom of every generated card. One physical line per row.
  Placeholders: <code>{year}</code> resolves to the current 4-digit year and
  <code>{generated_at}</code> to an ISO-8601 timestamp at render time.
  Leave empty to use the bundled default.
</p>
<?php $creditDefault = implode("\n", \App\Service\CardRenderer::DEFAULT_CREDIT_FOOTER); ?>
<div class="field">
  <label class="form-label" for="eqsl_credit_template">Footer template</label>
  <textarea id="eqsl_credit_template" name="eqsl_credit_template" class="form-control" rows="4"
            placeholder="<?= h($creditDefault) ?>"><?= h($settings['eqsl_credit_template'] ?? '') ?></textarea>
</div>

<h2>Security</h2>
<?php $bypass = (bool)($settings['rate_limit_private_ip_bypass'] ?? true); ?>
<div class="form-check mb-2">
  <input type="hidden" name="rate_limit_private_ip_bypass" value="0">
  <input type="checkbox" class="form-check-input" id="rateLimitBypass"
         name="rate_limit_private_ip_bypass" value="1" <?= $bypass ? 'checked' : '' ?>>
  <label class="form-check-label" for="rateLimitBypass">
    Skip login rate limit for non-public IPs (loopback, RFC1918, Docker bridge)
  </label>
</div>
<p class="form-text">
  When <strong>on</strong> (default), requests from <code>127.0.0.0/8</code>, <code>::1</code>,
  <code>10/8</code>, <code>172.16/12</code>, <code>192.168/16</code>, and IPv6 ULA/link-local
  bypass the <code>/login</code> and <code>/qsl/&hellip;/unlock</code> throttles entirely.
  Useful while iterating locally; turn off if your prod box might receive private-IP traffic
  you want to throttle.
</p>
<p class="form-text mb-4">
  <strong>Locked out?</strong> Open a shell on the database and run:<br>
  <code>UPDATE app_settings SET value='true', updated_at=NOW() WHERE `key`='rate_limit_private_ip_bypass';</code><br>
  Then clear the rate-limit buckets so accumulated stamps don't keep throttling you:<br>
  <code>rm -f tmp/cache/rate_limits/*</code> (on the app server).
</p>

<h2>Callsign auto-complete</h2>
<?php
$callsignEnabled = (bool)($settings['callsign_lookup_enabled'] ?? false);
$enabledProviders = array_filter(array_map('trim', explode(',', (string)($settings['callsign_lookup_providers'] ?? ''))));
$providerMap = [
    'local'       => 'Local directory — admin-imported CSV (recommended FIRST)',
    'radioid'     => 'RadioID.net — worldwide DMR registry, JSON API',
    'radioid_api' => 'RadioID API (users) — broader users endpoint; behind Cloudflare',
    'qrz'         => 'QRZ.com — requires paid XML key, currently disabled',
    'mcmc'        => 'MCMC Malaysia — live scrape of the apparatus-assignments register (9M / 9W)',
    'marts'       => 'MARTS Malaysia — use local directory; site unstable',
    'rapi'        => 'Indonesia RAPI — use local directory; PDF-only sources',
];
?>
<div class="form-check mb-2">
  <input type="hidden" name="callsign_lookup_enabled" value="0">
  <input type="checkbox" class="form-check-input" id="callsignLookupEnabled"
         name="callsign_lookup_enabled" value="1" <?= $callsignEnabled ? 'checked' : '' ?>>
  <label class="form-check-label" for="callsignLookupEnabled">
    Enable callsign auto-complete for the QSO form
  </label>
</div>
<p class="form-text mb-3">
  When on, the QSO add form fetches name / QTH / grid for the typed callsign
  from the enabled providers below, in the listed order. The first provider
  that returns useful data wins; the result is cached for 90 days. Disable
  globally to stop all outbound lookups (useful while developing offline
  or while a provider is misbehaving).
</p>

<div class="form-fieldset mb-4">
  <span class="form-fieldset__legend">Provider order</span>
  <p class="form-text mb-3">
    Tick to enable. Order top-to-bottom defines priority — change the order
    by un-ticking and re-ticking in your preferred sequence. Stub providers
    (QRZ, MCMC, MARTS, RAPI) currently return no data; safe to leave enabled
    while waiting for the scraper implementations.
  </p>
  <?php foreach ($providerMap as $code => $label): ?>
    <div class="form-check">
      <input type="checkbox" class="form-check-input" id="callsign_provider_<?= h($code) ?>"
             name="callsign_provider[<?= h($code) ?>]" value="1"
             <?= in_array($code, $enabledProviders, true) ? 'checked' : '' ?>>
      <label class="form-check-label" for="callsign_provider_<?= h($code) ?>">
        <code><?= h($code) ?></code> — <?= h($label) ?>
      </label>
    </div>
  <?php endforeach; ?>
</div>

<h2>Storage retention</h2>
<div class="field" style="max-width: 320px;">
  <label class="form-label" for="card_retention_days">Card retention (days)</label>
  <input type="number" id="card_retention_days" name="card_retention_days" min="0"
         value="<?= h($settings['card_retention_days'] ?? 0) ?>" class="form-control">
  <p class="form-text">
    Soft-delete user-owned cards older than this many days when an admin runs
    <a href="/admin/cleanup">Cleanup → Expire old cards</a>. Storage is
    reclaimed by the subsequent <strong>Prune orphans</strong> sweep.
    Set to <strong>0</strong> (default) to keep cards forever.
  </p>
</div>

<h2>SMTP <span class="form-label small">(overrides config/app_local.php at runtime if set)</span></h2>
<div class="row g-3">
  <div class="col-md-8">
    <div class="field">
      <label class="form-label" for="smtp_host">SMTP host</label>
      <input type="text" id="smtp_host" name="smtp_host"
             value="<?= h($settings['smtp_host'] ?? '') ?>" class="form-control">
    </div>
  </div>
  <div class="col-md-4">
    <div class="field">
      <label class="form-label" for="smtp_port">SMTP port</label>
      <input type="number" id="smtp_port" name="smtp_port"
             value="<?= h($settings['smtp_port'] ?? 587) ?>" class="form-control">
    </div>
  </div>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="smtp_user">SMTP username</label>
      <input type="text" id="smtp_user" name="smtp_user"
             value="<?= h($settings['smtp_user'] ?? '') ?>" class="form-control">
    </div>
  </div>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="smtp_pass">SMTP password</label>
      <input type="password" id="smtp_pass" name="smtp_pass"
             value="<?= h($settings['smtp_pass'] ?? '') ?>" class="form-control"
             autocomplete="new-password">
    </div>
  </div>
  <div class="col-md-12">
    <div class="field">
      <label class="form-label" for="smtp_from">From address</label>
      <input type="email" id="smtp_from" name="smtp_from"
             value="<?= h($settings['smtp_from'] ?? '') ?>" class="form-control">
    </div>
  </div>
</div>

<div class="d-flex gap-2 mt-4">
  <button class="btn btn-primary">Save settings</button>
</div>
<?= $this->Form->end() ?>
