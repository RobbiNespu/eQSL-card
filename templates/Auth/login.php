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
      <div class="password-field">
        <?= $this->Form->control('password', [
            'type'         => 'password',
            'class'        => 'form-control',
            'label'        => false,
            'id'           => 'password',
            'autocomplete' => 'current-password',
            'required'     => true,
            'templates'    => ['inputContainer' => '{{content}}'],
        ]) ?>
        <button type="button" class="password-toggle" data-toggle="password" data-target="#password"
                aria-label="Show password" aria-pressed="false" title="Show password">
          <svg class="pw-icon pw-icon--show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
          <svg class="pw-icon pw-icon--hide" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
        </button>
      </div>
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
