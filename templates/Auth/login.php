<?php
/**
 * M4-T14 banner: when the login flow redirects with `?unverified=1&email=...`
 * (full wiring lands in M4-T19 polish), we render a "resend verification"
 * form here. The banner is template-only today; the form posts to
 * `/email/verify/resend`, which is rate-limited and silent on
 * non-existent / already-verified accounts.
 */
$prefilledEmail = (string)$this->getRequest()->getQuery('email', '');
$showResend     = $this->getRequest()->getQuery('unverified', '') === '1';
?>
<div style="max-width: 420px; margin: 0 auto;">

  <?php if ($showResend) : ?>
    <div class="alert alert-warning">
      Your email isn't verified yet.
      <?= $this->Form->create(null, ['url' => '/email/verify/resend', 'class' => 'd-inline']) ?>
        <input type="hidden" name="email" value="<?= h($prefilledEmail) ?>">
        <button class="btn btn-sm btn-outline-warning ms-1">Resend verification email</button>
      <?= $this->Form->end() ?>
    </div>
  <?php endif; ?>

  <h1>Sign in</h1>
  <p>Welcome back. Enter your email and password to continue.</p>

  <?= $this->Form->create(null) ?>
    <div class="field">
      <label class="form-label" for="email">Email</label>
      <?= $this->Form->control('email', [
          'type'         => 'email',
          'class'        => 'form-control',
          'label'        => false,
          'id'           => 'email',
          'autocomplete' => 'username',
          'value'        => $prefilledEmail ?: null,
          'required'     => true,
          'templates'    => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>

    <div class="field">
      <label class="form-label" for="password">Password</label>
      <?= $this->Form->control('password', [
          'type'         => 'password',
          'class'        => 'form-control',
          'label'        => false,
          'id'           => 'password',
          'autocomplete' => 'current-password',
          'required'     => true,
          'templates'    => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>

    <div class="d-flex align-items-center gap-2 mt-4">
      <button class="btn btn-primary">Sign in</button>
      <a class="btn btn-link" href="/password/forgot">Forgot password?</a>
    </div>
  <?= $this->Form->end() ?>

  <p class="form-text mt-4">
    Don't have an account yet? <a href="/register">Create one</a>.
  </p>
</div>
