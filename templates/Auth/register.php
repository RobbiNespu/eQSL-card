<div style="max-width: 480px; margin: 0 auto;">

  <h1>Create account</h1>
  <p>Set up your station so you can log QSOs and generate eQSL cards.</p>

  <?= $this->Form->create($user) ?>
    <div class="field">
      <label class="form-label" for="name">Name</label>
      <?= $this->Form->control('name', [
          'class' => 'form-control',
          'label' => false,
          'id'    => 'name',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>

    <div class="field">
      <label class="form-label" for="callsign">Callsign</label>
      <?= $this->Form->control('callsign', [
          'class' => 'form-control',
          'label' => false,
          'id'    => 'callsign',
          'autocapitalize' => 'characters',
          'autocomplete' => 'off',
          'spellcheck' => 'false',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>

    <div class="field">
      <label class="form-label" for="email">Email</label>
      <?= $this->Form->control('email', [
          'type'  => 'email',
          'class' => 'form-control',
          'label' => false,
          'id'    => 'email',
          'autocomplete' => 'email',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>

    <div class="field">
      <label class="form-label" for="password">Password</label>
      <?= $this->Form->control('password', [
          'type'  => 'password',
          'class' => 'form-control',
          'label' => false,
          'id'    => 'password',
          'autocomplete' => 'new-password',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
      <p class="form-text">Use at least 8 characters. Longer passphrases are stronger.</p>
    </div>

    <div class="field">
      <label class="form-label" for="password_confirm">Confirm password</label>
      <?= $this->Form->control('password_confirm', [
          'type'  => 'password',
          'class' => 'form-control',
          'label' => false,
          'id'    => 'password_confirm',
          'autocomplete' => 'new-password',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>

    <div class="d-flex align-items-center gap-2 mt-4">
      <button class="btn btn-primary">Create account</button>
      <a class="btn btn-link" href="/login">I already have an account</a>
    </div>
  <?= $this->Form->end() ?>
</div>
