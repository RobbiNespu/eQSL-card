<h1>Step 4 — Create admin account</h1>
<?= $this->Form->create(null) ?>
<div class="mb-3"><label>Display name</label><?= $this->Form->control('name', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Callsign</label><?= $this->Form->control('callsign', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Email</label><?= $this->Form->control('email', ['class' => 'form-control', 'type' => 'email', 'label' => false]) ?></div>
<div class="mb-3"><label>Password</label><?= $this->Form->control('password', ['class' => 'form-control', 'type' => 'password', 'label' => false]) ?></div>
<button class="btn btn-primary">Create admin & finish</button>
<?= $this->Form->end() ?>
