<h1><?= h($title) ?></h1>

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
