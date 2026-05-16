<div style="max-width: 420px; margin: 0 auto;">
  <h1>Forgot password</h1>
  <p>Enter the email you registered with. If we have an account on file, we'll send a reset link.</p>

  <?= $this->Form->create(null) ?>
    <div class="field">
      <label class="form-label" for="email">Email</label>
      <?= $this->Form->control('email', [
          'type' => 'email', 'class' => 'form-control', 'label' => false, 'id' => 'email',
          'autocomplete' => 'username', 'required' => true,
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>

    <div class="d-flex align-items-center gap-2 mt-4">
      <button class="btn btn-primary">Send reset link</button>
      <a class="btn btn-link" href="/login">Back to sign-in</a>
    </div>
  <?= $this->Form->end() ?>
</div>
