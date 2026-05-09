<h1>Sign in</h1>
<?= $this->Form->create(null) ?>
<div class="mb-3"><label>Email</label><?= $this->Form->control('email', ['type' => 'email', 'class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Password</label><?= $this->Form->control('password', ['type' => 'password', 'class' => 'form-control', 'label' => false]) ?></div>
<button class="btn btn-primary">Sign in</button>
<a href="/password/forgot" class="btn btn-link">Forgot password?</a>
<?= $this->Form->end() ?>
