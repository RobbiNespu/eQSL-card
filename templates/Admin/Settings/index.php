<h1><?= h($title) ?></h1>

<h2>Default background</h2>
<p class="text-muted small">
  Image used when a guest generates an eQSL without uploading their own background.
  Falls back to the bundled <code>_demo-bg.jpg</code> when no admin override is set.
</p>
<div class="row mb-4">
  <div class="col-md-4">
    <?php if ($hasCustomBg): ?>
      <p class="small mb-1"><strong>Active:</strong> admin override</p>
      <img src="/files/templates/_default-bg.jpg?v=<?= h(filemtime(WWW_ROOT . 'files/templates/_default-bg.jpg')) ?>" class="img-thumbnail" style="max-width: 240px" alt="default bg">
      <?= $this->Form->create(null, ['url' => '/admin/settings/background/reset']) ?>
      <button class="btn btn-sm btn-outline-danger mt-2" onclick="return confirm('Delete admin override and fall back to the bundled background?')">Reset to bundled default</button>
      <?= $this->Form->end() ?>
    <?php elseif ($hasBundledBg): ?>
      <p class="small mb-1"><strong>Active:</strong> bundled fallback (<code>_demo-bg.jpg</code>)</p>
      <img src="/files/templates/_demo-bg.jpg" class="img-thumbnail" style="max-width: 240px" alt="bundled bg">
    <?php else: ?>
      <p class="text-danger small">No background available — guests cannot generate eQSLs without uploading.</p>
    <?php endif; ?>
  </div>
  <div class="col-md-6">
    <?= $this->Form->create(null, ['url' => '/admin/settings/background', 'type' => 'file']) ?>
    <label class="form-label">Upload a new default background</label>
    <input type="file" name="default_background" accept="image/jpeg,image/png,image/webp" class="form-control mb-2" required>

    <div class="row g-2 mb-2">
      <div class="col-md-6">
        <label class="form-label small">Author / photographer</label>
        <input type="text" name="default_background_author"
               value="<?= h($settings['default_background_author'] ?? '') ?>"
               class="form-control form-control-sm" placeholder="Leave blank if unknown">
      </div>
      <div class="col-md-6">
        <label class="form-label small">License</label>
        <select name="default_background_license" class="form-select form-select-sm">
          <?php $currentLicense = (string)($settings['default_background_license'] ?? 'unknown'); ?>
          <?php foreach (\App\Service\ImageLicense::options($currentLicense) as $code => $label): ?>
            <option value="<?= h($code) ?>"<?= $code === $currentLicense ? ' selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <button class="btn btn-primary">Save background</button>
    <p class="form-text small">Auto-resized to fit a 2000×1500 bounding box and saved as JPEG. Attribution shows on every card that falls back to this image.</p>
    <?= $this->Form->end() ?>
  </div>
</div>

<?= $this->Form->create(null) ?>
<h2>General</h2>
<div class="mb-3"><label>Site name</label>
  <input type="text" name="site_name" value="<?= h($settings['site_name'] ?? 'eQSL Card') ?>" class="form-control">
</div>
<div class="mb-3"><label>Max upload size (MB, post-optimize)</label>
  <input type="number" name="max_upload_mb" value="<?= h($settings['max_upload_mb'] ?? 2) ?>" class="form-control">
</div>
<div class="mb-3"><label>Share base URL (used in og:url)</label>
  <input type="text" name="share_base_url" value="<?= h($settings['share_base_url'] ?? '') ?>" class="form-control" placeholder="https://yourdomain">
</div>

<h2>eQSL credit footer</h2>
<p class="form-text small">
  Drawn at the bottom of every generated card. One physical line per row.
  Placeholders: <code>{year}</code> resolves to the current 4-digit year and
  <code>{generated_at}</code> to an ISO-8601 timestamp at render time.
  Leave empty to use the bundled default.
</p>
<?php
$creditDefault = implode("\n", \App\Service\CardRenderer::DEFAULT_CREDIT_FOOTER);
?>
<div class="mb-4">
  <textarea name="eqsl_credit_template" class="form-control" rows="4" placeholder="<?= h($creditDefault) ?>"><?= h($settings['eqsl_credit_template'] ?? '') ?></textarea>
</div>

<h2>Security</h2>
<?php $bypass = (bool)($settings['rate_limit_private_ip_bypass'] ?? true); ?>
<div class="mb-4">
  <div class="form-check">
    <input type="hidden" name="rate_limit_private_ip_bypass" value="0">
    <input type="checkbox" class="form-check-input" id="rateLimitBypass"
           name="rate_limit_private_ip_bypass" value="1" <?= $bypass ? 'checked' : '' ?>>
    <label class="form-check-label" for="rateLimitBypass">
      Skip login rate limit for non-public IPs (loopback, RFC1918, Docker bridge)
    </label>
  </div>
  <p class="form-text small">
    When <strong>on</strong> (default), requests from <code>127.0.0.0/8</code>, <code>::1</code>,
    <code>10/8</code>, <code>172.16/12</code>, <code>192.168/16</code>, and IPv6 ULA/link-local
    bypass the <code>/login</code> and <code>/qsl/&hellip;/unlock</code> throttles entirely.
    Useful while iterating locally; turn off if your prod box might receive private-IP traffic
    you want to throttle.
  </p>
  <p class="form-text small text-muted">
    <strong>Locked out?</strong> Open a shell on the database and run:<br>
    <code>UPDATE app_settings SET value='true', updated_at=NOW() WHERE `key`='rate_limit_private_ip_bypass';</code><br>
    Then clear the rate-limit buckets so accumulated stamps don't keep throttling you:<br>
    <code>rm -f tmp/cache/rate_limits/*</code> (on the app server).
  </p>
</div>

<h2>Callsign auto-complete</h2>
<?php
$callsignEnabled = (bool)($settings['callsign_lookup_enabled'] ?? false);
$enabledProviders = array_filter(array_map('trim', explode(',', (string)($settings['callsign_lookup_providers'] ?? ''))));
$providerMap = [
    'qrz'     => 'QRZ.com (worldwide, scrape — stub)',
    'mcmc'    => 'MCMC Malaysia (9M/9W, scrape — stub)',
    'marts'   => 'MARTS member directory (9M/9W, scrape — stub)',
    'radioid' => 'RadioID.net (worldwide DMR registry, JSON API)',
    'rapi'    => 'Indonesia RAPI (YB-YH/JZ, scrape — stub)',
];
?>
<div class="mb-4">
  <div class="form-check mb-3">
    <input type="hidden" name="callsign_lookup_enabled" value="0">
    <input type="checkbox" class="form-check-input" id="callsignLookupEnabled"
           name="callsign_lookup_enabled" value="1" <?= $callsignEnabled ? 'checked' : '' ?>>
    <label class="form-check-label" for="callsignLookupEnabled">
      Enable callsign auto-complete for the QSO form
    </label>
  </div>
  <p class="form-text small">
    When on, the QSO add form fetches name / QTH / grid for the typed callsign
    from the enabled providers below, in the listed order. The first provider
    that returns useful data wins; the result is cached for 90 days. Disable
    globally to stop all outbound lookups (useful while developing offline
    or while a provider is misbehaving).
  </p>

  <fieldset class="border rounded p-3 bg-light-subtle">
    <legend class="float-none w-auto small fw-bold px-2">Provider order</legend>
    <p class="form-text small mt-0">
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
  </fieldset>
</div>

<h2>Storage retention</h2>
<div class="mb-4">
  <label class="form-label">Card retention (days)</label>
  <input type="number" name="card_retention_days" min="0"
         value="<?= h($settings['card_retention_days'] ?? 0) ?>" class="form-control">
  <p class="form-text small">
    Soft-delete user-owned cards older than this many days when an admin runs
    <a href="/admin/cleanup">Cleanup → Expire old cards</a>. Storage is
    reclaimed by the subsequent <strong>Prune orphans</strong> sweep.
    Set to <strong>0</strong> (default) to keep cards forever.
  </p>
</div>

<h2>SMTP (overrides config/app_local.php at runtime if set)</h2>
<div class="row">
  <div class="col-md-6 mb-3"><label>SMTP host</label>
    <input type="text" name="smtp_host" value="<?= h($settings['smtp_host'] ?? '') ?>" class="form-control"></div>
  <div class="col-md-6 mb-3"><label>SMTP port</label>
    <input type="number" name="smtp_port" value="<?= h($settings['smtp_port'] ?? 587) ?>" class="form-control"></div>
  <div class="col-md-6 mb-3"><label>SMTP username</label>
    <input type="text" name="smtp_user" value="<?= h($settings['smtp_user'] ?? '') ?>" class="form-control"></div>
  <div class="col-md-6 mb-3"><label>SMTP password</label>
    <input type="password" name="smtp_pass" value="<?= h($settings['smtp_pass'] ?? '') ?>" class="form-control" autocomplete="new-password"></div>
  <div class="col-md-6 mb-3"><label>From address</label>
    <input type="email" name="smtp_from" value="<?= h($settings['smtp_from'] ?? '') ?>" class="form-control"></div>
</div>

<button class="btn btn-primary">Save settings</button>
<?= $this->Form->end() ?>
