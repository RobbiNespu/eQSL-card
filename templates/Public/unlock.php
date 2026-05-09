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
<h1>Password required</h1>
<p class="text-muted">This shared eQSL card is password-protected.</p>

<?= $this->Form->create(null, ['url' => '/qsl/' . $slug . '/unlock']) ?>
<div class="mb-3">
  <label class="form-label">Password</label>
  <input type="password" name="password" class="form-control" autocomplete="current-password" required>
</div>
<button class="btn btn-primary">Unlock</button>
<?= $this->Form->end() ?>
