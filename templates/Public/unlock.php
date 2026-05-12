<?php
/**
 * Public share password gate (M2-T15).
 *
 * Rendered for GET /qsl/{slug}/unlock and re-rendered on a wrong-password
 * POST. Submits back to the same URL; the controller verifies against
 * `cards.share_password_hash` (Argon2id) and on success writes a per-slug
 * session flag before redirecting to `/qsl/{slug}`.
 *
 * @var \App\View\AppView $this
 * @var string $slug
 * @var string $title
 */
?>
<div style="max-width: 420px; margin: 0 auto;">
  <h1>Password required</h1>
  <p>This shared eQSL card is password-protected. Enter the password the sender gave you to view it.</p>

  <?= $this->Form->create(null, ['url' => '/qsl/' . $slug . '/unlock']) ?>
    <div class="field">
      <label class="form-label" for="password">Password</label>
      <input type="password" id="password" name="password" class="form-control"
             autocomplete="current-password" required>
    </div>
    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-primary">Unlock</button>
    </div>
  <?= $this->Form->end() ?>
</div>
