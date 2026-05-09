<h1>Forgot password</h1>
<?= $this->Form->create(null) ?>
<?= $this->Form->control('email', ['type' => 'email', 'class' => 'form-control', 'label' => 'Email']) ?>
<button class="btn btn-primary">Send reset link</button>
<?= $this->Form->end() ?>
