<h1>Step 2 — Database</h1>
<?= $this->Form->create(null) ?>
<div class="mb-3"><label>Host</label><?= $this->Form->control('host', ['default' => $data['host'] ?? 'localhost', 'class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Port</label><?= $this->Form->control('port', ['default' => '3306', 'class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Database name</label><?= $this->Form->control('database', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>User</label><?= $this->Form->control('username', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Password</label><?= $this->Form->control('password', ['type' => 'password', 'class' => 'form-control', 'label' => false]) ?></div>
<hr>
<h2>SMTP (optional, can be configured later)</h2>
<div class="mb-3"><label>SMTP host</label><?= $this->Form->control('smtp_host', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>SMTP user</label><?= $this->Form->control('smtp_user', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>SMTP password</label><?= $this->Form->control('smtp_pass', ['type' => 'password', 'class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>From address</label><?= $this->Form->control('smtp_from', ['class' => 'form-control', 'label' => false]) ?></div>
<button class="btn btn-primary">Save & continue</button>
<?= $this->Form->end() ?>
