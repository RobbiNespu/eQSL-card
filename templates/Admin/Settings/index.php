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
    <button class="btn btn-primary">Save background</button>
    <p class="form-text small">Auto-resized to fit a 2000×1500 bounding box and saved as JPEG.</p>
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
