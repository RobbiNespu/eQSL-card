<h1>Create account</h1>
<?= $this->Form->create($user) ?>
<div class="mb-3"><label>Name</label><?= $this->Form->control('name', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Callsign</label><?= $this->Form->control('callsign', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Email</label><?= $this->Form->control('email', ['type' => 'email', 'class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Password</label><?= $this->Form->control('password', ['type' => 'password', 'class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Confirm password</label><?= $this->Form->control('password_confirm', ['type' => 'password', 'class' => 'form-control', 'label' => false]) ?></div>
<button class="btn btn-primary">Register</button>
<?= $this->Form->end() ?>
