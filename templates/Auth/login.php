<?php
/**
 * M4-T14 banner: when the login flow redirects with `?unverified=1&email=...`
 * (full wiring lands in M4-T19 polish), we render a "resend verification"
 * form here. The banner is template-only today; the form posts to
 * `/email/verify/resend`, which is rate-limited and silent on
 * non-existent / already-verified accounts.
 */
$prefilledEmail = (string)$this->getRequest()->getQuery('email', '');
$showResend = $this->getRequest()->getQuery('unverified', '') === '1';
?>
<?php if ($showResend) : ?>
  <div class="alert alert-warning">
    Your email isn't verified yet.
    <?= $this->Form->create(null, ['url' => '/email/verify/resend', 'class' => 'd-inline']) ?>
    <input type="hidden" name="email" value="<?= h($prefilledEmail) ?>">
    <button class="btn btn-sm btn-outline-warning">Resend verification email</button>
    <?= $this->Form->end() ?>
  </div>
<?php endif; ?>
<h1>Sign in</h1>
<?= $this->Form->create(null) ?>
<div class="mb-3"><label>Email</label><?= $this->Form->control('email', ['type' => 'email', 'class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Password</label><?= $this->Form->control('password', ['type' => 'password', 'class' => 'form-control', 'label' => false]) ?></div>
<button class="btn btn-primary">Sign in</button>
<a href="/password/forgot" class="btn btn-link">Forgot password?</a>
<?= $this->Form->end() ?>
