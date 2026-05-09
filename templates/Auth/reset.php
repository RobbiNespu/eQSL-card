<h1>Reset password</h1>
<?= $this->Form->create(null) ?>
<?= $this->Form->hidden('_token', ['value' => $token]) ?>
<?= $this->Form->control('password', ['type' => 'password', 'class' => 'form-control', 'label' => 'New password']) ?>
<button class="btn btn-primary">Reset</button>
<?= $this->Form->end() ?>
