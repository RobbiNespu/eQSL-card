<div style="max-width: 420px; margin: 0 auto;">
  <h1>Reset password</h1>
  <p>Set a new password below. The link you clicked is single-use and expires after an hour.</p>

  <?= $this->Form->create(null) ?>
    <?= $this->Form->hidden('_token', ['value' => $token]) ?>
    <div class="field">
      <label class="form-label" for="password">New password</label>
      <?= $this->Form->control('password', [
          'type' => 'password', 'class' => 'form-control', 'label' => false, 'id' => 'password',
          'autocomplete' => 'new-password', 'required' => true,
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
      <p class="form-text">At least 8 characters. Longer passphrases are stronger.</p>
    </div>

    <div class="d-flex align-items-center gap-2 mt-4">
      <button class="btn btn-primary">Reset password</button>
      <a class="btn btn-link" href="/login">Cancel</a>
    </div>
  <?= $this->Form->end() ?>
</div>
